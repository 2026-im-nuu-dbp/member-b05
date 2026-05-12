<?php
header('Content-Type: text/html; charset=utf-8');
require 'db_config.php';
require 'auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

    $user_check = validate_username($username);
    $pass_check = validate_password($password);
    $match_check = validate_passwords_match($password, $password_confirm);

    if (!$user_check['valid']) {
        $error = $user_check['error'];
    } elseif (!$pass_check['valid']) {
        $error = $pass_check['error'];
    } elseif (!$match_check['valid']) {
        $error = $match_check['error'];
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM members WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = '帳號已存在';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO members (username, password, nickname, profile_complete) VALUES (?, ?, ?, 0)');
                $stmt->execute([$username, $hashed, $username]);
                $success = '註冊成功！請登入';
                header('Refresh: 2; url=login.php');
            }
        } catch (PDOException $e) {
            // Provide a more helpful failure reason. Escape before output.
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
