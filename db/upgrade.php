<?php
require_once($CFG->libdir . '/dml/moodle_database.php');
defined('MOODLE_INTERNAL') || die();

function mod_speval_populate_default_criteria() {
    global $DB;

    $default_criteria = [
        'Contributed effectively to the group project',
        'Communicated clearly and on time with team members',
        'Completed assigned tasks with quality work',
        'Participated actively in meetings',
        'Showed initiative and creativity',
    ];

    foreach ($default_criteria as $questiontext) {
        // Use a simple SQL LIKE instead of TEXT comparison
        $sql = "SELECT id FROM {speval_criteria_bank} WHERE questiontext LIKE ?";
        $params = [$questiontext];
        $exists = $DB->record_exists_sql($sql, $params);

        if (!$exists) {
            $record = new stdClass();
            $record->questiontext = $questiontext;
            $DB->insert_record('speval_criteria_bank', $record);
        }
    }
}

function xmldb_speval_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads database manager.

    if ($oldversion < 2025100411) { // Use a new version number for this upgrade
        mod_speval_populate_default_criteria();
        upgrade_mod_savepoint(true, 2025100411, 'speval');
    }
}