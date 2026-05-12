<?php
header('Content-Type: text/html; charset=utf-8');
require 'db_config.php';
require 'auth.php';

$error = '';
$setup = isset($_GET['setup']) ? 1 : 0;

// 處理首次設定檔案
if ($setup && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim(isset($_POST['nickname']) ? $_POST['nickname'] : '');
    $avatar = trim(isset($_POST['avatar']) ? $_POST['avatar'] : '');
    $color = trim(isset($_POST['color']) ? $_POST['color'] : '');
    
    if ($nickname && $avatar && $color) {
        try {
            $stmt = $pdo->prepare('UPDATE members SET nickname = ?, avatar = ?, color = ?, profile_complete = 1 WHERE id = ?');
            $stmt->execute([$nickname, $avatar, $color, $_SESSION['user_id']]);
            $_SESSION['nickname'] = $nickname;
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $error = '設定失敗';
        }
    } else {
        $error = '請填寫所有欄位並選擇頭像與顏色';
    }
} elseif (!$setup && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 登入處理
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $error = '帳號和密碼不能為空';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, password, is_admin, profile_complete FROM members WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = $user['is_admin'];

                if ($user['profile_complete'] == 0) {
                    header('Location: login.php?setup=1');
                } else {
                    header('Location: ' . (isset($_SESSION['redirect_to']) ? $_SESSION['redirect_to'] : 'index.php'));
                    unset($_SESSION['redirect_to']);
                }
                exit;
            } else {
                $error = '帳號或密碼錯誤';
            }
        } catch (PDOException $e) {
            $error = '登入失敗';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $setup ? '設定個人檔案' : '登入' ?> - 討論區</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f4f7f6; margin: 0; padding: 20px; }
        .container { max-width: <?= $setup ? '750px' : '400px' ?>; width: 100%; margin: 0 auto; }
        .card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        /* 同步 edit_profile.php 的選擇器樣式 */
        .avatar-picker, .color-picker { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); 
            gap: 15px; 
            margin-bottom: 20px; 
        }
        .avatar-btn, .color-btn { 
            border: 2px solid #ddd; 
            background: white; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: all 0.3s; 
            height: 60px;
            display: flex; align-items: center; justify-content: center;
        }
        .avatar-btn { font-size: 32px; }
        .avatar-btn:hover, .color-btn:hover { transform: scale(1.1); }
        .avatar-btn.selected { border-color: #667eea; background: #f0f4ff; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3); }
        .color-btn.selected { outline: 3px solid #667eea; outline-offset: 3px; transform: scale(1.05); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1 class="text-center"><?= $setup ? '設定個人檔案' : '會員登入' ?></h1>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <form method="POST" id="setupForm">
                <?php if ($setup): ?>
                    <div class="form-group">
                        <label>昵稱</label>
                        <input type="text" name="nickname" required>
                    </div>

                    <div class="form-group">
                        <label>選擇大頭貼</label>
                        <div class="avatar-picker">
                            <?php foreach ($avatars as $avatar): ?>
                                <button type="button" class="avatar-btn" data-avatar="<?= $avatar ?>"><?= $avatar ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="avatar" id="selectedAvatar" required>
                    </div>

                    <div class="form-group">
                        <label>選擇顏色</label>
                        <div class="color-picker">
                            <?php foreach ($colors as $color): ?>
                                <button type="button" class="color-btn" data-color="<?= $color ?>" style="background-color: <?= $color ?>;"></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="color" id="selectedColor" required>
                    </div>

                    <button type="submit" class="btn" style="width:100%">完成設定，開始使用</button>
                <?php else: ?>
                    <div class="form-group">
                        <label>帳號</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>密碼</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn" style="width:100%">登入</button>
                <?php endif; ?>
            </form>

            <?php if (!$setup): ?>
                <p class="text-center" style="margin-top: 20px;">
                    還沒有帳號？<a href="register.php">立即註冊</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($setup): ?>
    <script>
        // 同步 edit_profile.php 的 JavaScript 選擇邏輯
        document.querySelectorAll('.avatar-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.avatar-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                document.getElementById('selectedAvatar').value = btn.dataset.avatar;
            });
        });

        document.querySelectorAll('.color-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.color-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                document.getElementById('selectedColor').value = btn.dataset.color;
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>