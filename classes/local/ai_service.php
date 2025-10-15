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
 * Call the AI module via HTTP API
 * @param array $data
 * @return array|null
 */
private static function call_ai_module($data) {
    // Get API URL from config or use default
    $api_url = get_config('mod_speval', 'ai_api_url');
    if (empty($api_url)) {
        $api_url = 'http://localhost:8000/analyze';
    }
    
    try {
        // Prepare JSON data
        $json_data = json_encode($data);
        
        // Set up HTTP context
        $options = [
            'http' => [
                'header' => [
                    "Content-Type: application/json",
                    "Content-Length: " . strlen($json_data)
                ],
                'method' => 'POST',
                'content' => $json_data,
                'timeout' => 30  // 30 second timeout
            ]
        ];
        
        $context = stream_context_create($options);
        
        // Make the API call
        $result = file_get_contents($api_url, false, $context);
        
        if ($result === false) {
            debugging('Failed to call AI API at: ' . $api_url, DEBUG_DEVELOPER);
            return null;
        }
        
        // Parse the response
        $response = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('Invalid JSON response from AI API: ' . $result, DEBUG_DEVELOPER);
            return null;
        }
        
        return $response;
        
    } catch (Exception $e) {
        debugging('Error calling AI API: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return null;
    }
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