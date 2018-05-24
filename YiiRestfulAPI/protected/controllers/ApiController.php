<?php

date_default_timezone_set('US/Eastern');
class ApiController extends Controller {
    // Members
    /**
     * Application Key, which has to be pass everytime API is called to authenticate valid source.
     */
    Const APPLICATION_KEY = 'f25a2fc72690b780b2a14e140ef6a9e0';

    /**
     * Default response format
     * either 'json' or 'xml'
     */
    private $format = 'json';

    /**
     * @return array action filters
     */
    public function filters() {
        return array();
    }

    // Action for User Login.
    public function actionUser_login() {
        if(!isset($_GET['key'])){
            $this->_sendResponse(401, CJSON::encode(['message' => 'Application Key is not passed.', 'error' => true]));
        } else {
            $key = $_GET['key'];
            if($key !== ApiController::APPLICATION_KEY){
                $this->_sendResponse(401, CJSON::encode(['message' => 'Invalid application key. Please contact administrator.', 'error' => true]), 'application/json');
            } else {
                $errorKey = [];
                if(!isset($_GET['username'])){
                    $errorKey[] = '{username}';
                }
                if(!isset($_GET['password'])){
                    $errorKey[] = '{password}';
                }                
                if(count($errorKey)){
                    $this->_sendResponse(401, CJSON::encode(['message' => implode(", ", $errorKey).' not passed.', 'error' => true]), 'application/json');
                } else {
                    $model = User::model()->findByAttributes(['user_name' => $_GET['username'], 'password' => md5($_GET['password'])]);
                    if($model == NULL){
                        $this->_sendResponse(403, CJSON::encode(['message' => 'Invalid username or password.', 'error' => true]), 'application/json');
                    } elseif(!$model->status) {
                        $this->_sendResponse(403, CJSON::encode(['message' => 'user {'.$model->user_name.'} is not active. Please contact administrator.', 'error' => true]), 'application/json');
                    } else {
                        $this->_sendResponse(200, CJSON::encode(['message' => '', 'error' => false, 'data' => ['user_id' => $model->id]]), 'application/json');
                    }
                }
            }
        }
        
    }

