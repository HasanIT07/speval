<?php
require_once('../../config.php');
require_sesskey(); // keep sesskey for safety
global $DB;

$cmid = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('speval', $cmid, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

// Call the grading service
\mod_speval\local\grade_service::calculate_spe_grade($cm, $cm->course);

// Redirect back to results.php with a success notification
$resultsurl = new moodle_url('/mod/speval/results.php', ['id' => $cmid]);
redirect($resultsurl, get_string('gradesuccess', 'mod_speval'), 2); // 2 seconds delay
