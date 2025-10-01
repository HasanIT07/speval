<?php
require_once('../../config.php');
require_sesskey(); // still keep sesskey for safety
global $DB;

$cmid = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('speval', $cmid, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

//  Removed require_capability for now 
// require_capability('mod/speval:grade', $context);

// Call the grading service
\mod_speval\local\grade_service::calculate_spe_grade($cm, $cm->course);

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Grades calculated successfully']);
exit;
