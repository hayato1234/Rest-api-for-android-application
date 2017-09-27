<?php
class DbHandler {
 
    private $conn;
 
    function __construct() {
        require_once dirname(__FILE__).'/DbConnect.php';
        $db = new DbConnect();
        $this->conn = $db->connect();
    }
 
    public function createUser($name, $email, $password) {
        $response = array();

        try{
            if (!$this->isUserExists($email)) {
                $password_hash = PassHash::hash($password);
                $api_key = $this->generateApiKey();
    
                $sql = "INSERT INTO users (name, email, password_hash, api_key, status) VALUES (:name,:email,:pass_hash,:api, 1)";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindparam(':name',$name);
                $stmt->bindParam(':email',$email);
                $stmt->bindparam(':pass_hash',$password_hash);
                $stmt->bindparam(':api',$api_key);
    
                $result = $stmt->execute();
    
                if ($result) {
                    return USER_CREATED_SUCCESSFULLY;
                } else {
                    return USER_CREATE_FAILED;
                }
            } else {
                return USER_ALREADY_EXISTED;
            }
        }catch(PDOException $e){
            echo '{"error": {"text": '.$e->getMessage().'}}';
        }
        return $response;
    }
 
    public function checkLogin($email, $password) {
        $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE email = :email");
        $stmt->bindParam(':email',$email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $password_hash = $stmt->fetchColumn();

            if (PassHash::check_password($password_hash, $password)) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }
 
    private function isUserExists($email) {
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email',$email);
        $stmt->execute();
        $num_rows = $stmt->rowCount();
        return $num_rows >0;
    }
 
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT name, email, api_key, status, created_at FROM users WHERE email = :email");
        $stmt->bindParam(':email',$email);
        
        if ($stmt->execute()) {
            $user = $stmt->fetchAll(PDO::FETCH_OBJ);
            return $user;
        } else {
            return NULL;
        }
    }
 
    public function getApiKeyById($user_id) {
        $stmt = $this->conn->prepare("SELECT api_key FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id',$user_id);
        if ($stmt->execute()) {
            $api_key = $stmt->get_result()->fetch_assoc();
            return $api_key;
        } else {
            return NULL;
        }
    }
 
    public function getUserId($api_key) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE api_key = :api_key");
        $stmt->bindParam(':api_key',$api_key);
        if ($stmt->execute()) {
            $user_id = $stmt->fetchColumn();
            return $user_id;
        } else {
            return NULL;
        }
    }
 
    public function isValidApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE api_key = :api_key");
        $stmt->bindParam(':api_key',$api_key);
        $stmt->execute();
        $num_rows = $stmt->rowCount();
        return $num_rows > 0;
    }
 
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }
 
    public function createTask($user_id, $task) {        
        $stmt = $this->conn->prepare("INSERT INTO tasks(task) VALUES(:task)");
        $stmt->bindParam(':task',$task);
        try{
            $result = $stmt->execute();
        }catch(Exception $e){
            print_r($e->getMessage());
        } 
 
        if ($result) {
            $new_task_id = $this->conn->lastInsertId();
            $res = $this->createUserTask($user_id, $new_task_id);
            if ($res) {
                return $new_task_id;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }
 
    public function getTask($task_id, $user_id) {
        $stmt = $this->conn->prepare("SELECT t.id, t.task, t.status, t.created_at from tasks t, user_tasks ut WHERE t.id = :task_id AND ut.task_id = t.id AND ut.user_id = :user_id");
        $stmt->bindParam(':task_id',$task_id);
        $stmt->bindParam(':user_id',$user_id);
        if ($stmt->execute()) {
            $task = $stmt->fetchAll(PDO::FETCH_OBJ);
            return $task;
        } else {
            return null;
        }
    }
 
    public function getAllUserTasks($user_id) {
        $stmt = $this->conn->prepare("SELECT t.* FROM tasks t, user_tasks ut WHERE t.id = ut.task_id AND ut.user_id = :user_id");
        $stmt->bindParam(':user_id',$user_id);
        $stmt->execute();
        if($stmt->execute()){
            $tasks = $stmt->fetchAll(PDO::FETCH_OBJ);
            return $tasks;
        }else{
            return null;
        }
    }
 
    public function updateTask($user_id, $task_id, $task, $status) {
        $sql = "UPDATE tasks t, user_tasks ut set t.task = :task, t.status = :status 
        WHERE t.id = :task_id AND t.id = ut.task_id AND ut.user_id = :user_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':task',$task);
        $stmt->bindParam(':status',$status);
        $stmt->bindParam(':task_id',$task_id);
        $stmt->bindParam(':user_id',$user_id);

        try{
            if($stmt->execute()){
                $num_affected_rows = $stmt->rowCount();
                if($num_affected_rows > 0){ //the row is update
                    return UPDATE_SUCCESS;
                }else{//sql excuted but no changes made
                    return UPDATE_NO_CHANGE;
                }
            }else{// sql failed to excute
                return UPDATE_FAIL;
            }
        }catch(Exception $e){
            print_r($e->getMessage());
        } 
    }
 
    public function deleteTask($user_id, $task_id) {
        $sql = "DELETE t FROM tasks t, user_tasks ut WHERE t.id = :task_id AND ut.task_id = t.id AND ut.user_id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':task_id',$task_id);
        $stmt->bindParam(':user_id',$user_id);
        try{
            $stmt->execute();
            $this->conn = null;
        }catch(Exception $e){
            print_r($e->getMessage());
        } 
        $num_affected_rows = $stmt->rowCount();
        return $num_affected_rows > 0;
    }
 
    public function createUserTask($user_id, $task_id) {
        $stmt = $this->conn->prepare("INSERT INTO user_tasks(user_id, task_id) values(:user_id, :task_id)");
        $stmt->bindParam(':task_id',$task_id);
        $stmt->bindParam(':user_id',$user_id);
        $result = $stmt->execute();
        return $result;
    }
}