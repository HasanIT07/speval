<?php
/*
    * Renderer for the speval module.
    * All HTML generation functions should be placed here.
*/


namespace mod_speval\output;

defined('MOODLE_INTERNAL') || die();

use mod_speval\local\util;
use \plugin_renderer_base;
use \moodle_url;
use \html_writer;

class renderer extends plugin_renderer_base {
    public function student_landing_page($cm, $speval){
        /*
        * Render the landing page instructions and start button
        *
        * @param $cm Course module
        * @return string HTML
        */

        // Initial info/landing page before evaluation starts
        $html = $this->output->heading(format_string($cm->name));                   

        if (!empty($speval->intro)) {
            $html .= $this->output->box(format_module_intro('speval', $speval, $cm->id), 'generalbox');                 
        }

        // Show grading info if available
        if (isset($speval->grade)) {
            $html .= html_writer::tag('p', '<b>Maximum grade:</b> ' . (float)$speval->grade);
        }

        // Initial instructions
        $html .= html_writer::start_div('speval-container');
            $html .= html_writer::tag('h2', 'Self & Peer Evaluation');
            $html .= html_writer::tag('p',
                '<b>Please note:</b> Everything you put into this form will be kept strictly confidential by the unit coordinator.<br>' .
                '<b>Contribution Ratings:</b><br>' .
                '- Very Poor: Very poor, or even obstructive, contribution to the project process<br>' .
                '- Poor: Poor contribution to the project process<br>' .
                '- Average: Acceptable contribution to the project process<br>' .
                '- Good: Good contribution to the project process<br>' .
                '- Excellent: Excellent contribution to the project process<br><br>' .
                '<b>Using the assessment scales above, fill out the following.</b>'
            );

        $html .= html_writer::end_div();

        // Start button
        $starturl = new moodle_url('/mod/speval/view.php', ['id' => $cm->id, 'start' => 1]);
        

        $html .= html_writer::start_tag('form', ['method' => 'get', 'action' => $starturl]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'start', 'value' => 1]);
        $html .= html_writer::tag('button', 'Start Evaluation', ['type' => 'submit', 'class' => 'submit-button']);
        $html .= html_writer::end_tag('form');
        return $html;
    }


    public function evaluation_form($speval, $studentsInGroup) {
        $html  = html_writer::start_div('speval-container');
        $html .= html_writer::tag('h2', 'Self & Peer Evaluation');
        $html .= html_writer::tag('p', '<b>Please note:</b> Everything you put into this form will be kept strictly confidential.<br>');
        $html .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => ''
        ]);

        $html .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ]);

        foreach ($studentsInGroup as $student) {
            $html .= $this->peer_fields($speval, $student);
        }

        $html .= html_writer::div(
            html_writer::tag('button', 'Submit All Evaluations', ['type' => 'submit']),
            'form-actions'
        );

        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_div();

        return $html;
    }


    public function peer_fields($speval, $student) {
        /* 
        * Render the criteria question for students view
        * Requires $this->criteria_row(...)
        * 
        */

        $studentid = $student->id;
        $fullname  = fullname($student);

        $criteria_data = util::get_criteria_data($speval);
        
        $html = html_writer::empty_tag('hr');
        
        $html .= html_writer::start_tag('fieldset', ['class' => 'speval-peer']);
        $html .= html_writer::tag('legend', s(fullname($student)), ['class' => 'peer-name']);

        // for each criteria question related to this $speval instance: add its text and radio options to the form
        for ($i=1; $i<=$criteria_data->length; $i++){
            $criteriatext = $criteria_data->{"criteria$i"};
            $html .= $this->criteria_row("criteria$i", $criteriatext, $studentid);
        }

        // Comment field
        $html .= html_writer::start_div('form-row');
        $html .= html_writer::label('Comment:', "comment_{$studentid}");
        $html .= html_writer::tag('textarea', '', [
            'name' => "comment[{$studentid}]",
            'id'   => "comment_{$studentid}",
            'rows' => 4,
            'cols' => 160
        ]);
        $html .= html_writer::end_div();

        $html .= html_writer::end_tag('fieldset');

        return $html;
    }   


    private function criteria_row($name, $criteriaText, $studentid){
        /*
        * Render the criteria question and its radio button scale for each criteria       
        */

        $criteria_labels = [
            1 => 'Very Poor',
            2 => 'Poor',
            3 => 'Average',
            4 => 'Good',
            5 => 'Excellent'
        ];
        $html = html_writer::start_div('form-row');
        $html .= html_writer::label($criteriaText, "{$name}_{$studentid}", ["class" => "criteria-text"]);

        $html .= html_writer::start_tag('span', ['id' => "{$name}_{$studentid}"]);
        foreach ($criteria_labels as $value => $text) {
            $input = html_writer::empty_tag('input', [
                'type' => 'radio',
                'name' => "{$name}[{$studentid}]",
                'value' => $value,
                'required' => 'required'
            ]);
            $html .= html_writer::tag('label', $input . ' ' . $text, [
                'class' => "scale-value"
                // 'style' => 'margin-right:8px; display:inline-block;'
            ]);
        }
        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_div();

        return $html;
    }

    public function no_peers_message() {
        return html_writer::tag('p', 'You are not in a group with other students. Please contact your unit coordinator.', ['class' => 'no-peers']);
    }


    public function submission_success_notification(){
        $html = $this->notification(                                                 // Notify student that submission was successful
        "All evaluations submitted! Grades updated for assessed members.", 
        \core\output\notification::NOTIFY_SUCCESS
        );

        return $html;
    }
}
?>