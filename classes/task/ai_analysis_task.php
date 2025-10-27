<?php
namespace mod_speval\task;

defined('MOODLE_INTERNAL') || die();

class ai_analysis_task extends \core\task\adhoc_task {
    
    public function execute() {
        $data = $this->get_custom_data();
        $spevalid = $data['spevalid'];
        
        try {
            // Run AI analysis
            $results = \mod_speval\local\ai_service::analyze_evaluations($spevalid);
            
            // Log results
            mtrace("AI analysis completed for activity {$spevalid}. Processed " . count($results) . " results.");
            
            return true;
        } catch (Exception $e) {
            mtrace("AI analysis failed for activity {$spevalid}: " . $e->getMessage());
            return false;
        }
    }
}