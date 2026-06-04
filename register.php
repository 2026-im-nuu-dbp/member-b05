<?php
header('Content-Type: text/html; charset=utf-8');
require 'db_config.php';
require 'auth.php';

// 初始化錯誤訊息與成功訊息變數
$error = '';
$success = '';

// 檢查使用者是否提交了表單（當點擊註冊按鈕，瀏覽器發送 POST 請求時觸發）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 接收並處理表單資料：
    // trim() 用於清除帳號前後的空白字元。三元運算子用於確保欄位沒填時給予空字串，避免噴 Warning。
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

    // 呼叫 auth.php 裡定義的自訂驗證函式，檢查帳號格式、密碼長度及兩次密碼是否一致
    $user_check = validate_username($username);
    $pass_check = validate_password($password);
    $match_check = validate_passwords_match($password, $password_confirm);

    // 依序檢查驗證結果，只要其中一項不符合規範，就將錯誤訊息存入 $error 變數
    if (!$user_check['valid']) { 
        $error = $user_check['error']; // 帳號驗證失敗（例如太短或含非法字元）
    } elseif (!$pass_check['valid']) {
        $error = $pass_check['error']; // 密碼驗證失敗（例如長度不足）
    } elseif (!$match_check['valid']) {
        $error = $match_check['error']; // 兩次密碼輸入不一致
    } else {
        // 當前端資料驗證完全正確，開始進入資料庫操作流程
        try {
            // 【步驟 1】檢查帳號是否已被註冊
            // 使用「?」預備陳述式（Prepared Statement）防範 SQL 注入攻擊
            $stmt = $pdo->prepare('SELECT id FROM members WHERE username = ?');
            $stmt->execute([$username]); // 將使用者輸入的帳號帶入查詢
            
            // 如果 fetch() 有撈到資料，代表該帳號已存在於資料庫中
            if ($stmt->fetch()) {
                $error = '帳號已存在';
            } else {
                // 【步驟 2】進行新帳號註冊
                // 使用 PHP 內建安全函式將密碼進行雜湊（Hash）加密，絕對不能在資料庫存明文密碼！
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                
                // 準備寫入新會員資料（欄位：帳號、加密後的密碼、暱稱、個人資料完成度預設為 0）
                $stmt = $pdo->prepare('INSERT INTO members (username, password, nickname, profile_complete) VALUES (?, ?, ?, 0)');
                
                // 執行 SQL 語法，這裡將帳號同時暫時當作暱稱寫入
                $stmt->execute([$username, $hashed, $username]);
                
                // 註冊成功，設定成功訊息
                $success = '註冊成功！請登入';
                
                // 讓網頁在 2 秒後自動跳轉到登入頁面（login.php）
                header('Refresh: 2; url=login.php');
            }
        } catch (PDOException $e) {
            // 如果資料庫執行過程中出錯，捕捉異常並將錯誤訊息回報給 $error
            // 注意：實務上在正式上線（Production）環境，建議不要直接把 $e->getMessage() 噴給使用者看，避免暴露資料庫結構
            $error = '註冊失敗：' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊 - 討論區</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 400px;
            margin: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1 class="text-center">會員註冊</h1>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label>帳號 (3個字符以上)</label>
                        <input type="text" name="username" required value="<?= escape(isset($_POST['username']) ? $_POST['username'] : '') ?>">
                    </div>

                    <div class="form-group">
                        <label>密碼 (6個字符以上)</label>
                        <input type="password" name="password" required>
                    </div>

                    <div class="form-group">
                        <label>確認密碼</label>
                        <input type="password" name="password_confirm" required>
                    </div>

                    <button type="submit" class="btn" style="width: 100%;">註冊</button>
                </form>

                <p class="text-center mt-20">
                    已有帳號？<a href="login.php">立即登入</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
