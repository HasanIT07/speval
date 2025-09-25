<?php
function speval_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE: return MOD_ARCHETYPE_OTHER;
        case FEATURE_BACKUP_MOODLE2: return true;
        default: return null;
    }
}

function speval_add_instance(stdClass $speval, mod_speval_mod_form $mform = null) {
    global $DB;

    $speval->timemodified = time();

    // Use the database to insert the new record.
    $id = $DB->insert_record('speval', $speval);

    return $id;
}




function speval_update_instance(stdClass $speval, mod_speval_mod_form $mform = null) {
    global $DB;

    $speval->timemodified = time();
    $speval->id = $speval->instance;

    return $DB->update_record('speval', $speval);
}

function speval_delete_instance($id) {
    global $DB;

    $DB->delete_records('speval', array('id' => $id));
    $DB->delete_records('speval_eval', array('spevalid' => $id)); // You'll need to add a foreign key to the eval table for this to work.

    return true;
}



?>