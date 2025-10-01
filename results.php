<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$id = required_param('id', PARAM_INT); // Course module ID.

// Always fetch cm, course, and module instance in one go.
list($course, $cm) = get_course_and_cm_from_cmid($id, 'speval');
$moduleinstance = $DB->get_record('speval', ['id' => $cm->instance], '*', MUST_EXIST);

$context = context_module::instance($cm->id);
require_capability('mod/speval:view', $context);

// Correct PAGE setup
$PAGE->set_url(new moodle_url('/mod/speval/results.php', ['id' => $cm->id]));
$PAGE->set_cm($cm, $course);
$PAGE->set_context($context);
$PAGE->set_title(get_string('results', 'speval'));
$PAGE->set_heading($course->fullname);

// Output starts
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('results', 'speval'));


// Check if current user is a student (by capability)
if (!has_capability('mod/speval:grade', $context)) {
    // Student view
    echo "<h3>Your Grade</h3>";

    $grade = $DB->get_record('speval_grades', [
        'userid' => $USER->id,
        'activityid' => $cm->instance
    ]);

    if ($grade) {
        echo "Final Grade: " . $grade->finalgrade;
    } else {
        echo "No grade available yet.";
    }

} else {
    // Teacher/manager view

	//this button is to trigger grade calculation for all students
echo $OUTPUT->single_button(
    new moodle_url('/mod/speval/grade_service.php', ['id' => $cm->id]),
    get_string('gradeall', 'mod_speval'),
    'post'
);


// Show results (can fetch from speval_grades here).
$grades = $DB->get_records('speval_grades', ['activityid' => $cm->instance]);
$user = $DB->get_records('user');
if ($grades) {
    $table = new html_table();
    $table->head = ['Name', 'Final Grade'];

    foreach ($grades as $g) {

		$name = $user[$g->userid]->firstname . ' ' . $user[$g->userid]->lastname;
        $table->data[] = [$name,  $g->finalgrade];
    }
    echo html_writer::table($table);
} else {
	echo $OUTPUT->notification(get_string('nogrades', 'mod_speval'), 'notifyproblem');
}
}

// Output end
echo $OUTPUT->footer();