    /**
     * Action for get user details.
     */
   public function actionGet_user(){
        if(!isset($_GET['key'])){
            $this->_sendResponse(401, CJSON::encode(['message' => 'Application Key is not passed.', 'error' => true]));
        } else {
            $key = $_GET['key'];
            if($key !== ApiController::APPLICATION_KEY){
                $this->_sendResponse(401, CJSON::encode(['message' => 'Invalid application key. Please contact administrator.', 'error' => true]), 'application/json');
            } elseif(!isset($_GET['id'])){
                $this->_sendResponse(401, CJSON::encode(['message' => '{id} is not passed.', 'error' => true]), 'application/json');
            } else {
                $id = $_GET['id'];
                $model = User::model()->findByPk($id);
                if($model == NULL){
                    $this->_sendResponse(403, CJSON::encode(['message' => 'Invalid {id} passed.', 'error' => true]), 'application/json');
                } elseif(!$model->status) {
                    $this->_sendResponse(403, CJSON::encode(['message' => 'user {'.$model->user_name.'} is not active. Please contact administrator.', 'error' => true]), 'application/json');
                } else{
                    $this->_sendResponse(200, CJSON::encode(['message' => '', 'error' => false, 'data' => $model->attributes]), 'application/json');
                }
            }
        }
   }
    /**
     * Action for create user.
     */
   public function actionCreate_user(){
        if(!isset($_REQUEST['key'])){
            $this->_sendResponse(401, CJSON::encode(['message' => 'Application Key is not passed.', 'error' => true]));
        } else {
            $key = $_REQUEST['key'];
            $errorField = [];
            if($key !== ApiController::APPLICATION_KEY) {
                $this->_sendResponse(401, CJSON::encode(['message' => 'Invalid application key. Please contact administrator.', 'error' => true]), 'application/json');
            } else {
                if(!isset($_REQUEST['user_name'])){
                    $errorField[] = '{user_name}';
                }
                if(!isset($_REQUEST['password'])){
                    $errorField[] = '{password}';
                }
                if(!isset($_REQUEST['status'])){
                    $errorField[] = '{status}';
                }
                if(!isset($_REQUEST['first_name'])){
                    $errorField[] = '{first_name}';
                }
                if(!isset($_REQUEST['last_name'])){
                    $errorField[] = '{last_name}';
                }
                if(count($errorField)){
                    $this->_sendResponse(401, CJSON::encode(['message' => implode(", ", $errorField).' not passed.', 'error' => true]), 'application/json');
                } else {
                    $model = New User; 
                    $model->attributes = $_REQUEST;
                    $model->password = md5($model->password);
                    if(User::model()->findByAttributes(['user_name' => $model->user_name]) != NULL){
                        $this->_sendResponse(403, CJSON::encode(['message' => 'username {'.$model->user_name.'} is already taken. Please choose different username.', 'error' => true]), 'application/json');
                    } else {
                        $model->created_at = date('Y-m-d H:i:s');
                        if($model->save()){
                            $this->_sendResponse(200, CJSON::encode(['message' => 'User {'.$model->user_name.'} created successfully.', 'error' => false]), 'application/json');
                        }
                    }
                }
            }
        }
   }
    /**
     * Action for delete user.
     */
   public function actionDelete_user(){
        $json = file_get_contents('php://input');
        $vars = CJSON::decode($json,true);
        if(!isset($vars['key'])){
            $this->_sendResponse(401, CJSON::encode(['message' => 'Application Key is not passed.', 'error' => true]));
        } else {
            $key = $vars['key'];
            if($key !== ApiController::APPLICATION_KEY){
                $this->_sendResponse(401, CJSON::encode(['message' => 'Invalid application key. Please contact administrator.', 'error' => true]), 'application/json');
            } elseif(!isset($vars['id'])){
                $this->_sendResponse(401, CJSON::encode(['message' => '{id} is not passed.', 'error' => true]), 'application/json');
            } else {
                $id = $vars['id'];
                $model = User::model()->findByPk($id);
                if($model == NULL){
                    $this->_sendResponse(403, CJSON::encode(['message' => 'Invalid {id} passed.', 'error' => true]), 'application/json');
                } else{
                    $username = $model->user_name;
                    if($model->delete()){
                        $this->_sendResponse(200, CJSON::encode(['message' => 'username {'.$username.'} has been deleted successfully.', 'error' => false]), 'application/json');
                    }
                }
            }
        }
   }
    private function _sendResponse($status = 200, $body = '', $content_type = 'text/html') {
        // set the status
        $status_header = 'HTTP/1.1 ' . $status . ' ' . $this->_getStatusCodeMessage($status);
        header($status_header);
        // and the content type
        header('Content-type: ' . $content_type);

        // pages with body are easy
        if ($body != '') {
            // send the body
            echo $body;
        }
        // we need to create the body if none is passed
        else {
            // create some body messages
            $message = '';

            // this is purely optional, but makes the pages a little nicer to read
            // for your users.  Since you won't likely send a lot of different status codes,
            // this also shouldn't be too ponderous to maintain
            switch ($status) {
                case 401:
                    $message = 'You must be authorized to view this page.';
                    break;
                case 404:
                    $message = 'The requested URL ' . $_SERVER['REQUEST_URI'] . ' was not found.';
                    break;
                case 500:
                    $message = 'The server encountered an error processing your request.';
                    break;
                case 501:
                    $message = 'The requested method is not implemented.';
                    break;
            }

            // servers don't always have a signature turned on 
            // (this is an apache directive "ServerSignature On")
            $signature = ($_SERVER['SERVER_SIGNATURE'] == '') ? $_SERVER['SERVER_SOFTWARE'] . ' Server at ' . $_SERVER['SERVER_NAME'] . ' Port ' . $_SERVER['SERVER_PORT'] : $_SERVER['SERVER_SIGNATURE'];

            // this should be templated in a real-world solution
            $body = '
                <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
                <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
                    <title>' . $status . ' ' . $this->_getStatusCodeMessage($status) . '</title>
                </head>
                <body>
                    <h1>' . $this->_getStatusCodeMessage($status) . '</h1>
                    <p>' . $message . '</p>
                    <hr />
                    <address>' . $signature . '</address>
                </body>
                </html>';

            echo $body;
        }
        Yii::app()->end();
    }

    private function _getStatusCodeMessage($status) {
        // these could be stored in a .ini file and loaded
        // via parse_ini_file()... however, this will suffice
        // for an example
        $codes = Array(
            200 => 'OK',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
        );
        return (isset($codes[$status])) ? $codes[$status] : '';
    }

}
