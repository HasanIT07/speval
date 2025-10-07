<?php
namespace mod_speval\form;

defined('MOODLE_INTERNAL') || die();

class criteria_form extends \moodleform {
    public const MAX_CRITERIA = 5;

    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);

        for ($i = 1; $i <= self::MAX_CRITERIA; $i++) {
            $mform->addElement('textarea', "criteria$i", get_string("criteria{$i}", 'mod_speval'), ['rows' => 2, 'cols' => 80]);
            $mform->setType("criteria$i", PARAM_TEXT);
        }

        $this->add_action_buttons();
    }
}