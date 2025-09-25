<?php
// Core Moodle includes.
require(__DIR__.'/../../config.php');
require_login();

$id = required_param('id', PARAM_INT); // Course module id.
$cm = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
require_capability('mod/speval:view', $context);

// Set the course module on the page object.
$PAGE->set_cm($cm);

// Set the page context.
$PAGE->set_context($context);

// Set the page URL.
$url = new moodle_url('/mod/speval/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);

// === Add this line to include your CSS file ===
$PAGE->requires->css(new moodle_url('/mod/speval/styles.css'));



// Output the page header and main heading
echo $OUTPUT->header();
echo $OUTPUT->heading('Self & Peer Evaluation');

// Very simple submission form (not Moodleform for brevity).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    global $DB, $USER, $COURSE;

    // === The fix is here: using optional_param instead of required_param for non-required fields ===
    $record = (object)[
        'unitid'      => $COURSE->id,
        'userid'      => $USER->id,
        //need to change this to auto fill
        'peerid'      => required_param('peerid', PARAM_INT),
        'criteria1'   => optional_param('c1', 0, PARAM_INT),
        'criteria2'   => optional_param('c2', 0, PARAM_INT),
        'criteria3'   => optional_param('c3', 0, PARAM_INT),
        'criteria4'   => optional_param('c4', 0, PARAM_INT),
        'criteria5'   => optional_param('c5', 0, PARAM_INT),
        'comment'     => optional_param('comment', '', PARAM_TEXT),
        'timecreated' => time(),
    ];
    $DB->insert_record('speval_eval', $record);
    echo $OUTPUT->notification("Evaluation submitted! Do the Same for remaining group members or exit if you are done.", 'notifysuccess');
}

?>
// Main container for the evaluation forms and instructions
?>
<div class="speval-container">
    <!-- Main heading and instructions for students -->
    <h2>Self & Peer Evaluation</h2>
    <b><p>Please note:</b> Everything that you put into this form will be kept strictly confidential by the unit coordinator.<br><br>
    Enter the details below for you and your peers.</p>
    <p>
    1 = Very poor, or even obstructive, contribution to the project process<br>

<?php
// Renders a form for evaluating a single peer (team member)
function speval_render_form_for_peer($peerid, $peername) {
    ?>
    <!-- Evaluation form for a single peer -->
    <form method="post" style="border:1px solid #ccc; margin-bottom:20px; padding:15px;">
        <!-- Hidden fields for session and peer ID -->
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>" />
        <input type="hidden" name="peerid" value="<?php echo (int)$peerid; ?>" />
        <!-- Peer name heading -->
        <h3>Evaluate: <?php echo htmlspecialchars($peername); ?></h3>
        <!-- Criteria 1 -->
        <div class="form-row">
            <label for="c1_<?php echo (int)$peerid; ?>">1. The amount of work and effort put into the Requirements and Analysis Document, the Project Management Plan, and the Design Document.</label>
            <select name="c1" id="c1_<?php echo (int)$peerid; ?>" required>
                <option value="" disabled selected>Select a rating</option>
                <option value="1">1 - Very poor</option>
                <option value="2">2 - Poor</option>
                <option value="3">3 - Acceptable</option>
                <option value="4">4 - Good</option>
                <option value="5">5 - Excellent</option>
            </select>
        </div>
        <!-- Criteria 2 -->
        <div class="form-row">
            <label for="c2_<?php echo (int)$peerid; ?>">2. Willingness to work as part of the group and taking responsibility in the group.</label>
            <select name="c2" id="c2_<?php echo (int)$peerid; ?>" required>
                <option value="" disabled selected>Select a rating</option>
                <option value="1">1 - Very poor</option>
                <option value="2">2 - Poor</option>
                <option value="3">3 - Acceptable</option>
                <option value="4">4 - Good</option>
                <option value="5">5 - Excellent</option>
            </select>
        </div>
        <!-- Criteria 3 -->
        <div class="form-row">
            <label for="c3_<?php echo (int)$peerid; ?>">3. Communication within the group and participation in group meetings.</label>
            <select name="c3" id="c3_<?php echo (int)$peerid; ?>" required>
                <option value="" disabled selected>Select a rating</option>
                <option value="1">1 - Very poor</option>
                <option value="2">2 - Poor</option>
                <option value="3">3 - Acceptable</option>
                <option value="4">4 - Good</option>
                <option value="5">5 - Excellent</option>
            </select>
        </div>
        <!-- Criteria 4 -->
        <div class="form-row">
            <label for="c4_<?php echo (int)$peerid; ?>">4. Contribution to the management of the project, e.g. work delivered on time.</label>
            <select name="c4" id="c4_<?php echo (int)$peerid; ?>" required>
                <option value="" disabled selected>Select a rating</option>
                <option value="1">1 - Very poor</option>
                <option value="2">2 - Poor</option>
                <option value="3">3 - Acceptable</option>
                <option value="4">4 - Good</option>
                <option value="5">5 - Excellent</option>
            </select>
        </div>
        <!-- Criteria 5 -->
        <div class="form-row">
            <label for="c5_<?php echo (int)$peerid; ?>">5. Problem solving and creativity on behalf of the group’s work.</label>
            <select name="c5" id="c5_<?php echo (int)$peerid; ?>" required>
                <option value="" disabled selected>Select a rating</option>
                <option value="1">1 - Very poor</option>
                <option value="2">2 - Poor</option>
                <option value="3">3 - Acceptable</option>
                <option value="4">4 - Good</option>
                <option value="5">5 - Excellent</option>
            </select>
        </div>
        <!-- Comment field -->
        <div class="form-row">
            <label for="comment_<?php echo (int)$peerid; ?>">Comment:</label>
            <textarea name="comment" id="comment_<?php echo (int)$peerid; ?>" rows="4" cols="40"></textarea>
        </div>
        <!-- Submit button -->
        <div class="form-actions">
            <button type="submit">Submit</button>
        </div>
    </form>
    <?php
}

// Get all group/team members (including self) and render a form for each
require_once($CFG->dirroot . '/user/lib.php');
$courseid = isset($COURSE->id) ? $COURSE->id : (isset($course->id) ? $course->id : 0);
$groupids = $DB->get_fieldset_select('groups', 'id', 'courseid = ?', [$courseid]);
$usergroupids = $DB->get_fieldset_select('groups_members', 'groupid', 'userid = ? AND groupid IN (' . implode(',', $groupids) . ')', [$USER->id]);
$allmemberids = [$USER->id];
if (!empty($usergroupids)) {
    list($ingroupsql, $groupparams) = $DB->get_in_or_equal($usergroupids);
    $allmemberids = $DB->get_fieldset_select('groups_members', 'userid', 'groupid ' . $ingroupsql, $groupparams);
    if (!in_array($USER->id, $allmemberids)) {
        $allmemberids[] = $USER->id;
    }
}
$allmemberids = array_unique($allmemberids);
if (!empty($allmemberids)) {
    list($in_sql, $params) = $DB->get_in_or_equal($allmemberids);
    $students = $DB->get_records_select('user', 'id ' . $in_sql, $params, 'lastname,firstname', '*');
    foreach ($students as $student) {
        // Ensure all required fields for fullname()
        foreach ([
            'firstnamephonetic','lastnamephonetic','middlename','alternatename'
        ] as $field) {
            if (!isset($student->$field)) {
                $student->$field = '';
            }
        }
        // Render a form for this peer
        speval_render_form_for_peer($student->id, fullname($student));
    }
}

