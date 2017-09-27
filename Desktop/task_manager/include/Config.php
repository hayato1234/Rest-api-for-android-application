<?php
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'your pass');
define('DB_HOST', 'localhost');
define('DB_NAME', 'task_manager');
 
define('USER_CREATED_SUCCESSFULLY', 0);
define('USER_CREATE_FAILED', 1);
define('USER_ALREADY_EXISTED', 2);

define('CODE_SUCCESS',200);
define('CODE_CREATED',201);
define('CODE_NO_CONTENT',204);
define('CODE_BAD_REQUEST',400);
define('CODE_UNAUTHORIZED',401);
define('CODE_FORBIDDEN',403);
define('CODE_CONFLICT',409);
define('CODE_SERVER_ERROR',500);

define('UPDATE_SUCCESS','success');
define('UPDATE_NO_CHANGE','no change');
define('UPDATE_FAIL','failed');
?>