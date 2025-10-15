<?php
/*
    * Form handler for processing self and peer evaluation submissions.
    * This class contains methods to handle form submissions, validate data,
    * and insert evaluation records into the database.
*/
namespace mod_speval\local;

defined('MOODLE_INTERNAL') || die();

class form_handler {
    public static function process_submission($courseid, $user, $speval) {
        /* 
         * Process the form submission for self and peer evaluations.
         * Inserts evaluation records into the database table speval_eval.
         * 
         * @param stdClass $cm The course module object
         * @param int $courseid The course ID
         * @param stdClass $user The user object of the evaluator
         * @return void
         */
        global $DB, $CFG;

        // Safely get arrays, if not present, initialize as empty array
        $c1 = optional_param_array('criteria_text1', [], PARAM_INT);
        $c2 = optional_param_array('criteria_text2', [], PARAM_INT);
        $c3 = optional_param_array('criteria_text3', [], PARAM_INT);
        $c4 = optional_param_array('criteria_text4', [], PARAM_INT);
        $c5 = optional_param_array('criteria_text5', [], PARAM_INT);
        $comments = optional_param_array('comment', [], PARAM_RAW);



        // --- 1. Get Start Time and Calculate Quick Submission Flag ---
        $starttime = required_param('starttime', PARAM_INT);
        $endtime = time();
        $duration_seconds = $endtime - $starttime;
        $MIN_DURATION_SECONDS = 180; // 3 minutes

        $quick_submission_flag = 0;
        if ($duration_seconds < $MIN_DURATION_SECONDS) {
            $quick_submission_flag = 1;
        }
        // ------------------------------------------------------------

        // ... rest of the method (e.g., if (empty($c1)) { return false; })

        // Only process if at least criteria 1 was submitted.
        if (empty($c1)) {
            return false; // nothing to process
        }

        $allcriteria = [
            'criteria1' => $c1, 
            'criteria2' => $c2, 
            'criteria3' => $c3, 
            'criteria4' => $c4, 
            'criteria5' => $c5
        ];

        $peerids = array_keys($c1);

        global $DB;
        
        // Insert new evaluations
        foreach ($peerids as $peerid) {
            $record = (object)[
                'activityid'  => $speval->id,
                'userid'      => $user->id,
                'peerid'      => $peerid,
                'comment1'    => $comments[$peerid] ?? '',
                'comment2'    => '', // Second comment field (can be used for additional comments)
                'timecreated' => time(),
            ];

            foreach($allcriteria as $fieldname => $values) {
                $record->{$fieldname} = $values[$peerid] ?? 0; // Default to 0 if not set
            }

            $DB->insert_record('speval_eval', $record);

            $group_info = self::get_peer_group_info($peerid, $speval->id); 
            
            $flag_record = (object)[
                'userid' => $user->id,
                'peerid' => $peerid,
                'activityid' => $speval->id,
                'grouping' => $group_info['groupingid'] ?? 0, // Use group info
                'groupid' => $group_info['groupid'] ?? 0,    // Use group info
                'commentdiscrepancy' => 0, 
                'markdiscrepancy' => 0,    
                'quicksubmissiondiscrepancy' => $quick_submission_flag, // <--- Set the calculated flag
                'misbehaviorcategory' => 1, // Default category
                'timecreated' => $endtime
            ];

            // Check if a flag record already exists 
            $existing_flag = $DB->get_record('speval_flag_individual', [
                'userid' => $user->id,
                'peerid' => $peerid,
                'activityid' => $speval->id
            ]);

            if ($existing_flag) {
                // If it exists, update only the quicksubmissiondiscrepancy field
                $update_data = (object)[
                    'id' => $existing_flag->id, 
                    'quicksubmissiondiscrepancy' => $quick_submission_flag
                ];
                $DB->update_record('speval_flag_individual', $update_data);
            } else {
                // Insert a new record
                $DB->insert_record('speval_flag_individual', $flag_record);
            }

        }

        // Trigger AI analysis after storing evaluations
        self::trigger_ai_analysis($speval->id);

        // Grade update logic could go in a separate grade_service class.
    }

    /**
     * Trigger AI analysis for new submissions
     * @param int $activityid
     */
    private static function trigger_ai_analysis($activityid) {
        try {
            // Try to queue AI analysis task
            $task = new \mod_speval\task\ai_analysis_task();
            $task->set_custom_data(['activityid' => $activityid]);
            \core\task\manager::queue_adhoc_task($task);
            debugging("AI analysis task queued successfully for activity {$activityid}", DEBUG_DEVELOPER);
        } catch (Exception $e) {
            debugging("Failed to queue AI analysis task: " . $e->getMessage(), DEBUG_DEVELOPER);
            
            // Fallback: Run AI analysis directly (synchronous)
            debugging("Running AI analysis synchronously as fallback", DEBUG_DEVELOPER);
            try {
                $results = \mod_speval\local\ai_service::analyze_evaluations($activityid);
                debugging("AI analysis completed synchronously. Processed " . count($results) . " results.", DEBUG_DEVELOPER);
            } catch (Exception $ai_error) {
                debugging("Synchronous AI analysis also failed: " . $ai_error->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
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
        $arraykey = 0;
        if (!empty($groups)) {
            foreach ($groups as $grouping => $group_list) {
                if (!empty($group_list)) {
                    $groupid = reset($group_list); 
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