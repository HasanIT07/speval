<?php
require_once('../../config.php');
require_login();

$id = required_param('id', PARAM_INT); // course module id
$table = required_param('table', PARAM_RAW); // allow underscores

$cm = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
global $DB;

// Fetch data
if ($table === 'speval_eval') {
    $records = $DB->get_records('speval_eval', ['activityid' => $cm->instance]);
} else if ($table === 'speval_grades') {
    $records = $DB->get_records('speval_grades', ['activityid' => $cm->instance]);
} else if ($table === 'speval_flag_individual') {
    $records = $DB->get_records('speval_flag_individual', ['activityid' => $cm->instance]);
} else {
    die('Invalid table specified.');
}

// CSV output headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$table.'_'.date('Ymd').'.csv"');

$out = fopen('php://output', 'w');

if ($records) {
    if ($table === 'speval_grades') {
        // CSV header for grades
        $header = ['Name', 'criteria1', 'criteria2', 'criteria3', 'criteria4', 'criteria5', 'finalgrade'];
        fputcsv($out, $header);

        // Fetch users
        $userids = array_column($records, 'userid');
        $users = $DB->get_records_list('user', 'id', $userids);

        foreach ($records as $rec) {
            $name = $users[$rec->userid]->firstname . ' ' . $users[$rec->userid]->lastname;
            $row = [
                $name,
                $rec->criteria1,
                $rec->criteria2,
                $rec->criteria3,
                $rec->criteria4,
                $rec->criteria5,
                $rec->finalgrade
            ];
            fputcsv($out, $row);
        }
    } else if ($table === 'speval_eval') {
        // CSV header for eval
        $header = ['Evaluator Name', 'Peer Name', 'criteria1', 'criteria2', 'criteria3', 'criteria4', 'criteria5', 'comment1', 'comment2', 'timecreated'];
        fputcsv($out, $header);

        // Fetch users for evaluator and peer
        $userids = [];
        foreach ($records as $rec) {
            $userids[] = $rec->userid;
            $userids[] = $rec->peerid;
        }
        $userids = array_unique($userids);
        $users = $DB->get_records_list('user', 'id', $userids);

        foreach ($records as $rec) {
            $evaluator = $users[$rec->userid]->firstname . ' ' . $users[$rec->userid]->lastname;
            $peer = $users[$rec->peerid]->firstname . ' ' . $users[$rec->peerid]->lastname;
            $row = [
                $evaluator,
                $peer,
                $rec->criteria1,
                $rec->criteria2,
                $rec->criteria3,
                $rec->criteria4,
                $rec->criteria5,
                $rec->comment1,
                $rec->comment2,
                date('Y-m-d H:i:s', $rec->timecreated)
            ];
            fputcsv($out, $row);
        }
    } else if ($table === 'speval_flag_individual') {
        // CSV header for individual flags
        $header = ['Evaluator Name', 'Peer Name', 'activityid', 'grouping', 'groupid', 'commentdiscrepancy', 'markdiscrepancy', 'quicksubmissiondiscrepancy', 'misbehaviorcategory', 'timecreated'];
        fputcsv($out, $header);

        // Fetch users for evaluator and peer
        $userids = [];
        foreach ($records as $rec) {
            $userids[] = $rec->userid;
            $userids[] = $rec->peerid;
        }
        $userids = array_unique($userids);
        $users = $DB->get_records_list('user', 'id', $userids);

        foreach ($records as $rec) {
            $evaluator = isset($users[$rec->userid]) ? ($users[$rec->userid]->firstname . ' ' . $users[$rec->userid]->lastname) : $rec->userid;
            $peer = isset($users[$rec->peerid]) ? ($users[$rec->peerid]->firstname . ' ' . $users[$rec->peerid]->lastname) : $rec->peerid;
            $timeformatted = (isset($rec->timecreated) && !empty($rec->timecreated)) ? date('Y-m-d H:i:s', (int)$rec->timecreated) : '';
            $row = [
                $evaluator,
                $peer,
                $rec->activityid,
                (int)$rec->grouping,
                (int)$rec->groupid,
                (int)$rec->commentdiscrepancy,
                (int)$rec->markdiscrepancy,
                (int)$rec->quicksubmissiondiscrepancy,
                (int)$rec->misbehaviorcategory,
                $timeformatted
            ];
            fputcsv($out, $row);
        }
    }
}

fclose($out);
exit;
