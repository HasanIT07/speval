<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_speval_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads database manager.

    if ($oldversion < 2025092900) {
        // Define field unitid to be added to speval_eval.
        $table = new xmldb_table('speval_eval');
        $field = new xmldb_field('unitid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');

        // Conditionally launch add field unitid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Speval savepoint reached.
        upgrade_mod_savepoint(true, 2025092900, 'speval');
    }

    return true;
}