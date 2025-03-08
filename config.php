<?php
define('USER_DB_FILE', dirname(__DIR__) . '/private/ghostpinger_users.json');

function ensurePrivateDirectoryExists() {
    $dir = dirname(USER_DB_FILE);
    if (!file_exists($dir)) {
        mkdir($dir, 0700, true);
    }
}

function initUserDatabase() {
    ensurePrivateDirectoryExists();
    if (!file_exists(USER_DB_FILE)) {
        $users = [];
        file_put_contents(USER_DB_FILE, json_encode($users));
        return true;
    }
    return false;
}

function addUser($username, $password) {
    $users = getUsersData();
    
    if (isset($users[$username])) {
        return false;
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $users[$username] = [
        'password' => $hashedPassword,
        'created' => time(),
        'last_login' => null
    ];
    
    file_put_contents(USER_DB_FILE, json_encode($users));
    return true;
}

function authenticateUser($username, $password) {
    $users = getUsersData();
    
    if (!isset($users[$username])) {
        return false;
    }
    
    if (password_verify($password, $users[$username]['password'])) {
        $users[$username]['last_login'] = time();
        file_put_contents(USER_DB_FILE, json_encode($users));
        return true;
    }
    
    return false;
}

function getUsersData() {
    if (!file_exists(USER_DB_FILE)) {
        initUserDatabase();
        return [];
    }
    
    return json_decode(file_get_contents(USER_DB_FILE), true);
}

function hasUsers() {
    $users = getUsersData();
    return !empty($users);
}

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        session_start();
    }
}

function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

function logoutUser() {
    startSecureSession();
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}
