<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_speval_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads database manager.

    if ($oldversion < 2025100403) { // Use a new version number for this upgrade

        upgrade_mod_savepoint(true, 2025100403, 'speval');
    }
}