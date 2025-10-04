<?php
// Add a navigation tab for 'Add Question' in the activity navigation
function speval_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE: return MOD_ARCHETYPE_OTHER;
        case FEATURE_BACKUP_MOODLE2: return true;
        default: return null;
    }
}

function speval_delete_instance($id) {
    die("SPEVAL DELETE INSTANCE CALLED with id=$id");
    global $DB;

    $DB->delete_records('speval', array('id' => $id));

    // debugging("Deleting speval instance: $id", DEBUG_DEVELOPER);
    error_log("Deleting speval instance: $id");

    // $DB->delete_records('speval_eval', array('spevalid' => $id)); // You'll need to add a foreign key to the eval table for this to work.

    return true;
}


function speval_add_instance(stdClass $speval, mod_speval_mod_form $mform = null) {
    global $DB;

    $speval->timemodified = time();

    if (empty($speval->linkedassign)) {
        $speval->linkedassign = null;
    } else{
        $speval->grouping = null;
    }

    // if (!isset($speval->visible)) {
    //     $speval->visible = 1;
    // }
    // $speval->visible = 1;

    $id = $DB->insert_record('speval', $speval);
    return $id;
}





function speval_update_instance(stdClass $speval, mod_speval_mod_form $mform = null) {
    global $DB;

    $speval->timemodified = time();
    $speval->id = $speval->instance;
    
    if (empty($speval->linkedassign)) {
        $speval->linkedassign = null;
    } else{
        $speval->grouping = null;
    }

    return $DB->update_record('speval', $speval);
}

function speval_extend_settings_navigation(settings_navigation $settings, navigation_node $spevalnode) {
    global $PAGE;

    if (!empty($spevalnode) && has_capability('mod/speval:view', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/speval/results.php', ['id' => $PAGE->cm->id]);
        $spevalnode->add(
            get_string('results', 'speval'), // uses lang/en/speval.php
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'spevalresults'
        );
    }
}



?>