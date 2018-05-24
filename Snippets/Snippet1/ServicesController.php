<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\Service;
use app\models\MyDoc;

class ServicesController extends Controller {
	/**
     * @inheritdoc
     */
    public function behaviors() {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['index',],
                'rules' => [
                    [
						'actions' => ['index'],
						'allow' => true,
					],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions() {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }
    
	/* 
	 * API to start/stop service and create lead
	 * @rule - Patient must be registered and not opted out
	 * @rule - if opted out create new lead in case
	 * @rule - if not on platform .. then create a lead with source as my_doc
	 * 
	 */
	public function actionIndex() {
		// check for phone
		if (!isset($_GET['mobile']) || empty($_GET['mobile'])) {
			echo "Please provide phone.";
			exit;
		}
		
		// check for code
		if (!isset($_GET['services']) || empty($_GET['services'])) {
			echo "Please provide code.";
			exit;
		}
		
		// check for
		if (!isset($_GET['re']) || empty($_GET['re'])) {
			echo 'Please provice service type.';
			exit;
		}
		
		if ($_GET['re'] != 'start') {
			if ($_GET['re'] != 'stop') { 
				echo 'Please provice valid service type.';
				exit;
			}
		}
		
		// assign variables
		$phone = $_GET['mobile'];
		$action = $_GET['re'];
		$keyword = $_GET['services'];
		
		// check if keyword is a valid keyword and exist in system
		$models = new Service();
		$program_data = $models->checkKeyword($keyword);
		if (!is_array($program_data) || empty($program_data)) {
			echo "There is no active program associated with the provided keyword.";
			exit;
		}
		
		// check patient
		$patient_data = $models->checkUniquePatient($phone);
		
		// if service type is stop then check if phone number exits
		if ($action == 'stop') {
			
			if (empty($patient_data)) {
				echo 'Patient not found.';
				exit;
				
			} else {
				
				// check if patient is part of the program associated with the provided keyword
				$patient_program_data = $models->getPatientProgramDetails($patient_data['patient_id'], $program_data['program_id']);
				
				if (!empty($patient_program_data)) {
					
					// check if patient is enrolled into the service of program
					$check_service = $models->checkPatientService(array('patient_id' => $patient_data['patient_id'], 'program_id' => $program_data['program_id'], 'intervention_type_id' => $program_data['intervention_type_id'], 'keyword' => $keyword));
					
					if (empty($check_service)) {
						echo 'Patient has not enrolled into the service.';
						exit;
						
					} else {
						$delete_services = $models->deletePatientProgramServices(array('patient_id' => $patient_data['patient_id'], 'program_id' => $program_data['program_id'], 'intervention_type_id' => $program_data['intervention_type_id'], 'keyword' => $keyword));
						if ($delete_services) {
							echo "Service has been stopped.";
							exit;
						}
					}
					
				} else {
					
					echo "Patient not enrolled into the program associated with the provided keyword.";
					exit;
				}
			}
		} else if ($action == 'start') {
			
			if (empty($patient_data)) {
				
				// create a lead .. create a patient for the phone number provided
				$patient_id = $models->createPatient($phone);
				$patient_data['patient_id'] = $patient_id;
				// check if patient is already enrolled
				$enrollment_data = $models->checkPatientProgram($patient_data['patient_id'], $program_data['program_id']);
				
				if (empty($enrollment_data)) {
					// enroll the patient into the program
					$enrollment_id = $models->enrollPatient($patient_data['patient_id'], $program_data['program_id'], 3, $program_data['show_patient_form'], $program_data['intervention_duration']);
					if ($enrollment_id > 0) {
						echo "<br />";
						echo "Patient enrolled successfully.";
						exit;
					}
					
				} else {
					
					// patient already enrolled into the program
					echo "<br />";
					echo "Patient is already enrolled into the program.";
					exit;
				}
				
			} else {
				
				// check patient status for the program
				$patient_program_data = $models->getPatientProgramDetails($patient_data['patient_id'], $program_data['program_id']);
				
				if (!empty($patient_program_data)) {
					
					// check if patient is enrolled into the service of program
					$check_service = $models->checkPatientService(array('patient_id' => $patient_data['patient_id'], 'program_id' => $program_data['program_id'], 'intervention_type_id' => $program_data['intervention_type_id'], 'keyword' => $keyword));
					
					// check if patient has been opted out, but registration was complete, type is callback
					if ($patient_program_data['registration_complete'] == 1) {
						
						// check for type
						if ($patient_program_data['type'] == 'registration') {
							
							// check for service
							if (!empty($check_service)) {
								echo "Service already started.";
								exit;
							
							} else {
								// enroll patient into the service
								$enroll_service_check = $models->enrollPatientToServices($patient_data['patient_id'], $program_data['program_id'], $program_data['intervention_type_id'], $keyword);
								if ($enroll_service_check) {
									echo "Service has been started.";
									exit;
								}
							}
						
						} else if ($patient_program_data['type'] == 'callback') {
							
							if ($patient_program_data['reject_id'] == 0) {
								
								// check for service
								if (!empty($check_service)) {
									echo "Service already started.";
									exit;
								
								} else {
									
									// enroll patient into the service
									$enroll_service_check = $models->enrollPatientToServices($patient_data['patient_id'], $program_data['program_id'], $program_data['intervention_type_id'], $keyword);
									if ($enroll_service_check) {
										echo "Service has been started.";
										exit;
									}
								}
							
							} else {
								
								// update the data .. delete services, program details data and create a fresh lead
								$update_patient_data = $models->updatePatientDataAndCreateLead($patient_data['patient_id'], $program_data['program_id'], 3, $program_data['show_patient_form'], $program_data['intervention_duration']);
								if ($update_patient_data) {
									echo "Patient successfully enrolled into the program associated with the provided keyword.";
									exit;
								}
							}
							
						}
					
					} else {
						echo "Patient already enrolled into the program. But registration is pending.";
						exit;
					}
				}
				
			}
		}
	}
}
