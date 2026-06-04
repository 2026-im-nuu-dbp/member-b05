<?php
// 認證與輔助函式庫

// 檢查目前是否已經啟動 Session（工作階段），若尚未啟動則執行 session_start()。
// 這是為了跨網頁紀錄使用者的登入狀態（例如儲存 user_id）
if (!session_id()) {
    session_start();
}

// 【防止重複載入機制】
// 檢查是否已經定義過 'DB_CONFIG_LOADED' 這個常數
if (!defined('DB_CONFIG_LOADED')) {
    require_once 'db_config.php'; // 若沒載入過，則引入資料庫連線設定
    define('DB_CONFIG_LOADED', true); // 定義常數，標記「已載入」，避免後續重複引入導致錯誤
}

// --- 全局共用資料 ---
// 統一管理頭像與顏色，避免 login.php 與 edit_profile.php 重複定義
$avatars = ['😀', '😂', '😍', '😎', '🤔', '🙌', '👍', '💪']; // 可供會員選擇的預設表情頭像
$colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E2']; // 頭像的背景顏色

// --- 登入狀態檢查 ---
// 檢查目前使用者的 Session 裡面，是否有設定 user_id 與 username，用來判斷「目前是否為登入狀態」
function is_logged_in() {
    return isset($_SESSION['user_id'], $_SESSION['username']);
}

// 檢查 get_current_user 這個函式名稱是否已經存在，避免重複定義導致程式崩潰
if (!function_exists('get_current_user')) {
    // 獲取當前登入會員的完整資料
    function get_current_user() {
        if (!is_logged_in()) return null; // 如果沒登入，直接回傳空值（null）

        global $pdo; // 引入全局的 $pdo 資料庫連線物件
        try {
            // 從資料庫撈出該登入使用者的所有欄位資料
            $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch() ?: null; // 如果撈得到就回傳資料陣列，撈不到就回傳 null
        } catch (PDOException $e) {
            return null; // 資料庫出錯時回傳 null
        }
    }
}

// 檢查當前登入的使用者是否為「管理員」
function is_admin() {
    // 必須同時滿足：1.已登入、2.Session中有管理員標籤、3.該標籤值為 1
    return is_logged_in() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// --- 權限強制檢查（守門員函式） ---
// 強制頁面必須登入才能觀看
function require_login() {
    if (!is_logged_in()) {
        // 記憶使用者當前想瀏覽的網址（URL），以便登入成功後能自動跳轉回來，優化體驗
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php'); // 強制重導向到登入頁面
        exit; // 停止後續所有 PHP 程式碼的執行
    }
}

// 強制頁面必須是管理員才能觀看
function require_admin() {
    require_login(); // 先進一步檢查是否登入
    if (!is_admin()) {
        header('Location: index.php'); // 若不是管理員，強制踢回首頁
        exit;
    }
}

// 強制頁面必須填寫完「完整個人資料」才能觀看
function require_profile_complete() {
    require_login(); // 先檢查是否登入
    $user = get_current_user(); // 獲取當前用戶資料
    // 檢查用戶是否存在，或者 profile_complete 欄位是否為 0（?? 0 是指如果欄位不存在則預設為 0）
    if (!$user || ($user['profile_complete'] ?? 0) == 0) {
        header('Location: login.php?setup=1'); // 強制引導到資料設定頁面
        exit;
    }
}

// --- 資料驗證函式 ---
// 驗證帳號格式
function validate_username($username) {
    $username = trim($username); // 去除首尾空白
    if (empty($username)) return ['valid' => false, 'error' => '帳號不能為空'];
    if (strlen($username) < 3) return ['valid' => false, 'error' => '帳號至少需要3個字符'];
    return ['valid' => true]; // 通過驗證，回傳 valid 為 true
}

// 驗證密碼格式
function validate_password($password) {
    if (empty($password)) return ['valid' => false, 'error' => '密碼不能為空'];
    if (strlen($password) < 6) return ['valid' => false, 'error' => '密碼至少需要6個字符'];
    return ['valid' => true]; // 通過驗證，回傳 valid 為 true
}

// 驗證兩次密碼是否一致
function validate_passwords_match($p1, $p2) {
    // 使用「===」全等運算子，同時檢查兩者的「值」與「資料型態」是否完全相同
    if ($p1 === $p2) {
        // 條件成立：兩次輸入的密碼完全一致
        return ['valid' => true];
    } else {
        // 條件不成立：密碼不相同，回傳失敗狀態與錯誤訊息
        return ['valid' => false, 'error' => '密碼不相符'];
    }
}

// --- 安全性輔助 ---
// 預防 XSS 攻擊的字串轉義函式
function escape($text) {
    // htmlspecialchars 會把特殊字元（如 <, >, &, ", '）轉成 HTML 實體字元（例如 < 變成 &lt;）
    // (string)$text 是強制轉型為字串，確保傳入數字或空值時不會噴錯
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}