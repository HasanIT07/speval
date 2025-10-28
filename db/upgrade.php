<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_speval_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

<<<<<<< HEAD
    if ($oldversion < 2025102700) {
=======
    if ($oldversion < 2025101501) {
>>>>>>> a5537d46769ff4ec46cbc417d07930ca7d693ef3

    // Savepoint reached.
        upgrade_mod_savepoint(true, 2025102700, 'speval');
    }

    return true;
}