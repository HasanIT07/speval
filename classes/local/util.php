<?php
/*
    * Utility functions for the speval module.
    * All utility functions that don't fit elsewhere should go here.    
*/

namespace mod_speval\local;


defined('MOODLE_INTERNAL') || die();

class util {
    public const MAX_CRITERIA = 5;

    public static function get_students_in_same_groups($spevalid, \stdClass $user) {
        /* 
        * Get all students in the same groups as the given user within the given course.
        * If no groups exist in the course, or the user is not in any groups, return just the user.
        * 
        * @param int $spevalid The SPE activity id.
        * @param \stdClass $user The current user.
        * @return array User objects indexed by id.
         */
        global $DB;
        global $COURSE;

        $speval = $DB->get_record('speval', ['id' => $spevalid], '*', MUST_EXIST);                  // Get the SPEval activity record from the DB
        $studentids = [$user->id];                                                                  // Start with the user themselves

        // If linked to an assignment.
        if (!empty($speval->linkedassign)) {
            $assign = $DB->get_record('assign', ['id' => $speval->linkedassign]);                   // Get the linked assignment record

            $groupingid = $assign->teamsubmissiongroupingid ?? 0;                                   // Get the grouping id from assignment (0 if none)
            $groups = groups_get_user_groups($assign->course, $user->id);                           // Get all groups the user belongs to in this course
        } else {
            $groupingid = $speval->grouping ?? 0;                                                    // Get the grouping id from speval activity (0 if none)
            $groups = groups_get_user_groups($speval->course, $user->id);
        }

        // Defensive: ensure $groups[$groupingid] is an array before using array_values
        $groupids = [];
        if (isset($groups[$groupingid]) && is_array($groups[$groupingid])) {
            $groupids = array_values($groups[$groupingid]);
        }
        $groupid = $groupids[0] ?? null;
        $members = [];
        if (!empty($groupid)) {
            $members = groups_get_members($groupid, 'u.id', 'u.id');
            $studentids = array_unique(array_merge($studentids, array_keys($members)));
        }

        list($in_sql, $params) = $DB->get_in_or_equal($studentids);                                 // Get the SQL and params for all member ids

        return $DB->get_records_select(
            'user',
            "id $in_sql",
            $params,
            'lastname, firstname',
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename'
        );
    }


    public static function get_criteria_data($speval) {
        /* 
        * Used by criteria.php 
        * This function creates a criteriaObject that has the following properties:
        * $criteriaObject->length
        * $criteriaObject->custom_criteria1         // The text written by the teacher stored in $customfield in criteria_form.php
        * $criteriaObject->custom_criteria2
        * $criteriaObject->custom_criteria3
        * $criteriaObject->custom_criteria4
        * $criteriaObject->custom_criteria5
        * $criteriaObject->predefined_criteria1     // The element chosen by the teacher stored in $fieldname in criteria_form.php 
        * $criteriaObject->predefined_criteria2
        * $criteriaObject->predefined_criteria3
        * $criteriaObject->predefined_criteria4
        * $criteriaObject->predefined_criteria5
        * $criteriaObject->criteria_text1           // The final text shown to studends
        * $criteriaObject->criteria_text2
        * $criteriaObject->criteria_text3
        * $criteriaObject->criteria_text4
        * $criteriaObject->criteria_text5
        */
        global $DB;

        $records = $DB->get_records('speval_criteria', ['spevalid' => $speval->id], 'sortorder ASC');

        $criteriaObject = new \stdClass();

        $i = 0;
        foreach ($records as $criteria) {
            $i++;

            // If questiontext not empty in the DB, store this value in the property {"custom_criteria{$i}"}
            if (!empty($criteria->questiontext)){
                $criteriaObject->{"custom_criteria{$i}"} = $criteria->questiontext;
                $criteriaObject->{"criteria_text{$i}"} = $criteria->questiontext;
            

            // If questionbankid not NULL and not 0 in the DB, store this value in the property {"predefined_criteria{$i}"}
            } else if  (!empty($criteria->questionbankid)){
                $criteriaObject->{"predefined_criteria{$i}"} = $criteria->questionbankid ?? 0;
                $crit_text_record = $DB->get_record('speval_criteria_bank', ['id' => $criteria->questionbankid]);
                $criteriaObject->{"criteria_text{$i}"} = $crit_text_record->questiontext;
            }

            
        }

        while ($i < self::MAX_CRITERIA){
            $i++;
            $criteriaObject->{"predefined_criteria{$i}"} = NULL;
            $criteriaObject->{"custom_criteria{$i}"} = NULL;
            $criteriaObject->{"criteria_text{$i}"} = "";
        }

        $criteriaObject->length = $i;

        return $criteriaObject;
    }


    public static function save_criteria($spevalid, $data) {
        /* 
        * Used by criteria.php 
        */
        global $DB;
        for ($i = 1; $i <= self::MAX_CRITERIA; $i++) {
            $existing = $DB->get_record('speval_criteria', ['spevalid' => $spevalid, 'sortorder' => $i]);
            if (!$existing) {
                $newcriteria = new \stdClass();
                $newcriteria->spevalid = $spevalid;
                $newcriteria->sortorder = $i;
                $newcriteria->questiontext = $data->{"custom_criteria$i"};
                $newcriteria->questionbankid = $data->{"predefined_criteria$i"};
                $DB->insert_record('speval_criteria', $newcriteria);
            } else {
                if ($data->{"predefined_criteria$i"} > 0){                                      // The predefined is not "other"
                    $existing->questiontext   = NULL;
                    $existing->questionbankid = $data->{"predefined_criteria$i"};
                } else {                                                                        // The predefined is "other"
                    $existing->questiontext   = $data->{"custom_criteria$i"};
                    $existing->questionbankid = 0;
                }

                $DB->update_record('speval_criteria', $existing);
            }
        }
    }



}