<?php
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
	
	// DEBUG
	$userid = $USER->id;
	$courseid = $COURSE->id;
	// $usergroups = groups_get_user_groups($courseid, $userid);
	 debugging(print_r($userid, true), DEBUG_DEVELOPER);
	debugging(print_r($courseid, true), DEBUG_DEVELOPER);



	// Description field
	$this->standard_intro_elements();

	// Number of criteria field.
	$mform->addElement('select', 'criteria_count', get_string('criteria_count', 'speval'), array(1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'));
	$mform->setDefault('criteria_count', 3);
	$mform->setType('criteria_count', PARAM_INT);

	// Intro ?
        // $mform->addElement('textarea', 'intro', get_string('intro', 'core'), 'wrap="virtual" rows="5" cols="50"');
        

	// Loads the fields: 
		// Common module settinsg  
		// Restrict access
		// Completion conditions 
		// Tags 
		//Competencies
	$this->standard_coursemodule_elements();

	// Action buttons (Save/Cancel).
        $this->add_action_buttons();
    }
}