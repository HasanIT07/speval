<?php
namespace mod_speval\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');

class criteria_bank_form extends \moodleform {
    protected $courseid;
    protected $cmid;

    public function __construct($action = null, $customdata = null) {
        if (!empty($customdata['courseid'])) {
            $this->courseid = $customdata['courseid'];
        }
        
        if (!empty($customdata['cmid'])) {
            $this->cmid = $customdata['cmid'];
        }
        
        parent::__construct($action, $customdata);
    }

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'questiontext', get_string('questiontext', 'mod_speval'), ['size' => 120]);
        $mform->setType('questiontext', PARAM_TEXT);
        $mform->addRule('questiontext', null, 'required', null, 'client');

        $mform->addElement('selectyesno', 'isopenquestion', get_string('openquestion', 'mod_speval'));
        $mform->setDefault('isopenquestion', 0);

        $mform->addElement('hidden', 'courseid', $this->courseid);
        $mform->setType('courseid', PARAM_INT);

        // Add cmid hidden field here
        if (!empty($this->cmid)) {
            $mform->addElement('hidden', 'id', $this->cmid);
            $mform->setType('id', PARAM_INT);
        }

        $this->add_action_buttons(true, get_string('savequestion', 'mod_speval'));
    }
}