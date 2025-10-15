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

        global $DB, $USER;
        
        // Initial info/landing page before evaluation starts
        $html = $this->output->heading(format_string($cm->name));                   

        // if (!empty($speval->intro)) {
        //     $html .= $this->output->box(format_module_intro('speval', $speval, $cm->id), 'generalbox');                 
        // }

        // Show grading info if available
        if (isset($speval->grade)) {
            $html .= html_writer::tag('p', '<b>Maximum grade:</b> ' . (float)$speval->grade);
        }

        // Initial instructions
        $html .= html_writer::start_div('speval-container');
        
            $html .= html_writer::tag('h2', 'Self & Peer Evaluation');
            $html .= $this->output->box(format_module_intro('speval', $speval, $cm->id), 'generalbox');     
            $starturl = new moodle_url('/mod/speval/view.php', ['id' => $cm->id, 'start' => 1]);        
            $html .= html_writer::start_tag('form', ['method' => 'get', 'action' => $starturl, 'style' => 'display:inline-block;']);
            $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
            $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'start', 'value' => 1]);
            $html .= html_writer::tag('button', 'Start Self and Peer Evaluation', ['type' => 'submit', 'class' => 'spebutton']);
            $html .= html_writer::end_tag('form');
            
        $html .= html_writer::end_div();

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
            $is_self = ($student->id == $GLOBALS['USER']->id);
            $html .= $this->peer_fields($speval, $student, $is_self);
        }

        // Lets temporarely comment this so testing can be done faster v

        // // Simple 1-minute countdown above the submit button
        // $html .= html_writer::div(
        //     html_writer::tag('div',
        //         '<span id="speval-timer-msg" style="color:#b00; font-weight:bold;">Please spend at least 1 minute on your evaluation before submitting. <span id="speval-timer">60</span> seconds left.</span>',
        //         ['style' => 'margin-bottom:8px;']
        //     ) .
        //     html_writer::tag('button', 'Submit All Evaluations', [
        //         'type' => 'submit',
        //         'id' => 'speval-submit-btn',
        //         'disabled' => 'disabled',
        //         'style' => 'opacity:0.5;'
        //     ]),
        //     'form-actions'
        // );

        // $html .= html_writer::end_tag('form');
        // $html .= html_writer::end_div();

        // // Simple JS timer (no localStorage)


        // $html .= "<script>
        // (function() {
        //     var btn = document.getElementById('speval-submit-btn');
        //     var timerSpan = document.getElementById('speval-timer');
        //     var msg = document.getElementById('speval-timer-msg');
        //     var seconds = 60;
        //     var interval = setInterval(function() {
        //         seconds--;
        //         timerSpan.textContent = seconds;
        //         if (seconds <= 0) {
        //             clearInterval(interval);
        //             btn.disabled = false;
        //             btn.style.opacity = 1;
        //             msg.textContent = '';
        //         }
        //     }, 1000);
        // })();
        // </script>";

        return $html;
    }


    // $is_self: true for self-evaluation, false for peer
    public function peer_fields($speval, $student, $is_self = false) {
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
        $html .= html_writer::tag('legend', $is_self ? 'Self Evaluation' : s(fullname($student)), ['class' => 'peer-name']);
        // Render more questions for self, fewer for peer ➜ !The number of scale criteria should remain the same. What needs to change is the number of comments  
        $num_questions = $is_self ? $criteria_data->n_criteria : min($criteria_data->n_criteria, 5); // I am changing this to 5 temporarely but this num_questions might not be needed
        for ($i=1; $i<=$num_questions; $i++){
            $criteriatext = $criteria_data->{"criteria_text$i"};
            $html .= $this->criteria_row("criteria_text$i", $criteriatext, $studentid);
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
        // Optionally: add a hidden field for column2 (for extra data)
        $html .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => "column2[{$studentid}]",
            'value' => '' // Fill with JS or backend as needed
        ]);
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
        $html .= '<br>';
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


    public function display_grade_for_student($user, $speval){
        global $DB;

        // If the user already submitted, show a message instead of the start button
        $html = html_writer::div(
            html_writer::tag('p', 'You have already submitted your evaluation. Thank you!', ['class' => 'alert alert-info'])
        );
        
        $html .= html_writer::tag('h3', 'Your Grade');

        $grade = $DB->get_record('speval_grades', [
            'userid' => $user->id,
            'activityid' => $speval->id
        ]);

        if ($grade) {
            $html .= html_writer::tag('p', "Final Grade: $grade->finalgrade");
        } else {
            $html .= html_writer::tag('p', "No grade available yet.");
        }

        return $html;
    }

}
?>