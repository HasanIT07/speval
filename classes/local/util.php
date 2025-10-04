<?php
/*
    * Utility functions for the speval module.
    * All utility functions that don't fit elsewhere should go here.    
*/

namespace mod_speval\local;


defined('MOODLE_INTERNAL') || die();

class util {
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
            $groupid = array_values($groups[$groupingid])[0] ?? [];                                 // Groups save a key-value pairs of gourpingid-groupid
            $members = groups_get_members($groupid, 'u.id', 'u.id');                            // Get all members of that group
            $studentids = array_unique(array_merge($studentids, array_keys($members)));         // Merge with the user themselves and remove duplicates
        } else {
            // ...
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
}