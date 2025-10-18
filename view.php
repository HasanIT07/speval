<?php
/*
 * Self and Peer Evaluation (SPEval) Moodle Plugin
 * View Page - main entry point for students to access the evaluation form.
 */

require(__DIR__.'/../../config.php');

use mod_speval\local\util;
use mod_speval\local\form_handler;

$id         = required_param('id', PARAM_INT);
$cm         = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
$course     = get_course($cm->course);
$context    = context_module::instance($cm->id);
$speval     = $DB->get_record('speval', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_capability('mod/speval:view', $context);

$PAGE->set_cm($cm);
$PAGE->set_url(new moodle_url('/mod/speval/view.php', ['id' => $cm->id]));
$PAGE->requires->css(new moodle_url('/mod/speval/styles.css'));
$PAGE->activityheader->disable();

$renderer = $PAGE->get_renderer('mod_speval');

$start = optional_param('start', 0, PARAM_INT);

$submission = $DB->record_exists('speval_eval', [
    'userid' => $USER->id,
    'activityid' => $speval->id
]);

$studentHasGrade = $DB->record_exists('speval_grades', [
    'userid' => $USER->id,
    'activityid' => $speval->id
]);

/**
 * Helper: read drafts for this user/activity into a prefill array:
 * [
 *   'criteria1' => [peerid => val], ... 'criteria5' => [...],
 *   'comment1'  => [peerid => '...']
 * ]
 */
function speval_load_prefill_from_drafts(\moodle_database $DB, int $activityid, int $userid): array {
    $records = $DB->get_records('speval_eval_draft', ['activityid' => $activityid, 'userid' => $userid]);

    $prefill = [
        'criteria1' => [],
        'criteria2' => [],
        'criteria3' => [],
        'criteria4' => [],
        'criteria5' => [],
        'comment1'  => [],
        'comment2'  => []
    ];

    foreach ($records as $rec) {
        $peerid = (int)$rec->peerid;

        // Each criteria may be NULL; only set if non-null to avoid forcing values.
        for ($i = 1; $i <= 5; $i++) {
            $field = "criteria{$i}";
            if (property_exists($rec, $field) && $rec->{$field} !== null) {
                $prefill[$field][$peerid] = (int)$rec->{$field};
            }
        }

        // comment1
        if (isset($rec->comment1) && $rec->comment1 !== null) {
            $prefill['comment1'][$peerid] = (string)$rec->comment1;
        }

        // comment2
        if (isset($rec->comment2) && $rec->comment2 !== null) {
            $prefill['comment2'][$peerid] = (string)$rec->comment2;
        }
    }

    return $prefill;
}

echo $OUTPUT->header();

if ($submission){
    if ($studentHasGrade) {
        echo $renderer->display_grade_for_student($USER, $speval, $cm);
    } else {
        echo "This activity has not been graded yet.";
    }

} else {
    // POST handler (either Save draft or final Submit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {

        $savedraft = optional_param('savedraft', 0, PARAM_INT);

        if ($savedraft) {
            // Gather arrays for draft save
            $c1 = optional_param_array('criteria_text1', [], PARAM_INT);
            $c2 = optional_param_array('criteria_text2', [], PARAM_INT);
            $c3 = optional_param_array('criteria_text3', [], PARAM_INT);
            $c4 = optional_param_array('criteria_text4', [], PARAM_INT);
            $c5 = optional_param_array('criteria_text5', [], PARAM_INT);
            $comments = optional_param_array('comment', [], PARAM_RAW);

            $ok = form_handler::save_draft($speval->id, $USER, $c1, $c2, $c3, $c4, $c5, $comments);

            echo $ok
                ? $OUTPUT->notification('Draft saved.', \core\output\notification::NOTIFY_SUCCESS)
                : $OUTPUT->notification('Could not save draft. Please try again.', \core\output\notification::NOTIFY_ERROR);

            // Re-render form with latest draft prefilled
            $studentsInGroup = util::get_students_in_same_groups($speval->id, $USER);
            if (empty($studentsInGroup)) {
                echo $renderer->no_peers_message();
            } else {
                $prefill = speval_load_prefill_from_drafts($DB, $speval->id, $USER->id);
                if (!empty($prefill['criteria1']) || !empty($prefill['comment1'])) {
                    echo $renderer->draft_loaded_notification();
                }
                echo $renderer->evaluation_form($speval, $studentsInGroup, $prefill);
            }

        } else {
            // Final submission path (stores evaluations + flags + queues AI)
            form_handler::process_submission($course->id, $USER, $speval);
            echo $renderer->submission_success_notification();
        }

    } else if (!$start) {
        // Landing page
        
        echo $renderer->student_landing_page($cm, $speval);

    } else {
        // Display the form (prefill from existing drafts, if any)
        $studentsInGroup = util::get_students_in_same_groups($speval->id, $USER);

        if (empty($studentsInGroup)) {
            echo $renderer->no_peers_message();
        } else {
            $prefill = speval_load_prefill_from_drafts($DB, $speval->id, $USER->id);
            if (!empty($prefill['criteria1']) || !empty($prefill['comment1'])) {
                echo $renderer->draft_loaded_notification();
            }
            echo $renderer->evaluation_form($speval, $studentsInGroup, $prefill, $cm);
        }
    }
}

echo $OUTPUT->footer();
