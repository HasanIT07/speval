<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$id = required_param('id', PARAM_INT);                                                  // Get the mdl_course_module id
list($course, $cm) = get_course_and_cm_from_cmid($id, 'speval');                        // Get the course and cm info from the id
$speval = $DB->get_record('speval', ['id' => $cm->instance], '*', MUST_EXIST);          // Get the speval instance record from the DB

$context = context_module::instance($cm->id);                                           // Get the context from the course module
require_capability('mod/speval:grade', $context);                                        // Ensure the user has permission to view this activity

// Correct PAGE setup
$PAGE->set_url(new moodle_url('/mod/speval/results.php', ['id' => $cm->id]));           // Set the URL for this page
$PAGE->set_cm($cm, $course);
$PAGE->set_context($context);
$PAGE->set_title(get_string('results', 'speval'));
$PAGE->set_heading($course->fullname);

// Output starts
$PAGE->activityheader->disable();
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('results', 'speval'));

// Student View was moved to view.php. By convention moodle does not show tabs for students. Access security is now set at line 10 of this file.

// Teacher/manager view
//this button is to trigger grade calculation for all students
echo $OUTPUT->single_button(
    new moodle_url('/mod/speval/grade_service.php', ['id' => $cm->id]),
    get_string('gradeall', 'speval'),
    'post'
);

// New buttons to export CSV
    echo $OUTPUT->single_button(
        new moodle_url('/mod/speval/export_csv.php', ['id' => $cm->id, 'table' => 'speval_eval']),
        'Export Eval CSV',
        'get'
    );

    echo $OUTPUT->single_button(
        new moodle_url('/mod/speval/export_csv.php', ['id' => $cm->id, 'table' => 'speval_grades']),
        'Export Grades CSV',
        'get'
    );

    echo $OUTPUT->single_button(
        new moodle_url('/mod/speval/export_csv.php', ['id' => $cm->id, 'table' => 'speval_flag_individual']),
        'Export AI Flags CSV',
        'get'
    );

// If there are no submissions yet for this activity, show info and stop

$hassubmissions = $DB->record_exists('speval_eval', ['activityid' => $cm->instance]);
if (!$hassubmissions) {
	$sm = get_string_manager();
	$nosubmsg = 'No submissions have been made for this activity yet.';
	if ($sm->string_exists('nosubmissionsyet', 'mod_speval')) {
		$nosubmsg = get_string('nosubmissionsyet', 'mod_speval');
	} else if ($sm->string_exists('nosubmissionsyet', 'speval')) {
		$nosubmsg = get_string('nosubmissionsyet', 'speval');
	}
	echo $OUTPUT->notification($nosubmsg, 'notifyinfo');
	echo $OUTPUT->footer();
	exit;
}

// Show results: grouped, per-student flags + final grade

// 1) Enrolled students in the course
$enrolled_students = $DB->get_records_sql("\n    SELECT u.id, u.firstname, u.lastname\n    FROM {user} u\n    JOIN {user_enrolments} ue ON ue.userid = u.id\n    JOIN {enrol} e ON e.id = ue.enrolid\n    WHERE e.courseid = :courseid\n", ['courseid' => $course->id]);

if (!$enrolled_students) {
    echo $OUTPUT->notification(get_string('nousersfound', 'speval'), 'notifyproblem');
	echo $OUTPUT->footer();
	exit;
}

// 2) Grades for this activity (may be empty if not yet calculated)
$grades = $DB->get_records('speval_grades', ['activityid' => $cm->instance], '', 'userid, id, finalgrade, criteria1, criteria2, criteria3, criteria4, criteria5');

// 3) AI flags (individual) for this activity
$flags = $DB->get_records('speval_flag_individual', ['activityid' => $cm->instance]);

// 4) Aggregate flags per student (as peer)
$peerflags = []; // peerid => aggregated info
foreach ($flags as $f) {
	$peerid = isset($f->peerid) ? $f->peerid : (isset($f->peer) ? $f->peer : 0);
	if (!$peerid) { continue; }
    if (!isset($peerflags[$peerid])) {
        $peerflags[$peerid] = [
            'comment' => false,
            'quick' => false,
            'misbehaviour_categories' => []
        ];
    }
    if (!empty($f->commentdiscrepancy)) {
		$peerflags[$peerid]['comment'] = true;
	}
    if (!empty($f->quicksubmissiondiscrepancy)) {
        $peerflags[$peerid]['quick'] = true;
    }
	// Collect misbehaviour categories if flagged (>1 indicates category beyond baseline)
	if (isset($f->misbehaviorcategory) && (int)$f->misbehaviorcategory >= 1) {
		$peerflags[$peerid]['misbehaviour_categories'][] = (int)$f->misbehaviorcategory;
	}
}

