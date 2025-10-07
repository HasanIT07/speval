<?php
/* 
    Self and Peer Evaluation (SPEval) Moodle Plugin
    View Page - Main entry point for students to access the evaluation form.

    This file handles displaying the evaluation form to students, processing form submissions,
    and updating grades based on peer evaluations.

    Key Features:
    - Displays a landing page with activity details and a start button.
    - Shows the evaluation form for self and peer assessments.
    - Processes form submissions and stores evaluations in the database.
    - Calculates and updates grades based on received evaluations.

    Note: This code assumes that the necessary database tables (speval, speval_eval) and
    Moodle capabilities are already set up. Additional error handling and validation
    may be required for a production environment.
*/

require(__DIR__.'/../../config.php'); // Make the DB USER and COURSE objects available in the global scope
require_login();

use mod_speval\local\util;
use mod_speval\local\form_handler;

$id         = required_param('id', PARAM_INT);                                      // Get the mdl_course_module id
$start      = optional_param('start', 0, PARAM_INT);                                // Show form if start=1
$cm         = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);        // Load the course module (activity instance wrapper) by id
$context    = context_module::instance($cm->id);                                    // Get the context from the course module
require_capability('mod/speval:view', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/speval/view.php', ['id' => $cm->id]));
$PAGE->requires->css(new moodle_url('/mod/speval/styles.css', ['v' => time()]));

$renderer = $PAGE->get_renderer('mod_speval');                                      // Get he renderer class from speval\classes\ouput\renderer.php
$speval     = $DB->get_record('speval', ['id' => $cm->instance]);                   // Load the actual SPEval activity settings record from the DB


echo $OUTPUT->header();

if (!$start) {
    echo $renderer->student_landing_page($cm, $speval);                         // All the HTML for the start page is in the renderer class
    echo $OUTPUT->footer();
    exit;
}

// Handle form submission (all-in-one)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {               // Check if there is a POST REQUEST ("Submit all evaluations" is clicked)
    form_handler::process_submission($COURSE->id, $USER);                       // Inserts evaluation records into the database table mdl_speval_eval.
    echo $renderer->submission_success_notification();
    // grade_service::update_grades($cm, $COURSE->id, $USER);                   // Grade calculation and update will be implemented inside this method
}

$studentsInGroup = util::get_students_in_same_groups($speval->id, $USER);
echo $renderer->evaluation_form($speval, $studentsInGroup);

echo $OUTPUT->footer();
?>
