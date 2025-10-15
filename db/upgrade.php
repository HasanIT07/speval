<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_speval_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025101406) {

    // Define table speval_openquestion to be created.
    $table = new xmldb_table('speval_openquestion');

    // Add fields.
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('spevalid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
    $table->add_field('questiontext', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('questionbankid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

    // Add keys.
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('spevalid_fk', XMLDB_KEY_FOREIGN, ['spevalid'], 'speval', ['id']);
    $table->add_key('questionbankid_fk', XMLDB_KEY_FOREIGN, ['questionbankid'], 'speval_criteria_bank', ['id']);

    // Conditionally create the table.
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }
        upgrade_mod_savepoint(true, 2025101406, 'speval');
    }

    return true;
}