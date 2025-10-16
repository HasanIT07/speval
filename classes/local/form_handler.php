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
        /* * Process the form submission for self and peer evaluations.
        * Inserts evaluation records into the database table speval_eval.
        * * @param int $courseid The course ID
        * @param stdClass $user The user object of the evaluator
        * @param stdClass $speval The speval activity object
        * @return void
        */
        global $DB, $CFG;

        // Safely get arrays
        $c1 = optional_param_array('criteria_text1', [], PARAM_INT);
        $c2 = optional_param_array('criteria_text2', [], PARAM_INT);
        $c3 = optional_param_array('criteria_text3', [], PARAM_INT);
        $c4 = optional_param_array('criteria_text4', [], PARAM_INT);
        $c5 = optional_param_array('criteria_text5', [], PARAM_INT);
        $comments = optional_param_array('comment', [], PARAM_RAW);
        $comment2 = optional_param_array('comment2', [], PARAM_RAW);

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
        
        // --- CLEANUP DRAFT BEFORE SUBMISSION ---
        // Since the user is submitting, delete any existing draft for this evaluation.
        foreach ($peerids as $peerid) {
            $DB->delete_records('speval_draft', [
                'activityid' => $speval->id,
                'userid' => $user->id,
                'peerid' => $peerid
            ]);
        }
        // ---------------------------------------

        // Insert new evaluations
        foreach ($peerids as $peerid) {
            // 1. Save Evaluation Record (Final Submission)
            $record = (object)[
                'activityid'  => $speval->id,
                'userid'      => $user->id,
                'peerid'      => $peerid,
                'comment1'    => $comments[$peerid] ?? '',
                'comment2'    => $comment2[$peerid] ?? '',
                'timecreated' => $endtime, // FIX: Use $endtime for consistency
            ];

            foreach($allcriteria as $fieldname => $values) {
                $record->{$fieldname} = $values[$peerid] ?? 0; // Default to 0 if not set
            }

            $DB->insert_record('speval_eval', $record);

            // 2. Save/Update Flag Record
            $group_info = self::get_peer_group_info($peerid, $speval->id); 
            
            $flag_record = (object)[
                'userid' => $user->id,
                'peerid' => $peerid,
                'activityid' => $speval->id,
                'grouping' => $group_info['groupingid'] ?? 0, 
                'groupid' => $group_info['groupid'] ?? 0,    
                'commentdiscrepancy' => 0, 
                'markdiscrepancy' => 0,    
                'quicksubmissiondiscrepancy' => $quick_submission_flag, 
                'misbehaviorcategory' => 1, 
                'timecreated' => $endtime
            ];

            // Check if a flag record already exists (Upsert logic)
            $existing_flag = $DB->get_record('speval_flag_individual', [
                'userid' => $user->id,
                'peerid' => $peerid,
                'activityid' => $speval->id
            ]);

            if ($existing_flag) {
                // If it exists, update only the quicksubmissiondiscrepancy field
                $update_data = (object)[
                    'id' => $existing_flag->id, 
                    'quicksubmissiondiscrepancy' => $quick_submission_flag,
                    'timecreated' => $endtime // Update timecreated to reflect last change
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

    // =========================================================================
    // === NEW DRAFT SAVING FUNCTIONALITY ===
    // =========================================================================

    /**
     * Saves the current form data as a draft.
     * This function is called via AJAX periodically.
     * * @param int $activityid The SPEval activity instance ID.
     * @param stdClass $user The user object of the evaluator.
     * @param array $c1 Criteria 1 scores (keyed by peerid).
     * @param array $c2 Criteria 2 scores (keyed by peerid).
     * @param array $c3 Criteria 3 scores (keyed by peerid).
     * @param array $c4 Criteria 4 scores (keyed by peerid).
     * @param array $c5 Criteria 5 scores (keyed by peerid).
     * @param array $comments Comments (keyed by peerid).
     * @return bool True on success.
     */
    public static function save_draft($activityid, $user, $c1, $c2, $c3, $c4, $c5, $comments) {
        global $DB;
        $time = time();
        $success = true;

        // Collect all criteria arrays into one structure
        $allcriteria = [
            'criteria1' => $c1, 'criteria2' => $c2, 'criteria3' => $c3,
            'criteria4' => $c4, 'criteria5' => $c5
        ];

        // The peer IDs are derived from the keys of the criteria arrays
        $peerids = array_keys($c1);

        foreach ($peerids as $peerid) {
            // Check if a draft record already exists for this (user, peer, activity)
            $existing_draft = $DB->get_record('speval_draft', [
                'activityid' => $activityid,
                'userid'     => $user->id,
                'peerid'     => $peerid
            ]);

            $draft_data = (object)[
                'activityid'  => $activityid,
                'userid'      => $user->id,
                'peerid'      => $peerid,
                'comment1'    => $comments[$peerid] ?? '',
                // Add comment2 if it's used for draft saving
                'comment2'    => null, 
                'timemodified' => $time,
            ];

            // Add criteria scores
            foreach ($allcriteria as $field_name => $values) {
                 // Use null for missing scores to avoid saving '0' prematurely
                $draft_data->{$field_name} = $values[$peerid] ?? null; 
            }

            if ($existing_draft) {
                // Update existing draft
                $draft_data->id = $existing_draft->id;
                $DB->update_record('speval_draft', $draft_data);
            } else {
                // Insert new draft
                $draft_data->timecreated = $time;
                $DB->insert_record('speval_draft', $draft_data);
            }
        }
        return $success;
    }
    
    // =========================================================================
    // === HELPER FUNCTIONS ===
    // =========================================================================

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
            // Silently queued; avoid emitting browser output
        } catch (\Exception $e) { // FIX: Use \Exception
            // Log to server error log instead of browser
            error_log('mod_speval: Failed to queue AI analysis task: ' . $e->getMessage());
            
            // Fallback: Run AI analysis directly (synchronous)
            // Silent fallback run
            try {
                $results = \mod_speval\local\ai_service::analyze_evaluations($activityid);
            } catch (\Exception $ai_error) { // FIX: Use \Exception
                error_log('mod_speval: Synchronous AI analysis failed: ' . $ai_error->getMessage());
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
        if (!empty($groups)) {
            foreach ($groups as $grouping => $group_list) {
                if (!empty($group_list)) {
                    // FIX: Use reset() to safely get the first element of the associative array
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