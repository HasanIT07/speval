<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_speval_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025092203) {

        // Define table speval.
        $table = new xmldb_table('speval');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('intro', XMLDB_TYPE_TEXT, null, null, null, null);
            $table->add_field('introformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('criteria_count', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 3);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2025092203, 'speval');
    }

    return true;
}