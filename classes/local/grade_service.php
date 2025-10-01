<?php
namespace mod_speval\local;

defined('MOODLE_INTERNAL') || die();

class grade_service {
    public static function calculate_spe_grade($cm, $courseid) {
    global $DB, $CFG;

    $speval = $DB->get_record('speval', ['id' => $cm->instance]);
    $maxgrade = isset($speval->grade) ? $speval->grade : 5;

    require_once($CFG->libdir.'/gradelib.php');


    $submissions = $DB->get_records('speval_eval', ['activityid' => $cm->instance]);
    if (!$submissions) {
        return; // nothing to grade
    }

    $processed_students = [];

    foreach ($submissions as $submission) {
        $studentid = $submission->userid;

        if (in_array($studentid, $processed_students)) {
            continue; // already graded
        }

        $studentgrades = [0,0,0,0,0];
        $count = 0;

        foreach ($submissions as $s) {
            if ($s->peerid == $studentid) {
                $studentgrades[0] += $s->criteria1;
                $studentgrades[1] += $s->criteria2;
                $studentgrades[2] += $s->criteria3;
                $studentgrades[3] += $s->criteria4;
                $studentgrades[4] += $s->criteria5;
                $count++;
            }
        }

        $grade = 0;
        if ($count > 0) {
            $grade = array_sum($studentgrades) / ($count * 5);
        }

        $processed_students[] = $studentid;
        


                    // First, delete old grade for this student (avoid duplicates)
            $DB->delete_records('speval_grades', [
                'activityid' => $submission->activityid,
                'userid' => $studentid
            ]);
    $DB->insert_record('speval_grades', [
        'unitid'     => $submission->unitid,
        'userid'     => $studentid,      // or $submission->userid depending on your logic
        'activityid' => $submission->activityid,
        'c1'         => $studentgrades[0],
        'c2'         => $studentgrades[1],
        'c3'         => $studentgrades[2],
        'c4'         => $studentgrades[3],
        'c5'         => $studentgrades[4],
        'finalgrade' => $grade,
    ]);

}

//need to add functionality to give 0 who did not submit peer evals


}
}
