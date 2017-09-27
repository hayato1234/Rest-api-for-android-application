<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$user_id = NULL;

require '../libs/vendor/autoload.php';
require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
include_once '../include/Config.php';
$app = new \Slim\App;

$mw = function ($request, $response, $next) {
    $newResponse = authenticate($response);
    $isValid = $newResponse->getStatusCode();
    $response = $next($request, $newResponse);
    return $response;
};

//register users
$app->post('/register', function (Request $request, Response $response) {
    $response = verifyRequiredParams(array('name', 'email', 'password'),$response);
    if($response->getStatusCode() == CODE_SUCCESS){
        $name = $request->getParam('name');
        $email = $request->getParam('email');
        $password = $request->getParam('password');
    
        $response = validateEmail($email,$response);
        if($response->getStatusCode()!=400){
            $db = new DbHandler();
            $result = array();
            $res = $db->createUser($name, $email, $password);
            if ($res == USER_CREATED_SUCCESSFULLY) {
                $result["error"] = false;
                $result["message"] = "Your account has been successfuly created";
                $response = $response->withJson($result)->withStatus(CODE_CREATED);
            } else if ($res == USER_CREATE_FAILED) {
                $result["error"] = true;
                $result["message"] = "Account creation failed";
                $response = $response->withJson($result)->withStatus(CODE_SERVER_ERROR);
            } else if ($res == USER_ALREADY_EXISTED) {
                $result["error"] = true;
                $result["message"] = "An account with this email already exists";
                $response = $response->withJson($result)->withStatus(CODE_CONFLICT);
            }
        }
    }
    return $response;        
});

//login user and return api
$app->post('/login', function(Request $request, Response $response) {
    $response =verifyRequiredParams(array('email', 'password'),$response);
    if($response->getStatusCode() == CODE_SUCCESS){
        $email = $request->getParam('email');
        $password = $request->getParam('password');
    
        $db = new DbHandler();
        if ($db->checkLogin($email, $password)) {
            $user = $db->getUserByEmail($email);
            $response = $response->withJson($user)->withStatus(CODE_SUCCESS);
        } else {
            $result = array();
            $result["error"]=false;
            $result["message"]="email/password wrong";
            $response = $response->withJson($result)->withStatus(CODE_UNAUTHORIZED); 
        }
    }
    return $response;
});

//create a task
$app->post('/tasks', function(Request $request, Response $response) {
    $response = verifyRequiredParams(array('task'),$response);
    if($response->getStatusCode()==200){
        $task = $request->getParam('task');
        global $user_id;
        $db = new DbHandler();
    
        $task_id = $db->createTask($user_id, $task);
        $result = array();
        if ($task_id != NULL) {
            $result["error"] = false;
            $result["message"] = "the task '$task' is created with the user  '$user_id'";
            $result["task_id"] = $task_id;
            $response = $response->withJson($result)->withStatus(CODE_CREATED);
        } else {
            $result["error"] = true;
            $result["message"] = 'failed to save task';
            $response = $response->withJson($result)->withStatus(CODE_SERVER_ERROR);
        }
    }
    return $response;
})->add($mw);

//all task from a user
$app->get('/tasks', function(Request $request, Response $response) {
    global $user_id;
    $db = new DbHandler();

    $result = $db->getAllUserTasks($user_id);
    if($result!=null){
        $response = $response->withJson($result)->withStatus(CODE_SUCCESS);
    }else {
        $error=array();
        $error["error"] = true;
        $error["message"] = 'failed to get requested data from server';
        $response = $response->withJson($error)->withStatus(CODE_SERVER_ERROR);
    }
    return $response;
})->add($mw);

// single task
$app->get('/tasks/{id}', function(Request $request, Response $response) {
    global $user_id;
    $db = new DbHandler();
    $task_id = $request->getAttribute('id');

    $result = $db->getTask($task_id, $user_id);
    // $result = NULL;
    if ($result != NULL) {
        $task = $result[0];
        $response = $response->withJson($result[0])->withStatus(CODE_SUCCESS);
    } else {
        $error=array();
        $error["error"] = true;
        $error["message"] = 'Sorry, you are not authorized to access this data';
        $response = $response->withJson($error)->withStatus(CODE_FORBIDDEN);
    }
    return $response;
})->add($mw);

//update task
$app->put('/tasks/{id}', function(Request $request,Response $response){
    $response = verifyRequiredParams(array('task', 'status'),$response);
    if($response->getStatusCode()==200){
        global $user_id;            
        $task = $request->getParam('task');
        $status = $request->getParam('status');
        $task_id = $request->getAttribute('id');
    
        $db = new DbHandler();
        $message = array();
    
        $result = $db->updateTask($user_id, $task_id, $task, $status);
        // $result = false;
        if ($result==UPDATE_SUCCESS) {
            $message["error"] = false;
            $message["message"] = 'Task updated successfully';
            $response = $response->withJson($message)->withStatus(CODE_CREATED);
        }elseif($result==UPDATE_NO_CHANGE) {
            $message["error"] = false;
            $message["message"] = "No change made";
            $response = $response->withJson($message)->withStatus(CODE_SUCCESS);
            echo 'update failed';
        }else{
            $message["error"] = true;
            $message["message"] = "Sorry, you are not authorized to access this data";
            $response = $response->withJson($message)->withStatus(CODE_FORBIDDEN);
        }
    }
    return $response;
})->add($mw);

//delete task
$app->delete('/tasks/{task_id}', function(Request $request,Response $response) {
    global $user_id;
    $task_id = $request->getAttribute('task_id');

    $db = new DbHandler();
    $result = $db->deleteTask($user_id, $task_id);
    if ($result) {
        $message["error"] = false;
        $message["message"] = "task deleted successfully";
        $response = $response->withJson($message)->withStatus(CODE_SUCCESS);
    } else {
        $message["error"] = true;
        $message["message"] = "task could not be deleted";
        $response = $response->withJson($message)->withStatus(CODE_SERVER_ERROR);
    }
    return $response;
})->add($mw);

$app->run();
////////////////////////---------------------------------functions-----------------------------------------------------------


function verifyRequiredParams($required_fields,$response) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        $error_fields = substr($error_fields, 0, -2);
        $error_massage = array();
        $error_massage["error"] = true;
        $error_massage["message"] = "Required field ($error_fields) ".'is missing or empty';
        $response = $response->withJson($error_massage)->withStatus(CODE_BAD_REQUEST);
    }

    return $response;
}
 
function validateEmail($email,$response) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_massage = array();
        $error_massage["error"] = true;
        $error_massage["message"] = 'Email address is not valid';
        $response = $response->withJson($error_massage)->withStatus(CODE_BAD_REQUEST);
    }
    return $response;
}
 
//middleware
function authenticate($response) {
    $headers = apache_request_headers();
    $result = array();
 
    if (isset($headers['Authorization'])) {
        $db = new DbHandler();
 
        $api_key = $headers['Authorization'];
        if (!$db->isValidApiKey($api_key)) {
            $result["error"] = true;
            $result["message"] = "Access Denied. Invalid Api key";
            $response = $response->withJson($result)->withStatus(401);
        } else {
            global $user_id;
            $user = $db->getUserId($api_key);
            if ($user != NULL){
                $user_id = $user;
                $response = $response->withStatus(200);
            }
        }
    } else {
        $result["error"] = true;
        $result["message"] = "Api key is misssing";
        $response = $response->withJson($result)->withStatus(400);
    }
    return $response;
}



