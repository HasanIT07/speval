<?php
/*
	* Form definition for the speval module.
	* This file relates to the teacher view for adding the activity to a course.
*/

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_speval_mod_form extends moodleform_mod {
    public function definition() {
    global $USER; 
	global $COURSE;

	$mform = $this->_form;
	
	// Activity name field.
    $mform->addElement('text', 'name', get_string('pluginname', 'mod_speval'));
    $mform->setType('name', PARAM_TEXT);
	$mform->addRule('name', null, 'required', null, 'client');
	
	$userid = $USER->id;
	$courseid = $COURSE->id;
	// $usergroups = groups_get_user_groups($courseid, $userid);


	// Description field
	$this->standard_intro_elements();

	// Number of criteria field.
	// not working properly (if i select1 or 2, it still shows all 5 questions)
	// $mform->addElement('select', 'criteria_count', get_string('criteria_count', 'speval'), array(1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'));
	// $mform->setDefault('criteria_count', 3);
	// $mform->setType('criteria_count', PARAM_INT);

    // Grade field: allows teacher to set max grade for the activity
    // $this->standard_grading_elements();

	// $mform->addElement('textarea', 'intro', get_string('intro', 'core'), 'wrap="virtual" rows="5" cols="50"');
        
	$this->standard_coursemodule_elements();

	// Action buttons (Save/Cancel).
        $this->add_action_buttons();
    }
}