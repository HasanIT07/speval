<?php
// Core Moodle includes and authentication
require(__DIR__.'/../../config.php');
require_login();

// Get the course module id from the URL and fetch the course module and context
$id = required_param('id', PARAM_INT); // Course module id
$cm = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
require_capability('mod/speval:view', $context); // Check user has permission to view

// Set up the Moodle page object
$PAGE->set_cm($cm);

// Set the page context.
$PAGE->set_context($context);

// Set the page URL.
$url = new moodle_url('/mod/speval/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);

$selected = 'spe'; // For navigation highlighting (if tabs are used)

// Add custom CSS for the module
$PAGE->requires->css(new moodle_url('/mod/speval/styles.css'));

// Output the page header and heading
echo $OUTPUT->header();
echo $OUTPUT->heading('Self & Peer Evaluation');

// Handle form submission for peer/self evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    global $DB, $USER, $COURSE;

    // Build the record to insert into the evaluation table
    $record = (object)[
        'unitid'      => $COURSE->id, // Course id
        'userid'      => $USER->id,   // The user submitting the evaluation
        'peerid'      => required_param('peerid', PARAM_INT), // The peer being evaluated
        'criteria1'   => optional_param('c1', 0, PARAM_INT),
        'criteria2'   => optional_param('c2', 0, PARAM_INT),
        'criteria3'   => optional_param('c3', 0, PARAM_INT),
        'criteria4'   => optional_param('c4', 0, PARAM_INT),
        'criteria5'   => optional_param('c5', 0, PARAM_INT),
        'comment'     => optional_param('comment', '', PARAM_TEXT),
        'timecreated' => time(),
    ];
    // Insert the evaluation record into the database
    $DB->insert_record('speval_eval', $record);
    // Show a notification after submission
    echo $OUTPUT->notification("Evaluation submitted! Do the Same for remaining group members or exit if you are done.", 'notifysuccess');
}

?>
<div class="speval-container">
    <h2>Self & Peer Evaluation</h2>
    <b><p>Please note:</b> Everything that you put into this form will be kept strictly confidential by the unit coordinator.
                  1 = Very poor, or even obstructive, contribution to the project process<br>
                2 = Poor contribution to the project process<br>
                3 = acceptable contribution to the project process<br>
                4 = good contribution to the project process<br>
                5 = excellent contribution to the project process<br><br>
                <b>Using the assessment scales above. Fill out the Following</b><br>
</br></br>

Enter the delails below for you and your peers.
</p>

    <form method="post">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>" />



        <?php
        global $COURSE, $USER, $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $courseid = isset($COURSE->id) ? $COURSE->id : (isset($course->id) ? $course->id : 0); // Get course id
        // Get all group IDs for this user in this course
        $groupids = $DB->get_fieldset_select('groups', 'id', 'courseid = ?', [$courseid]);
        $usergroupids = [];
        if (!empty($groupids)) {
            // Find which groups the user belongs to
            $usergroupids = $DB->get_fieldset_select('groups_members', 'groupid', 'userid = ? AND groupid IN (' . implode(',', $groupids) . ')', [$USER->id]);
        }
        $allmemberids = [$USER->id]; // Always include self
        if (!empty($usergroupids)) {
            // Get all user ids in the same groups
            list($ingroupsql, $groupparams) = $DB->get_in_or_equal($usergroupids);
            $allmemberids = $DB->get_fieldset_select('groups_members', 'userid', 'groupid ' . $ingroupsql, $groupparams);
            if (!in_array($USER->id, $allmemberids)) {
                $allmemberids[] = $USER->id;
            }
        }
        $allmemberids = array_unique($allmemberids);
        if (!empty($allmemberids)) {
            // Get user records for all group members (including self)
            list($in_sql, $params) = $DB->get_in_or_equal($allmemberids);
            $students = $DB->get_records_select('user', 'id ' . $in_sql, $params, 'lastname,firstname', 'id,firstname,lastname');
            foreach ($students as $student) {
                $fullname = fullname($student); // Get full name for display
        ?>
    // Render a form for each group member (including self)
    <form method="post" class="speval-peerform">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>" />
            <input type="hidden" name="peerid" value="<?php echo $student->id; ?>" />
            <div class="form-row">
                <!-- Display the name of the peer being evaluated -->
                <label><b><?php echo s($fullname); ?></b></label>
            </div>
            <p>
  
            </p>
            <!-- Criteria 1: Effort and documentation , note inline block ensure same line buttons-->
            <div class="form-row">
                <label for="c1_<?php echo $student->id; ?>">1. The amount of work and effort put into the Requirements and Analysis Document, the Project Management Plan, and the Design Document.</label>
                <span id="c1_<?php echo $student->id; ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label style="margin-right:8px; display:inline-block;">
                        <input type="radio" name="c1" value="<?php echo $i; ?>" required> <?php echo $i; ?>
                    </label>
                <?php endfor; ?>
                </span>
            </div>
            <!-- Criteria 2: Teamwork -->
            <div class="form-row">
                <label for="c2_<?php echo $student->id; ?>">2. Willingness to work as part of the group and taking responsibility in the group.</label>
                <span id="c2_<?php echo $student->id; ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label style="margin-right:8px; display:inline-block;">
                        <input type="radio" name="c2" value="<?php echo $i; ?>" required> <?php echo $i; ?>
                    </label>
                <?php endfor; ?>
                </span>
            </div>
            <!-- Criteria 3: Communication -->
            <div class="form-row">
                <label for="c3_<?php echo $student->id; ?>">3. Communication within the group and participation in group meetings.</label>
                <span id="c3_<?php echo $student->id; ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label style="margin-right:8px; display:inline-block;">
                        <input type="radio" name="c3" value="<?php echo $i; ?>" required> <?php echo $i; ?>
                    </label>
                <?php endfor; ?>
                </span>
            </div>
            <!-- Criteria 4: Project management -->
            <div class="form-row">
                <label for="c4_<?php echo $student->id; ?>">4. Contribution to the management of the project, e.g. work delivered on time.</label>
                <span id="c4_<?php echo $student->id; ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label style="margin-right:8px; display:inline-block;">
                        <input type="radio" name="c4" value="<?php echo $i; ?>" required> <?php echo $i; ?>
                    </label>
                <?php endfor; ?>
                </span>
            </div>
            <!-- Criteria 5: Problem solving -->
            <div class="form-row">
                <label for="c5_<?php echo $student->id; ?>">5. Problem solving and creativity on behalf of the group’s work.</label>
                <span id="c5_<?php echo $student->id; ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label style="margin-right:8px; display:inline-block;">
                        <input type="radio" name="c5" value="<?php echo $i; ?>" required> <?php echo $i; ?>
                    </label>
                <?php endfor; ?>
                </span>
            </div>
            <!-- Free-text comment field -->
            <div class="form-row">
                <label for="comment_<?php echo $student->id; ?>">Comment:</label>
                <textarea name="comment" id="comment_<?php echo $student->id; ?>" rows="4" cols="40"></textarea>
            </div>
        </form>
    <hr> <!-- Divider between forms for each peer/self -->
        <?php
            }
        }
        ?>

        <div class="form-actions">
            <button type="submit">Submit</button>
        </div>
    </form>
<?php
?>
</div>
<?php
// Output the page footer
echo $OUTPUT->footer();
?>