// Misbehaviour labels (1..6) with robust lookup across components, with defaults
$misdefaults = [
    1 => 'Normal or positive teamwork behaviour',
    2 => 'Aggressive or hostile behaviour',
    3 => 'Uncooperative or ignoring messages behaviour',
    4 => 'Irresponsible or unreliable behaviour',
    5 => 'Harassment or inappropriate comments behaviour',
    6 => 'Dishonest or plagiarism behaviour'
];

$stringman = get_string_manager();
$mislabels_map = [];
for ($i = 1; $i <= 6; $i++) {
    $key = 'misbehaviour_' . $i;
    if ($stringman->string_exists($key, 'mod_speval')) {
        $mislabels_map[$i] = get_string($key, 'mod_speval');
    } else if ($stringman->string_exists($key, 'speval')) {
        $mislabels_map[$i] = get_string($key, 'speval');
    } else {
        $mislabels_map[$i] = $misdefaults[$i];
    }
}

// 5) Group students by Moodle group for this course
$grouped = []; // groupid => list rows
foreach ($enrolled_students as $uid => $u) {
	// Determine group for this user
	$usergroups = groups_get_user_groups($course->id, $uid);
	$groupid = 0;
	if (!empty($usergroups)) {
		foreach ($usergroups as $grouplist) {
			if (!empty($grouplist)) {
				$groupid = reset($grouplist);
				break;
			}
		}
	}

	// Final grade (if exists)
	$final = isset($grades[$uid]) ? (float)$grades[$uid]->finalgrade : 0.0;

	// Discrepancies
	$markdisc = ($final < 2.5); // rule provided
	$commentdisc = !empty($peerflags[$uid]['comment']);

	$misdisplay = '-';
    if (!empty($peerflags[$uid]['misbehaviour_categories'])) {
        $cats = array_unique($peerflags[$uid]['misbehaviour_categories']);
        // Remove "Normal" category (1) from display
        $cats = array_values(array_filter($cats, function($c) { return (int)$c !== 1; }));
        if (!empty($cats)) {
            $names = [];
            foreach ($cats as $cat) {
                $names[] = isset($mislabels_map[$cat]) ? $mislabels_map[$cat] : (string)$cat;
            }
            $misdisplay = implode(', ', $names);
        }
    }

    $row = [
        'name' => trim($u->firstname . ' ' . $u->lastname),
		'id' => $uid,
		'final' => format_float($final, 2),
		'markdisc' => $markdisc ? get_string('yes') : get_string('no'),
        'quickdisc' => !empty($peerflags[$uid]['quick']) ? get_string('yes') : get_string('no'),
		'commentdisc' => $commentdisc ? get_string('yes') : get_string('no'),
		'misbehave' => $misdisplay
	];

	if (!isset($grouped[$groupid])) {
		$grouped[$groupid] = [];
	}
	$grouped[$groupid][] = $row;
}

// 6) Render UI: expandable per group
if (!empty($grouped)) {
    $groupindex = 0;
    foreach ($grouped as $gid => $rows) {
        $gname = $gid ? groups_get_group_name($gid) : get_string('nogroup', 'speval');
        $detailsattrs = ['open' => 'open', 'class' => 'speval-group'];
        if ($groupindex > 0) {
            $detailsattrs['style'] = 'margin-top: 24px;';
        }
        echo html_writer::start_tag('details', $detailsattrs);
		echo html_writer::tag('summary', s('Group: ' . ($gname ?: $gid)));

		$table = new html_table();
        // Resolve quick submission label robustly across components
        $qslabel = 'Quick submission discrepancy';
        if ($stringman->string_exists('quicksubmissiondiscrepancy', 'mod_speval')) {
            $qslabel = get_string('quicksubmissiondiscrepancy', 'mod_speval');
        } else if ($stringman->string_exists('quicksubmissiondiscrepancy', 'speval')) {
            $qslabel = get_string('quicksubmissiondiscrepancy', 'speval');
        }

        $table->head = [
            get_string('name'),
            get_string('id', 'speval'),
            get_string('finalgrade', 'speval'),
            get_string('markdiscrepancy', 'speval'),
            $qslabel,
            get_string('commentdiscrepancy', 'speval'),
            get_string('misbehaviour', 'speval')
        ];

		foreach ($rows as $r) {
            $table->data[] = [
				s($r['name']),
				s($r['id']),
				s($r['final']),
				s($r['markdisc']),
                s($r['quickdisc']),
				s($r['commentdisc']),
				s($r['misbehave'])
			];
		}

		echo html_writer::table($table);
		echo html_writer::end_tag('details');
        $groupindex++;
	}
} else {
	echo $OUTPUT->notification(get_string('noresults', 'mod_speval'), 'notifyinfo');
}

// Output end
echo $OUTPUT->footer();
