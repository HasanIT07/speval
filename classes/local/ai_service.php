<?php
namespace mod_speval\local;

defined('MOODLE_INTERNAL') || die();

class ai_service {
    
    /**
     * Analyze all evaluations for an activity using AI
     * @param int $activityid The SPEval activity ID
     * @return array Analysis results
     */
    public static function analyze_evaluations($activityid) {
        global $DB;
        
        // Ensure grades exist for this activity before running AI
        if (!$DB->record_exists('speval_grades', ['activityid' => $activityid])) {
            throw new \moodle_exception('gradesnotcalculated', 'mod_speval');
        }

        // Get all evaluations for this activity
        $evaluations = $DB->get_records('speval_eval', ['activityid' => $activityid]);
        
        if (empty($evaluations)) {
            return [];
        }
        
        // Prepare data for AI analysis
        $analysis_data = self::prepare_analysis_data($evaluations);
        
        // Call AI module
        $ai_result = self::call_ai_module($analysis_data);
        
        if ($ai_result && isset($ai_result['status']) && $ai_result['status'] === 'success' && !empty($ai_result['results'])) {
            return self::store_analysis_results($activityid, $ai_result['results']);
        }
        
        return [];
    }
    
    /**
     * Prepare evaluation data for AI analysis
     * @param array $evaluations
     * @return array
     */
    private static function prepare_analysis_data($evaluations) {
        global $DB;
        $data = [];

        // Prefetch final grades for peers in this activity
        $activityid = reset($evaluations)->activityid;
        $grades = $DB->get_records('speval_grades', ['activityid' => $activityid], '', 'userid, id, finalgrade');
        
        foreach ($evaluations as $eval) {
            $peerfinal = isset($grades[$eval->peerid]) ? (float)$grades[$eval->peerid]->finalgrade : null;
            $data[] = [
                'id' => $eval->id,
                'userid' => $eval->userid,
                'peerid' => $eval->peerid,
                'activityid' => $eval->activityid,
                'criteria1' => $eval->criteria1,
                'criteria2' => $eval->criteria2,
                'criteria3' => $eval->criteria3,
                'criteria4' => $eval->criteria4,
                'criteria5' => $eval->criteria5,
                'finalgrade' => $peerfinal, // pass peer's computed final grade
                'comment1' => $eval->comment1 ?? '',
                'comment2' => '', // ignore comment2 per requirements
                'timecreated' => $eval->timecreated
            ];
        }
        
        return $data;
    }
    
    /**
     * Call the Python AI module
     * @param array $data
     * @return array|null
     */
    private static function call_ai_module($data) {
        global $CFG;
        
        // Get AI module path from config or use default
        $ai_module_path = get_config('mod_speval', 'ai_module_path');
        if (empty($ai_module_path)) {
            // Default path - adjust this to your actual path
            $ai_module_path = $CFG->dirroot . '/mod/speval/ai_module.py';
        }
        
        if (!file_exists($ai_module_path)) {
            debugging('AI module not found at: ' . $ai_module_path, DEBUG_DEVELOPER);
            return null;
        }
        
        try {
            // Prepare JSON input
            $json_data = json_encode($data);
            
            // Execute Python script
            $command = "python3 " . escapeshellarg($ai_module_path) . " --json";
            $descriptorspec = [
                0 => ["pipe", "r"],  // stdin
                1 => ["pipe", "w"],  // stdout
                2 => ["pipe", "w"]   // stderr
            ];
            
            $process = proc_open($command, $descriptorspec, $pipes);
            
            if (is_resource($process)) {
                // Write input data
                fwrite($pipes[0], $json_data);
                fclose($pipes[0]);
                
                // Read output
                $output = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                
                $return_value = proc_close($process);
                
                if ($return_value === 0 && $output) {
                    return json_decode($output, true);
                } else {
                    debugging('AI module error: ' . $error, DEBUG_DEVELOPER);
                    return null;
                }
            }
        } catch (Exception $e) {
            debugging('Error calling AI module: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        
        return null;
    }
    
    /**
     * Store AI analysis results in database
     * @param int $activityid
     * @param array $results
     * @return array
     */
    private static function store_analysis_results($activityid, $results) {
        global $DB;
        
        $stored_results = [];
        
        foreach ($results as $result) {
            if (isset($result['error'])) {
                continue; // Skip error results
            }
            // Get group information for this peer
            $group_info = self::get_peer_group_info($result['peer_id'], $activityid);

            // Map AI result to individual flags record
            $individual = [
                'userid' => $result['evaluator_id'],
                'peer' => $result['peer_id'],
                'activityid' => $activityid,
                'grouping' => $group_info['groupingid'] ?? null,
                'groupid' => $group_info['groupid'] ?? null,
                'commentdiscrepancy' => $result['comment_discrepancy_detected'] ? 1 : 0,
                'markdiscrepancy' => $result['mark_discrepancy_detected'] ? 1 : 0,
                'misbehaviorcategory' => isset($result['misbehaviour_category_index']) ? (int)$result['misbehaviour_category_index'] : 1,
                'timecreated' => $result['analysis_timestamp']
            ];

            // Upsert per unique (userid, peer, activityid)
            $existing = $DB->get_record('speval_flag_individual', [
                'userid' => $individual['userid'],
                'peer' => $individual['peer'],
                'activityid' => $activityid
            ]);

            if ($existing) {
                $individual['id'] = $existing->id;
                $DB->update_record('speval_flag_individual', (object)$individual);
            } else {
                $individual['id'] = $DB->insert_record('speval_flag_individual', (object)$individual);
            }

            $stored_results[] = $individual;
        }
        
        return $stored_results;
    }
    
    /**
     * Get group information for a peer
     * @param int $peerid
     * @param int $activityid
     * @return array
     */
    private static function get_peer_group_info($peerid, $activityid) {
        global $DB;
        
        // Get the course from the activity
        $speval = $DB->get_record('speval', ['id' => $activityid]);
        if (!$speval) {
            return ['groupid' => 0, 'groupingid' => 0];
        }
        
        $groups = groups_get_user_groups($speval->course, $peerid);
        
        $groupid = 0;
        $groupingid = 0;
        
        if (!empty($groups)) {
            foreach ($groups as $grouping => $group_list) {
                if (!empty($group_list)) {
                    $groupid = $group_list[0];
                    $groupingid = $grouping;
                    break;
                }
            }
        }
        
        return [
            'groupid' => $groupid,
            'groupingid' => $groupingid
        ];
    }
}