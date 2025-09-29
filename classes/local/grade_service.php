<?php
/*
    * Grade service for the speval module.
    * All grading related functions should be placed here.
*/
namespace mod_speval\local;

defined('MOODLE_INTERNAL') || die();



class grade_service {
    public static function calculate_spe_grade($cm, $courseid, $studentid) {

        // IMPORTANT:
        // calculate_spe_grade is broken, still requires to be fixed. The function should consider grouping.


        $peerids = array_keys($c1);                                                                 // Get all peer ids that were evaluated
        // Get max grade from activity settings
        $speval = $DB->get_record('speval', ['id' => $cm->instance]);                               // Get the current SPEVAL activity (Course Module)
        $maxgrade = isset($speval->grade) ? $speval->grade : 100;                                     // Default to 100 if not set                       

        foreach ($peerids as $peerid) {
            // Get all evaluations received by this peer
            $evals = $DB->get_records('speval_eval', ['unitid' => $courseid, 'peerid' => $peerid]);             // Get all evaluations for this peer in this course
            $total = 0;
            $count = 0;
            foreach ($evals as $eval) {
                $sum = $eval->criteria1 + $eval->criteria2 + $eval->criteria3 + $eval->criteria4 + $eval->criteria5;
                $total += $sum / 5.0; // average for this evaluation
                $count++;
            }
            $avg = $count > 0 ? $total / $count : 0;
            // Normalize to max grade (scale 1-5 to maxgrade)
            $grade = $maxgrade * ($avg / 5.0);

            // Update gradebook
            require_once($CFG->libdir.'/gradelib.php');
            $grades = [ $peerid => (object)['userid' => $peerid, 'rawgrade' => $grade] ];
            grade_update('mod/speval', $courseid, 'mod', 'speval', $cm->instance, 0, $grades);
        }     

    }
}
