<?php
// 認證與輔助函式庫

if (!session_id()) {
    session_start();
}

// 確保資料庫配置只載入一次
if (!defined('DB_CONFIG_LOADED')) {
    require_once 'db_config.php';
    define('DB_CONFIG_LOADED', true);
}

// --- 全局共用資料 ---
// 統一管理頭像與顏色，避免 login.php 與 edit_profile.php 重複定義
$avatars = ['😀', '😂', '😍', '😎', '🤔', '🙌', '👍', '💪'];
$colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E2'];

// --- 登入狀態檢查 ---
function is_logged_in() {
    return isset($_SESSION['user_id'], $_SESSION['username']);
}

function get_current_user() {
    if (!is_logged_in()) return null;

    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// --- 權限強制檢查 ---
function require_login() {
    if (!is_logged_in()) {
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        header('Location: index.php');
        exit;
    }
}

function require_profile_complete() {
    require_login();
    $user = get_current_user();
    if (!$user || ($user['profile_complete'] ?? 0) == 0) {
        header('Location: login.php?setup=1');
        exit;
    }
}

// --- 資料驗證函式 ---
function validate_username($username) {
    $username = trim($username);
    if (empty($username)) return ['valid' => false, 'error' => '帳號不能為空'];
    if (strlen($username) < 3) return ['valid' => false, 'error' => '帳號至少需要3個字符'];
    return ['valid' => true];
}

function validate_password($password) {
    if (empty($password)) return ['valid' => false, 'error' => '密碼不能為空'];
    if (strlen($password) < 6) return ['valid' => false, 'error' => '密碼至少需要6個字符'];
    return ['valid' => true];
}

function validate_passwords_match($p1, $p2) {
    return $p1 === $p2 ? ['valid' => true] : ['valid' => false, 'error' => '密碼不相符'];
}

// --- 安全性輔助 ---
function escape($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}