<?php
require_once('../../config.php');
require_once('criteria_bank_form.php');
require_once($CFG->libdir.'/tablelib.php');

global $DB;

// -----------------------------------------------------------------
// 1. Get course/module/context/plugin instance
$id = required_param('id', PARAM_INT); // course module id
$cm = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
$courseid = $cm->course;
$context = context_module::instance($cm->id);
$course = get_course($courseid);

require_login($course, false, $cm);
require_capability('mod/speval:addinstance', $context);

// -----------------------------------------------------------------
// 2. Check for action parameter (delete)
$action = optional_param('action', '', PARAM_ALPHA);
$deleteid = optional_param('deleteid', 0, PARAM_INT);

if ($action === 'delete' && $deleteid) {
    $DB->delete_records('speval_criteria_bank', ['id' => $deleteid]);
    redirect(new moodle_url('/mod/speval/criteria_bank.php', ['id' => $id]),
        get_string('questiondeleted', 'mod_speval'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// -----------------------------------------------------------------
// 4. Handle form submission
$form = new \mod_speval\form\criteria_bank_form(null, ['courseid' => $courseid, 'cmid' => $id]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
} else if ($data = $form->get_data()) {
    $record = new stdClass();
    $record->questiontext = $data->questiontext;
    $record->isopenquestion = $data->isopenquestion;
    $record->courseid = $data->courseid;

    $DB->insert_record('speval_criteria_bank', $record);
    redirect(new moodle_url('/mod/speval/criteria_bank.php', ['id' => $id]),
        get_string('questionsaved', 'mod_speval'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// -----------------------------------------------------------------
// 3. Set up page
$PAGE->set_url('/mod/speval/criteria_bank.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('criteriabank', 'mod_speval'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('criteriabank', 'mod_speval'));



// -----------------------------------------------------------------
// 5. Display form
$form->display();

// -----------------------------------------------------------------
// 6. Display existing questions in a table
$questions = $DB->get_records('speval_criteria_bank', ['courseid' => $courseid], 'id ASC');

if ($questions) {
    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::start_tag('thead');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Question Text');
    echo html_writer::tag('th', 'Open Question?');
    echo html_writer::tag('th', 'Actions');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');
    foreach ($questions as $q) {
        $deleteurl = new moodle_url('/mod/speval/criteria_bank.php', [
            'id' => $id,
            'action' => 'delete',
            'deleteid' => $q->id
        ]);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $q->id);
        echo html_writer::tag('td', format_string($q->questiontext));
        echo html_writer::tag('td', $q->isopenquestion ? 'Yes' : 'No');
        echo html_writer::tag('td', html_writer::link($deleteurl, get_string('delete', 'mod_speval')));
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo $OUTPUT->footer();