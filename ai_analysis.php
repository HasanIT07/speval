<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$id = required_param('id', PARAM_INT);
list($course, $cm) = get_course_and_cm_from_cmid($id, 'speval');
$context = context_module::instance($cm->id);
require_capability('mod/speval:addinstance', $context);

$PAGE->set_url(new moodle_url('/mod/speval/ai_analysis.php', ['id' => $cm->id]));
$PAGE->set_cm($cm, $course);
$PAGE->set_context($context);
$PAGE->set_title('AI Analysis Results');
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();                                               // Disable the standard activity header 

echo $OUTPUT->header();
echo $OUTPUT->heading('AI Analysis Results');

// Get AI analysis results from individual flags
$flags = $DB->get_records('speval_flag_individual', ['activityid' => $cm->instance]);

if ($flags) {
    $table = new html_table();
    $table->head = ['Evaluator', 'Peer', 'Group', 'Misbehaviour Category', 'Mark Discrepancy', 'Comment Discrepancy'];
    
    foreach ($flags as $flag) {
        $group_name = $flag->groupid ? groups_get_group_name($flag->groupid) : '-';
        $evaluator = $DB->get_field('user', $DB->sql_fullname(), ['id' => $flag->userid]);
        $peer = $DB->get_field('user', $DB->sql_fullname(), ['id' => $flag->peerid]);

        $table->data[] = [
            $evaluator ?: $flag->userid,
            $peer ?: $flag->peerid,
            $group_name,
            (int)$flag->misbehaviorcategory,
            ((int)$flag->markdiscrepancy) ? 'Yes' : 'No',
            ((int)$flag->commentdiscrepancy) ? 'Yes' : 'No'
        ];
    }
    
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification('No AI analysis results available.', 'notifyinfo');
}

echo $OUTPUT->footer();