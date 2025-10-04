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
	global $DB;

	$mform = $this->_form;
	$mform->setDefault('visible', 1);
	
	// Activity name field.
    $mform->addElement('text', 'name', get_string('spename', 'mod_speval'));
    $mform->setType('name', PARAM_TEXT);
	$mform->addRule('name', null, 'required', null, 'client');
	
	// $usergroups = groups_get_user_groups($courseid, $userid);

	
	// Description field
	$this->standard_intro_elements();




	// SPE link option: standalone or linked.
	$linkoptions = [
		0 => get_string('standalone', 'mod_speval'),
		1 => get_string('linktoassignment', 'mod_speval')
	];
	$mform->addElement('select', 'linkoption', get_string('linkoption', 'mod_speval'), $linkoptions);
	$mform->setType('linkoption', PARAM_INT);
	
	// If linked, select assignment from course.
	$assignments = $DB->get_records('assign', ['course' => $COURSE->id]);
	$assignmentoptions = [0 => "select assignment"];
	foreach ($assignments as $assign) {
		$assignmentoptions[$assign->id] = format_string($assign->name);
	}

	// Linked assignment dropdown (hidden if standalone).
	$mform->addElement('select', 'linkedassign', get_string('linkedassign', 'mod_speval'), $assignmentoptions);
	$mform->setType('linkedassign', PARAM_INT);
	$mform->hideIf('linkedassign', 'linkoption', 'eq', 0);



	// If not linked, select grouping from course.
	$groupings = groups_get_all_groupings($COURSE->id);
	$groupingoptions = [1 => "select grouping"];
	foreach($groupings as $grouping){
		$groupingoptions[$grouping->id] = format_string($grouping->name);
	}

	// Linked directly to a grouping.
	$mform->addElement('select', 'grouping', 'linkedgrouping', $groupingoptions);
	$mform->setType('grouping', PARAM_INT);
	$mform->hideIf('grouping', 'linkoption', 'neq', 0);




	if (!empty($this->current)) {
		if (!empty($this->current->linkedassign)) {
			$mform->setDefault('linkoption', 1);
			$mform->setDefault('linkedassign', $this->current->linkedassign);
		} else {
			$mform->setDefault('linkoption', 0);
			$mform->setDefault('linkedassign', 0);
		}
	}
        
	$this->standard_coursemodule_elements();

	// Action buttons (Save/Cancel).
        $this->add_action_buttons();
    }
}