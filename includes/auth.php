<?php
/**
 * Authentication Helper Functions
 * SmartEdu Hub - HTML/PHP Version
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Start user session after successful login
 */
function login_user($user_data) {
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['user_name'] = $user_data['name'];
    $_SESSION['user_email'] = $user_data['email'];
    $_SESSION['user_role'] = $user_data['role'];
    $_SESSION['logged_in'] = true;
    
    // إضافية للأدوار الخاصة
    if (!empty($user_data['stage_id'])) {
        $_SESSION['stage_id'] = $user_data['stage_id'];
    }
    if (!empty($user_data['level_id'])) {
        $_SESSION['level_id'] = $user_data['level_id'];
    }
    if (!empty($user_data['subject_id'])) {
        $_SESSION['subject_id'] = $user_data['subject_id'];
    }
    if (!empty($user_data['teacher_code'])) {
        $_SESSION['teacher_code'] = $user_data['teacher_code'];
    }
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if user has specific role
 */
function has_role($role) {
    return is_logged_in() && $_SESSION['user_role'] === $role;
}

/**
 * Check if user has any of the specified roles
 */
function has_any_role($roles) {
    if (!is_logged_in()) {
        return false;
    }
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Get current user ID
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function get_user_role() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Logout user
 */
function logout_user() {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Unset all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Require authentication (redirect if not logged in)
 */
function require_auth() {
    if (!is_logged_in()) {
        header("Location: ../public/login.php");
        exit();
    }
}

/**
 * Require login - alias for require_auth
 */
function requireLogin() {
    require_auth();
}

/**
 * Require specific role (redirect if doesn't have role)
 */
function require_role($role) {
    require_auth();
    if (!has_role($role)) {
        header("Location: ../dashboard/index.php");
        exit();
    }
}

/**
 * Require one of multiple roles
 */
function requireRole($roles) {
    require_auth();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!has_any_role($roles)) {
        header("Location: ../dashboard/index.php");
        exit();
    }
}

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Hash password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate unique teacher code
 */
function generate_teacher_code() {
    return 'T' . strtoupper(substr(uniqid(), -8));
}

/**
 * Set flash message
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,  // success, error, warning, info
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}
?>
