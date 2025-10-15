<?php
namespace mod_speval\task;

defined('MOODLE_INTERNAL') || die();

class ai_analysis_task extends \core\task\adhoc_task {
    
    public function execute() {
        $data = $this->get_custom_data();
        $activityid = $data['activityid'];
        
        try {
            // Run AI analysis
            $results = \mod_speval\local\ai_service::analyze_evaluations($activityid);
            
            // Log results
            mtrace("AI analysis completed for activity {$activityid}. Processed " . count($results) . " results.");
            
            return true;
        } catch (Exception $e) {
            mtrace("AI analysis failed for activity {$activityid}: " . $e->getMessage());
            return false;
        }
    }
}