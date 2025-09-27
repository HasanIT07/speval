<?php
require(__DIR__.'/../../config.php');
require_login();


$id = required_param('id', PARAM_INT);
$start = optional_param('start', 0, PARAM_INT); // Show form if start=1
$cm = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
require_capability('mod/speval:view', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$url = new moodle_url('/mod/speval/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->requires->css(new moodle_url('/mod/speval/styles.css'));


echo $OUTPUT->header();

global $DB, $USER, $COURSE, $CFG;
$speval = $DB->get_record('speval', ['id' => $cm->instance]);
$courseid = $COURSE->id;

if (!$start) {
    // Initial info/landing page before evaluation starts
    echo $OUTPUT->heading(format_string($cm->name));
    if (!empty($speval->intro)) {
        echo $OUTPUT->box(format_module_intro('speval', $speval, $cm->id), 'generalbox');
    }
    // Show grading info if available
    if (isset($speval->grade)) {
        echo '<p><b>Maximum grade:</b> ' . (float)$speval->grade . '</p>';
    }
    // Show group members (if any)
    $groupids = $DB->get_fieldset_select('groups', 'id', 'courseid = ?', [$courseid]);
    $usergroupids = [];
    if (!empty($groupids)) {
        $usergroupids = $DB->get_fieldset_select('groups_members', 'groupid', 'userid = ? AND groupid IN (' . implode(',', $groupids) . ')', [$USER->id]);
    }
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
        $students = $DB->get_records_select('user',
            'id ' . $in_sql,
            $params,
            'lastname,firstname',
            'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
        );
        echo '</ul>';
    }
    // Instructions
    echo '<div class="speval-container">';
    echo '<h2>Self & Peer Evaluation</h2>';
    echo '<b><p>Please note:</b> Everything you put into this form will be kept strictly confidential by the unit coordinator.<br>';
    echo '<b>Contribution Ratings:</b><br>';
    echo '- Very Poor: Very poor, or even obstructive, contribution to the project process<br>';
    echo '- Poor: Poor contribution to the project process<br>';
    echo '- Average: Acceptable contribution to the project process<br>';
    echo '- Good: Good contribution to the project process<br>';
    echo '- Excellent: Excellent contribution to the project process<br><br>';
    echo '<b>Using the assessment scales above, fill out the following.</b>';
    echo '</p>';
    echo '</div>';

    // Start button
    $starturl = new moodle_url('/mod/speval/view.php', ['id' => $cm->id, 'start' => 1]);
    echo '<form method="get" action="' . $starturl . '">';
    echo '<input type="hidden" name="id" value="' . $cm->id . '" />';
    echo '<input type="hidden" name="start" value="1" />';
    echo '<button type="submit">Start Evaluation</button>';
    echo '</form>';
    echo $OUTPUT->footer();
    exit;
}
// ...existing code...

// Render a criteria row for a user (uses array field names)
function speval_criteria_row($name, $label, $studentid) {
    $criteria_labels = [
        1 => 'Very Poor',
        2 => 'Poor',
        3 => 'Average',
        4 => 'Good',
        5 => 'Excellent'
    ];
    echo '<div class="form-row">';
    echo "<label for=\"{$name}_{$studentid}\">{$label}</label>";
    echo "<span id=\"{$name}_{$studentid}\">";
    foreach ($criteria_labels as $value => $text) {
        echo '<label style="margin-right:8px; display:inline-block;">';
        echo "<input type=\"radio\" name=\"{$name}[{$studentid}]\" value=\"{$value}\" required> {$text}";
        echo '</label>';
    }
    echo '</span>';
    echo '</div>';
}

// Render all fields for a peer
function speval_peer_fields($student) {
    $studentid = $student->id;
    $fullname  = fullname($student);
    echo "<fieldset class=\"speval-peer\">";
    echo "<legend><b>" . s($fullname) . "</b></legend>";
    speval_criteria_row("c1", "1. The amount of work and effort put into the Requirements and Analysis Document, the Project Management Plan, and the Design Document.", $studentid);
    speval_criteria_row("c2", "2. Willingness to work as part of the group and taking responsibility in the group.", $studentid);
    speval_criteria_row("c3", "3. Communication within the group and participation in group meetings.", $studentid);
    speval_criteria_row("c4", "4. Contribution to the management of the project, e.g. work delivered on time.", $studentid);
    speval_criteria_row("c5", "5. Problem solving and creativity on behalf of the group’s work.", $studentid);
    echo '<div class="form-row">';
    echo "<label for=\"comment_{$studentid}\">Comment:</label>";
    echo "<textarea name=\"comment[{$studentid}]\" id=\"comment_{$studentid}\" rows=\"4\" cols=\"40\"></textarea>";
    echo "</div>";
    echo "</fieldset><hr>";
}

// Handle form submission (all-in-one)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    global $DB, $USER, $COURSE;
    $courseid = $COURSE->id;

    // Safely get arrays, if not present, initialize as empty array
    $c1 = isset($_POST['c1']) && is_array($_POST['c1']) ? $_POST['c1'] : [];
    $c2 = isset($_POST['c2']) && is_array($_POST['c2']) ? $_POST['c2'] : [];
    $c3 = isset($_POST['c3']) && is_array($_POST['c3']) ? $_POST['c3'] : [];
    $c4 = isset($_POST['c4']) && is_array($_POST['c4']) ? $_POST['c4'] : [];
    $c5 = isset($_POST['c5']) && is_array($_POST['c5']) ? $_POST['c5'] : [];
    $comments = isset($_POST['comment']) && is_array($_POST['comment']) ? $_POST['comment'] : [];

    // Only process if at least one peer/self was submitted
    if (!empty($c1)) {
        // Insert new evaluations
        foreach ($c1 as $peerid => $value) {
            $record = (object)[
                'unitid'      => $courseid,
                'userid'      => $USER->id,
                'peerid'      => $peerid,
                'criteria1'   => isset($c1[$peerid]) ? $c1[$peerid] : 0,
                'criteria2'   => isset($c2[$peerid]) ? $c2[$peerid] : 0,
                'criteria3'   => isset($c3[$peerid]) ? $c3[$peerid] : 0,
                'criteria4'   => isset($c4[$peerid]) ? $c4[$peerid] : 0,
                'criteria5'   => isset($c5[$peerid]) ? $c5[$peerid] : 0,
                'comment'     => isset($comments[$peerid]) ? $comments[$peerid] : '',
                'timecreated' => time(),
            ];
            $DB->insert_record('speval_eval', $record);
        }

        // Calculate and update grades for all assessed members
        $peerids = array_keys($c1);
        // Get max grade from activity settings
        $speval = $DB->get_record('speval', ['id' => $cm->instance]);
        $maxgrade = isset($speval->grade) ? $speval->grade : 100;

        foreach ($peerids as $peerid) {
            // Get all evaluations received by this peer
            $evals = $DB->get_records('speval_eval', ['unitid' => $courseid, 'peerid' => $peerid]);
            $total = 0;
            $count = 0;
            foreach ($evals as $eval) {
                $sum = $eval->criteria1 + $eval->criteria2 + $eval->criteria3 + $eval->criteria4 + $eval->criteria5;
                $total += $sum / 5.0; // average for this evaluation
                $count++;
            }
            $avg = $count > 0 ? $total / $count : 0;
            // Normalize to max grade (scale 1-5 to maxgrade)
            $grade = $maxgrade * ($avg / 5.0);

            // Update gradebook
            require_once($CFG->libdir.'/gradelib.php');
            $grades = [ $peerid => (object)['userid' => $peerid, 'rawgrade' => $grade] ];
            grade_update('mod/speval', $courseid, 'mod', 'speval', $cm->instance, 0, $grades);
        }

        echo $OUTPUT->notification("All evaluations submitted! Grades updated for assessed members.", 'notifysuccess');
    }
}
?>

<div class="speval-container">
    <h2>Self & Peer Evaluation</h2>
    <b><p>Please note:</b> Everything you put into this form will be kept strictly confidential by the unit coordinator.<br>
    <!-- add information about grading here -->

    <form method="post">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>" />
        <?php
        global $COURSE, $USER, $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $courseid = isset($COURSE->id) ? $COURSE->id : (isset($course->id) ? $course->id : 0);
        $groupids = $DB->get_fieldset_select('groups', 'id', 'courseid = ?', [$courseid]);
        $usergroupids = [];
        if (!empty($groupids)) {
            $usergroupids = $DB->get_fieldset_select('groups_members', 'groupid', 'userid = ? AND groupid IN (' . implode(',', $groupids) . ')', [$USER->id]);
        }
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
            // Fetch all required user fields for fullname()
            $students = $DB->get_records_select('user',
                'id ' . $in_sql,
                $params,
                'lastname,firstname',
                'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
            );
            foreach ($students as $student) {
                speval_peer_fields($student);
            }
        }
        ?>
        <div class="form-actions">
            <button type="submit">Submit All Evaluations</button>
        </div>
    </form>
</div>
<?php
echo $OUTPUT->footer();
?>
