<?php
// Add a navigation tab for 'Add Question' in the activity navigation
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

    if (empty($speval->linkedassign)) {
        $speval->linkedassign = null;
    } else{
        $speval->grouping = null;
    }

    $id = $DB->insert_record('speval', $speval);
    return $id;
}


function speval_delete_instance($id) {
    die("SPEVAL DELETE INSTANCE CALLED with id=$id");
    global $DB;

    $DB->delete_records('speval', ['id' => $id]);
    $DB->delete_records('speval_eval', ['activityid' => $id]);
    $DB->delete_records('speval_grades', ['activityid' => $id]);
    $DB->delete_records('speval_flag', ['activityid' => $id]);

    return true;
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
    global $USER;


    // Add a 'Results' tab to the activity navigation if the user has view capability
    if (!empty($spevalnode) && has_capability('mod/speval:addinstance', $PAGE->cm->context)) {              // Ensure only teachers can see this page (Stuednts should not see tabs. There are no moodle activity that allow this).
        $url = new moodle_url('/mod/speval/results.php', ['id' => $PAGE->cm->id]);                          // Create the URL to the results page.
        $spevalnode->add(                                                                                   // Add the 'Results' tab to the activity navigation.
            get_string('results', 'speval'),                                                                // uses lang/en/speval.php                                         
            $url,                                                                                           // Link to the results page
            navigation_node::TYPE_SETTING,                                                                   
            null,
            'spevalresults'                                                                                 // Unique key for this node
        );
    }


    // Add a 'Criteria' tab visible to teachers/managers.
    if (has_capability('mod/speval:addinstance', context_course::instance($PAGE->course->id))) {
        $url = new moodle_url('/mod/speval/criteria.php', ['id' => $PAGE->cm->id]);
        $spevalnode->add(
            get_string('criteria', 'mod_speval'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'spevalcriteria'
        );
    }


    // Add a new 'Progress' tab visible to teachers/managers.
    if (has_capability('mod/speval:addinstance', context_course::instance($PAGE->course->id))) {
        $url = new moodle_url('/mod/speval/progress.php', ['id' => $PAGE->cm->id]);
        $spevalnode->add(
            get_string('progress', 'mod_speval'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'spevalprogress'
        );
    }


    // Add a new 'AI Analysis' tab visible to teachers/managers.
    if (has_capability('mod/speval:addinstance', context_course::instance($PAGE->course->id))) {
        $url = new moodle_url('/mod/speval/ai_analysis.php', ['id' => $PAGE->cm->id]);
        $spevalnode->add(
            'AI Analysis',
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'spevalai'
        );
    }
}
