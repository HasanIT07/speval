<?php

namespace mod_speval\output;

defined('MOODLE_INTERNAL') || die();

use \plugin_renderer_base;
use \moodle_url;
use \html_writer;

class renderer extends plugin_renderer_base {
    /*
     * Render the landing page instructions and start button
     *
     * @param int $cmid Course module ID
     * @return string HTML
     */

    public function speval_start($cmid){
        // Initial instructions
        $html = html_writer::start_div('speval-container');
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
        $starturl = new moodle_url('/mod/speval/view.php', ['id' => $cmid, 'start' => 1]);
        

        $html .= html_writer::start_tag('form', ['method' => 'get', 'action' => $starturl]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'start', 'value' => 1]);
        $html .= html_writer::tag('button', 'Start Evaluation', ['type' => 'submit', 'class' => 'submit-button']);
        $html .= html_writer::end_tag('form');
        return $html;
    }


    private function criteria_row($name, $label, $studentid){
        $criteria_labels = [
            1 => 'Very Poor',
            2 => 'Poor',
            3 => 'Average',
            4 => 'Good',
            5 => 'Excellent'
        ];
        $html = html_writer::start_div('form-row');
        $html .= html_writer::label($label, "{$name}_{$studentid}");

        $html .= html_writer::start_tag('span', ['id' => "{$name}_{$studentid}"]);
        foreach ($criteria_labels as $value => $text) {
            $input = html_writer::empty_tag('input', [
                'type' => 'radio',
                'name' => "{$name}[{$studentid}]",
                'value' => $value,
                'required' => 'required'
            ]);
            $html .= html_writer::tag('label', $input . ' ' . $text, [
                'style' => 'margin-right:8px; display:inline-block;'
            ]);
        }
        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_div();

        return $html;
    }


    public function peer_fields($student) {
        $studentid = $student->id;
        $fullname  = fullname($student);

        $html  = html_writer::start_tag('fieldset', ['class' => 'speval-peer']);
        $html .= html_writer::tag('legend', s(fullname($student)), ['class' => 'peer-name']);
        $html .= $this->criteria_row('c1', "1. The amount of work and effort put into the Requirements and Analysis Document, the Project Management Plan, and the Design Document.", $studentid);
        $html .= $this->criteria_row("c2", "2. Willingness to work as part of the group and taking responsibility in the group.", $studentid);
        $html .= $this->criteria_row('c3', "3. Communication within the group and participation in group meetings.", $studentid);
        $html .= $this->criteria_row("c4", "4. Contribution to the management of the project, e.g. work delivered on time.", $studentid);
        $html .= $this->criteria_row("c5", "5. Problem solving and creativity on behalf of the group’s work.", $studentid);    

        $html .= html_writer::end_tag('fieldset');
        return $html;
    }   
}
?>