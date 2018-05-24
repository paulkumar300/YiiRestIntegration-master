<?php
namespace app\models;
use yii;
use yii\base\Model;
use yii\db\Query;
use yii\db\QueryTrait;
use yii\data\Pagination;
use yii\db\Command;
use app\models\User;

class Service extends Model {
    
    // check service keyword
    public function checkKeyword($keyword) {
        $current_time = strtotime('today midnight');
        $result = Yii::$app->db->createCommand("
            SELECT p.id AS program_id, p.show_patient_form, p.program_duration, p.intervention_duration, p.start_date, p.end_date, service.program_id, service.intervention_type_id, service.keyword, service.optin_user, service.optin_password, service.sender_id
            FROM 007_psconnect_programs p
            INNER JOIN 029_psconnect_program_intervention service ON p.id = service.program_id
            LEFT JOIN 022_psconnect_program_recruitment_ps_id ps_id ON p.id = ps_id.program_id
            LEFT JOIN 023_psconnect_program_recruitment_my_doc my_doc ON p.id = my_doc.program_id
            LEFT JOIN 024_psconnect_program_recruitment_missed_call missed_call ON p.id = missed_call.program_id
            WHERE service.keyword = " . "'" . $keyword . "'" . "
            AND p.status = 1
            AND p.deleted = 0
            AND p.start_date <= " . $current_time . "
            AND p.end_date >= " . $current_time . "
            AND (ps_id.acknowledgement_ticket_verified = 1 OR my_doc.acknowledgement_ticket_verified = 1 OR missed_call.acknowledgement_ticket_verified = 1) ")->queryOne();
        return $result;
    }
    
    // check for unique patient
    public function checkUniquePatient($phone) {
        $result = Yii::$app->db->createCommand("SELECT id AS patient_id FROM 009_psconnect_patients WHERE phone = " . "'" .  $phone . "'" . " AND deleted = 0")->queryOne();
        return $result;
    }
    
    // check if patient is enrolled into the service
    public function checkPatientService($data) {
        $result = Yii::$app->db->createCommand("SELECT id AS service_enrollment_id FROM 011_psconnect_patient_requested_services 
            WHERE patient_id = " .  $data['patient_id'] . " 
            AND program_id = " . $data['program_id'] . "
            AND intervention_type_id =  " . $data['intervention_type_id'] . "
            AND keyword = " . "'" .  $data['keyword'] . "'" . "
            AND deleted = 0")->queryOne();
        return $result;
    }
    
    // delete patient services
    public function deletePatientProgramServices($where = array()) {
        Yii::$app->db->createCommand()->delete('011_psconnect_patient_requested_services', $where)->execute();
        return true;
    }
    
    // enroll patient to program services
    public function enrollPatientToServices($patient_id, $program_id, $intervention_type_id, $keyword) {
        $query = new Query;
        $query->select('id')
            ->from('011_psconnect_patient_requested_services')
            ->where(['patient_id' => $patient_id, 'program_id' => $program_id, 'intervention_type_id' => $intervention_type_id, 'keyword' => $keyword]);
        $rows = $query->one();
        if (empty($rows)) {
            Yii::$app->db->createCommand()->insert('011_psconnect_patient_requested_services', ['patient_id' => $patient_id, 'program_id' => $program_id, 'intervention_type_id' => $intervention_type_id, 'keyword' => $keyword, 'acknowledgement' => 1])->execute();
        }
        return true;
    }
    
    // get patient program details
    public function getPatientProgramDetails($patient_id, $program_id) {
        $result = Yii::$app->db->createCommand("SELECT * FROM 010_psconnect_patient_programs
            WHERE patient_id = " . $patient_id . "
            AND program_id = " . $program_id . "
            AND deleted = 0")->queryOne();
        return $result;
    }
    
    // create patient
    public function createPatient($phone) {
        Yii::$app->db->createCommand("INSERT INTO 009_psconnect_patients (phone, updated_at) VALUES (" . $phone . ", " . time() . ")")->execute();
        $patient_id = Yii::$app->db->getLastInsertID();
        return $patient_id;
    }
    
    // delete patient data in case patient has opted out and has initiated a service start request
    public function updatePatientDataAndCreateLead($patient_id, $program_id, $recruitment_channel_id, $show_patient_form, $intervention_duration) {
        // delete any service associated with the program for patient
        Yii::$app->db->createCommand()->delete('011_psconnect_patient_requested_services', array('patient_id' => $patient_id, 'program_id' => $program_id))->execute();
        
        // delete patient field data if any
        Yii::$app->db->createCommand()->delete('012_psconnect_patient_program_details', array('patient_id' => $patient_id, 'program_id' => $program_id))->execute();
        
        // delete any message mapping
        Yii::$app->db->createCommand()->delete('051_psconnect_messages_mapped', array('patient_id' => $patient_id, 'program_id' => $program_id))->execute();
        
        // delete any details associated with the program so that new lead can be created
        Yii::$app->db->createCommand()->delete('010_psconnect_patient_programs', array('patient_id' => $patient_id, 'program_id' => $program_id))->execute();
        
        // create a fresh lead
        $enrollment_id = $this->enrollPatient($patient_id, $program_id, $recruitment_channel_id, $show_patient_form, $intervention_duration);
        return $enrollment_id;
    }
    
    /*****************************
     * FOR START/STOP CRON SERVICE
     * ***************************
     */
     
    // get all patients having service acknowledge as 0
    public function getAllServiceStartPatient() {
        $result = Yii::$app->db->createCommand("SELECT service.patient_id, service.program_id, service.intervention_type_id, service.keyword, intervention.wid, intervention.optin_user, intervention.optin_password, intervention.optin_url, patient.phone
        FROM 011_psconnect_patient_requested_services service
        INNER JOIN 029_psconnect_program_intervention intervention ON service.program_id = intervention.program_id AND service.intervention_type_id = intervention.intervention_type_id AND service.keyword = intervention.keyword
        INNER JOIN 009_psconnect_patients patient ON service.patient_id = patient.id
        WHERE service.acknowledgement = 0
        ")->queryAll();
        return $result;
    }
    
    // get all patients having service acknowledge as 0
    public function getAllServiceStopPatient() {
        $result = Yii::$app->db->createCommand("SELECT service.patient_id, service.program_id, service.intervention_type_id, service.keyword, intervention.wid, intervention.optin_user, intervention.optin_password, intervention.optout_url, patient.phone
        FROM 011_psconnect_patient_requested_services service
        INNER JOIN 029_psconnect_program_intervention intervention ON service.program_id = intervention.program_id AND service.intervention_type_id = intervention.intervention_type_id AND service.keyword = intervention.keyword
        INNER JOIN 009_psconnect_patients patient ON service.patient_id = patient.id
        WHERE service.acknowledgement = 2
        ")->queryAll();
        return $result;
    }
    
    // update acknowledgement for service response
    public function updateServiceAcknowledgement($where, $action) {
        if ($action == 'update') {
            Yii::$app->db->createCommand()->update('011_psconnect_patient_requested_services', array('acknowledgement' => 1), $where)->execute();
            
        } else if ($action == 'delete') {
            Yii::$app->db->createCommand()->delete('011_psconnect_patient_requested_services', $where)->execute();
        }
        return true;
    }
    
    // check if patient is already enrolled
    public function checkPatientProgram($patient_id, $program_id) {
        $result = Yii::$app->db->createCommand("SELECT id AS enrollment_id FROM 010_psconnect_patient_programs WHERE patient_id = " . $patient_id . " AND program_id = " . $program_id)->queryOne();
        return $result;
    }
    
    // enroll patient into the program 
    public function enrollPatient($patient_id, $program_id, $recruitment_channel_id, $show_patient_form, $intervention_duration) {
        // insert data array
        $insert_data = array(
            'patient_id' => $patient_id,
            'program_id' => $program_id,
            'recruitment_channel_id' => $recruitment_channel_id,
            'source' => 'my_doc',
            'type' => 'registration',
            'language_id' => 1,
            'arrived_at' => time(),
        );
        
        // check if program has show_patient_form value as 1 ... if 0 .. then activate it
        if ($show_patient_form == 0) {
            $insert_data['registration_complete'] = 1;
            $insert_data['registration_complete_time'] = time();
            $insert_data['intervention_start_time'] = strtotime('today midnight');
            $insert_data['intervention_stop_time'] = strtotime('+ ' . $intervention_duration . ' month', $insert_data['intervention_start_time']);
        }
        
        // insert the registration data and return enrollment id
        Yii::$app->db->createCommand()->insert('010_psconnect_patient_programs', $insert_data)->execute();
        $enrollment_id = Yii::$app->db->getLastInsertID();
        return $enrollment_id;
    }
}
