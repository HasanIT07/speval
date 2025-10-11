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

echo $OUTPUT->header();
echo $OUTPUT->heading('AI Analysis Results');

// Get AI analysis results
$flags = $DB->get_records('speval_flag', ['activityid' => $cm->instance]);

if ($flags) {
    $table = new html_table();
    $table->head = ['Group', 'Misbehaviour', 'Mark Discrepancy', 'Comment Discrepancy', 'Notes'];
    
    foreach ($flags as $flag) {
        $group_name = groups_get_group_name($flag->groupid);
        
        $table->data[] = [
            $group_name,
            $flag->misbehaviourflag ? 'Yes' : 'No',
            $flag->markdiscrepancyflag ? 'Yes' : 'No',
            $flag->commentdiscrepancyflag ? 'Yes' : 'No',
            $flag->notes
        ];
    }
    
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification('No AI analysis results available. Run grade calculation to trigger AI analysis.', 'notifyinfo');
}

echo $OUTPUT->footer();