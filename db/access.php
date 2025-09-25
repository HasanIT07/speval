<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
	'mod/speval:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/course:manageactivities'
    ),

    'mod/speval:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => 'CAP_ALLOW',
            'teacher' => 'CAP_ALLOW',
	    'manager' => CAP_ALLOW
        ],
    ],
    'mod/speval:submit' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => 'CAP_ALLOW',
        ],
    ],
];