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

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 1. Load Moodle and necessary objects
require(__DIR__.'/../../config.php');                                               // Make the $DB $USER and $COURSE objects available in the global scope
use mod_speval\local\util;
use mod_speval\local\form_handler;

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 2. Get course/module/context/plugin instance
$id         = required_param('id', PARAM_INT);                                      // Get the mdl_course_module id
$cm         = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);        // Get the course_module record (activity instance wrapper) by id
$course     = get_course($cm->course);                                              // Load the course record from the DB  
$context    = context_module::instance($cm->id);                                    // Get the context from the course_module
$speval     = $DB->get_record('speval', ['id' => $cm->instance], '*', MUST_EXIST);  // Get the speval instance record from the DB

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 3. Require login and capabilities
require_login($course, false, $cm);                                                 // Ensure the user is logged in and has access to this course and activity
require_capability('mod/speval:view', $context);                                    // Ensure the user has permission to manage this activity

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 4. Set up page
$PAGE->set_cm($cm);                                                                 // Sets cm, course and page context
$PAGE->set_url(new moodle_url('/mod/speval/view.php', ['id' => $cm->id]));          // Allows this view to have a public link
$PAGE->requires->css(new moodle_url('/mod/speval/styles.css', ['v' => time()]));    // Links CSS Styles
$PAGE->activityheader->disable();

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 5. Get renderer class
$renderer = $PAGE->get_renderer('mod_speval');                                      // From: speval\classes\ouput\renderer.php

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 6. Determining bool values:
$start = optional_param('start', 0, PARAM_INT);                                     // Determine whether the student has started the evaluation.

$submission = $DB->record_exists('speval_eval', [                                   // Determine whether the student has submitted.
    'userid' => $USER->id,
    'activityid' => $speval->id
]);

$studentHasGrade = $DB->record_exists('speval_grades', [                            // Determine whether the studen has been given a grade.
    'userid' => $USER->id,
    'activityid' => $speval->id
]);


// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 6. Page Content
echo $OUTPUT->header();

if ($submission){
    if ($studentHasGrade) {
        echo $renderer->display_grade_for_student($USER, $speval);
    
    } else {
        echo "This activity has not been graded yet.";
    }

} else {
    
    // Handle form submission (all-in-one)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {               // Check if there is a POST REQUEST ("Submit all evaluations" is clicked)
        form_handler::process_submission($COURSE->id, $USER, $speval);              // Inserts evaluation records into the database table mdl_speval_eval.
        echo $renderer->submission_success_notification();
    } else if (!$start) {
        echo $renderer->student_landing_page($cm, $speval);                         // Show the landing page with instructions and start button
    } else {        
        $studentsInGroup = util::get_students_in_same_groups($speval->id, $USER);   
        echo $renderer->evaluation_form($speval, $studentsInGroup);                 // Show the evaluation form with "Submit all evaluations" button
    }   
}

echo $OUTPUT->footer();

?>