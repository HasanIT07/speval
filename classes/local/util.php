<?php
/*
    * Utility functions for the speval module.
    * All utility functions that don't fit elsewhere should go here.    
*/

namespace mod_speval\local;


defined('MOODLE_INTERNAL') || die();

class util {
    public static function get_students_in_same_groups($courseid, \stdClass $user) {
        /* 
        * Get all students in the same groups as the given user within the given course.
        * If no groups exist in the course, or the user is not in any groups, return just the user.
        * 
         * @param stdClass $course The course object
         * @param stdClass $user The user object
         * @param moodle_database $DB The global database object
         * @return array An array of user objects (including the given user)
         */
        global $DB;

        $groupids = $DB->get_fieldset_select('groups', 'id', 'courseid = ?', [$courseid]);    // All group ids in this course
        if (empty($groupids)) {                                                                 // No groups in this course
            return [$user->id => $user];                                                        // Return just the user (Is this if only a self evaluation is desired?)
        }

        $usergroupids = $DB->get_fieldset_select(                                               // All group ids the user is a member of
            'groups_members',                                                                   
            'groupid',
            'userid = ? AND groupid IN (' . implode(',', $groupids) . ')',
            [$user->id]
        );

        $allmemberids = [$user->id];                                                            // Start with the user themselves
        if (!empty($usergroupids)) {                                                            // If the user is in any groups
            list($ingroupsql, $params) = $DB->get_in_or_equal($usergroupids);                   // Get the SQL and params for the groups the user is in                   
            $allmemberids = $DB->get_fieldset_select('groups_members', 'userid', 'groupid ' . $ingroupsql, $params);  // Get all user ids in those groups
            if (!in_array($user->id, $allmemberids)) {                                          // Just in case, ensure the user themselves is included
                $allmemberids[] = $user->id;                                                    // Add the user themselves
            }
        }

        $allmemberids = array_unique($allmemberids);                                         // Remove any duplicates

        if (empty($allmemberids)) {                                                         // If no members found (shouldn't happen as user is always included)
            return [$user->id => $user];                                                    // Return just the user
        }

        list($in_sql, $params) = $DB->get_in_or_equal($allmemberids);                       // Get the SQL and params for all member ids 
        return $DB->get_records_select(                                                     // Get all user records for those ids
            'user',
            'id ' . $in_sql,
            $params,
            'lastname, firstname',
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename'
        );
    }
}