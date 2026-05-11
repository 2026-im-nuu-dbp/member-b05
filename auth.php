<?php
// Authentication and helper functions

if (!session_id()) {
    session_start();
}

if (!defined('DB_CONFIG_LOADED')) {
    require 'db_config.php';
    define('DB_CONFIG_LOADED', true);
}

if (!function_exists('is_logged_in')) {
    function is_logged_in()
    {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
}

if (!function_exists('get_current_user')) {
    function get_current_user()
    {
        if (!is_logged_in()) {
            return null;
        }

        global $pdo;
        try {
            $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch();
            return is_array($result) ? $result : null;
        } catch (PDOException $e) {
            return null;
        }
    }
}

if (!function_exists('is_admin')) {
    function is_admin()
    {
        if (!is_logged_in()) return false;
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
}

if (!function_exists('require_login')) {
    function require_login()
    {
        if (!is_logged_in()) {
            $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
            header('Location: auth.php?page=login');
            exit;
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin()
    {
        require_login();
        if (!is_admin()) {
            header('Location: index.php');
            exit;
        }
    }
}

if (!function_exists('require_profile_complete')) {
    function require_profile_complete()
    {
        require_login();
        $user = get_current_user();
        // 只有在 profile_complete 明确为 0 时才重定向到设置页面
        if (!$user || (isset($user['profile_complete']) && $user['profile_complete'] == 0)) {
            header('Location: auth.php?page=setup');
            exit;
        }
    }
}

if (!function_exists('validate_username')) {
    function validate_username($username)
    {
        $username = trim($username);
        if (empty($username)) return ['valid' => false, 'error' => '帳號不能為空'];
        if (strlen($username) < 3) return ['valid' => false, 'error' => '帳號至少需要3個字符'];
        return ['valid' => true];
    }
}

if (!function_exists('validate_password')) {
    function validate_password($password)
    {
        if (empty($password)) return ['valid' => false, 'error' => '密碼不能為空'];
        if (strlen($password) < 6) return ['valid' => false, 'error' => '密碼至少需要6個字符'];
        return ['valid' => true];
    }
}

if (!function_exists('validate_passwords_match')) {
    function validate_passwords_match($p1, $p2)
    {
        return $p1 === $p2 ? ['valid' => true] : ['valid' => false, 'error' => '密碼不相符'];
    }
}

if (!function_exists('escape')) {
    function escape($text)
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

?>
