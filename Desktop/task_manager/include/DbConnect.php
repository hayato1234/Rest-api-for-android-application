<?php
 
class DbConnect {
 
    private $conn;
    private $dbhost = 'localhost';
    private $dbname = 'task_manager';
 
    function connect() {
        include_once dirname(__FILE__).'/Config.php';
 
        $mysql_connect_str = "mysql:host=$this->dbhost; dbname=$this->dbname;";
        $this->conn = new PDO($mysql_connect_str,DB_USERNAME,DB_PASSWORD);

        $this->conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 
        if (mysqli_connect_errno()) {
            echo "Failed to connect to MySQL: " . mysqli_connect_error();
        }
        return $this->conn;
    }
 
}
 
?>