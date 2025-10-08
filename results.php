<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$id = required_param('id', PARAM_INT);                                                  // Get the mdl_course_module id
list($course, $cm) = get_course_and_cm_from_cmid($id, 'speval');                        // Get the course and cm info from the id
$speval = $DB->get_record('speval', ['id' => $cm->instance], '*', MUST_EXIST);          // Get the speval instance record from the DB

$context = context_module::instance($cm->id);                                           // Get the context from the course module
require_capability('mod/speval:view', $context);                                        // Ensure the user has permission to view this activity

// Correct PAGE setup
$PAGE->set_url(new moodle_url('/mod/speval/results.php', ['id' => $cm->id]));           // Set the URL for this page
$PAGE->set_cm($cm, $course);
$PAGE->set_context($context);
$PAGE->set_title(get_string('results', 'speval'));
$PAGE->set_heading($course->fullname);

// Output starts
$PAGE->activityheader->disable();
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

// New buttons to export CSV
    echo $OUTPUT->single_button(
        new moodle_url('/mod/speval/export_csv.php', ['id' => $cm->id, 'table' => 'speval_eval']),
        'Export Eval CSV',
        'get'
    );

    echo $OUTPUT->single_button(
        new moodle_url('/mod/speval/export_csv.php', ['id' => $cm->id, 'table' => 'speval_grades']),
        'Export Grades CSV',
        'get'
    );


// Show results (can fetch from speval_grades here).
$grades = $DB->get_records('speval_grades', ['activityid' => $cm->instance]);
$user = $DB->get_records('user');
if ($grades) {
$table = new html_table();
$table->head = ['Name', 'Final Grade', 'Actions'];

foreach ($grades as $g) {
    $name = $user[$g->userid]->firstname . ' ' . $user[$g->userid]->lastname;

    // Create edit link/button for each student
    $editurl = new moodle_url('/mod/speval/edit_grade.php', [
        'id' => $cm->id,
        'gradeid' => $g->id
    ]);
    $editbutton = html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-secondary']);

    $table->data[] = [$name, $g->finalgrade, $editbutton];
}

echo html_writer::table($table);
} else {
	echo $OUTPUT->notification(get_string('nogrades', 'mod_speval'), 'notifyproblem');
}
}

// Output end
echo $OUTPUT->footer();
