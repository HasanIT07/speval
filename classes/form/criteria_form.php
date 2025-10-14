<?php
namespace mod_speval\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class criteria_form extends \moodleform {

    public function definition() {
        global $DB;
        $mform = $this->_form;

        // Get existing criteria from customdata
        $criteriaData = $this->_customdata['criteriaData'] ?? null;
        $length = $criteriaData->length ?? 1;

        // Get options from the bank once
        $bankoptions = $this->get_criteria_bank_options();

        // Loop through criteria
        for ($i = 1; $i <= $length; $i++) {
            $fieldname = "criteria{$i}_select";
            $customfield = "criteria{$i}_custom";

            // Dropdown from bank
            $mform->addElement('select', $fieldname, get_string("criteria{$i}", 'mod_speval'), $bankoptions);
            
            // Custom field
            $mform->addElement('text', $customfield, "   ", ['size' => 60]);
            $mform->setType($customfield, PARAM_TEXT);
            $mform->hideIf($customfield, $fieldname, 'neq', 0);

            // Pre-fill values if available
            if (!empty($criteriaData->{$fieldname})) {
                $mform->setDefault($fieldname, $criteriaData->{$fieldname});
            }
            if (!empty($criteriaData->{$customfield})) {
                $mform->setDefault($customfield, $criteriaData->{$customfield});
            }
        }
        
        // Submit buttons
        $this->add_action_buttons(true, get_string('savechanges'));
    }
    
    /**
     * Get options from criteria bank
     */
    protected function get_criteria_bank_options() {
        global $DB;
        
        $records = $DB->get_records('speval_criteria_bank', [], 'id ASC');
        $options = [0 => get_string('other', 'mod_speval')]; // default first option

        foreach ($records as $r) {
            $options[$r->id] = $r->questiontext;
        }
        
        return $options;
    }
    
    
    
    
}

// defined('MOODLE_INTERNAL') || die();

// class criteria_form extends \moodleform {
//     public const MAX_CRITERIA = 5;

//     public function definition() {
//         $mform = $this->_form;
//         $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
//         $mform->setType('id', PARAM_INT);

//         for ($i = 1; $i <= self::MAX_CRITERIA; $i++) {
//             $mform->addElement('textarea', "criteria$i", get_string("criteria{$i}", 'mod_speval'), ['rows' => 2, 'cols' => 80]);
//             $mform->setType("criteria$i", PARAM_TEXT);
//         }

//         $this->add_action_buttons();
//     }
// }