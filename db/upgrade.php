<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_speval_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads database manager.

    if ($oldversion < 2025100309) { // Use a new version number for this upgrade

        // Define field grouping to be added to speval.
        $table = new xmldb_table('speval');
        $field = new xmldb_field('grouping', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'linkedassign');

        // Conditionally launch add field grouping.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Speval savepoint reached.
        upgrade_mod_savepoint(true, 2025100309, 'speval');
    }
}