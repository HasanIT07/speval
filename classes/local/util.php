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
        */
        global $DB;

        $records = $DB->get_records('speval_criteria', ['spevalid' => $speval->id], 'sortorder ASC');

        $criteriaObject = new \stdClass();

        $i = 0;
        foreach ($records as $criteria) {
            $i++;
            $criteriaObject->{"criteria{$i}"} = $criteria->questiontext ?? ''; // or ->description if that's the correct field
        }

        $criteriaObject->length = $i;
        // // Ensure all 5 criteria fields exist (even if less than 5 records found)
        // for (; $i <= 5; $i++) {
        //     $criteriaObject->{"criteria{$i}"} = '...';
        // }

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
                $newcriteria->questiontext = $data->{"criteria$i"};
                $DB->insert_record('speval_criteria', $newcriteria);
            } else {
                $existing->questiontext = $data->{"criteria$i"};
                $DB->update_record('speval_criteria', $existing);
            }
        }
    }



}