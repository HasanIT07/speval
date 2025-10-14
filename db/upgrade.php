<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_speval_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025101405) {

    // Define table
    $table = new xmldb_table('speval_criteria_bank');

    // Define field to be dropped
    $field = new xmldb_field('curseid');

    // Conditionally drop the field if it exists
    if ($dbman->field_exists($table, $field)) {
        $dbman->drop_field($table, $field);
    }

        upgrade_mod_savepoint(true, 2025101405, 'speval');
    }

    return true;
}