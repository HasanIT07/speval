<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_speval_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025101501) {
    global $DB;
    $dbman = $DB->get_manager();

    // Define table and field.
    $table = new xmldb_table('speval_criteria_bank');
    $field = new xmldb_field('unitid');

    // // Drop the foreign key first (if it exists).
    // $key = new xmldb_key('fk_unitid', XMLDB_KEY_FOREIGN, ['unitid'], 'units', ['id']);
    // if ($dbman->key_exists($table, $key)) {
    //     $dbman->drop_key($table, $key);
    // }

    // Drop the field.
    if ($dbman->field_exists($table, $field)) {
        $dbman->drop_field($table, $field);
    }

    // Savepoint reached.
        upgrade_mod_savepoint(true, 2025101501, 'speval');
    }

    return true;
}