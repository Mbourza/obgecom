<?php 

function escape($string){

	return htmlentities($string, ENT_QUOTES, 'UTF-8');
}

function checkUserRole($requiredRole)
{
    session_start();
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] < $requiredRole) {
        header('Location: error.php');
        exit;
    }
}


?>