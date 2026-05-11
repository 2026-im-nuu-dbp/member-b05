<?php
header('Content-Type: text/html; charset=utf-8');
require 'db_config.php';
require 'auth.php';

$error = '';
$setup = isset($_GET['setup']) ? 1 : 0;

// 处理首次设定档案
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
            $error = '设定失败';
        }
    } else {
        $error = '请填写所有字段';
    }
} elseif (!$setup && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 登入处理
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
    <title><?= $setup ? '設定檔案' : '登入' ?> - 討論區</title>
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f4f7f6;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        
        /* 動態設定寬度：登入時窄一點，設定時寬一點 */
        .container {
            max-width: <?= $setup ? '600px' : '400px' ?> !important;
            width: 100%;
            margin: auto;
        }

        .card {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            width: 100%;
            box-sizing: border-box;
        }
        
        /* ======== 統一成之前討論的 Flex 排版 ======== */
        .avatar-picker, .color-picker {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 12px !important;
            margin-bottom: 20px;
            justify-content: flex-start;
        }
        
        .avatar-label, .color-label {
            cursor: pointer;
            width: 60px !important;
            height: 60px !important;
            flex-shrink: 0;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .avatar-label {
            font-size: 30px;
            border: 2px solid #ddd;
            background: white;
        }

        .color-label {
            border: none;
        }
        
        /* 滑鼠移上去的放大效果 */
        .avatar-label:hover, .color-label:hover {
            transform: scale(1.1) !important;
        }

        /* 頭像被「選中」時的效果 */
        input[type="radio"][name="avatar"]:checked + label {
            border-color: #667eea !important;
            background: #f0f4ff !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3) !important;
            transform: scale(1.1) !important;
        }

        /* 顏色被「選中」時的效果 */
        input[type="radio"][name="color"]:checked + label {
            outline: 3px solid #667eea !important;
            outline-offset: 4px !important;
            transform: scale(1.05) !important;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
        }
        /* ==================================== */
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1 class="text-center"><?= $setup ? '設定個人檔案' : '會員登入' ?></h1>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php if ($setup): ?>
                    <div class="form-group">
                        <label>昵稱</label>
                        <input type="text" name="nickname" required>
                    </div>

                    <div class="form-group">
                        <label>選擇頭像</label>
                        <div class="avatar-picker">
                            <?php foreach(['😀','😂','😍','🤔','😎','🔥','⭐','🎉','❤️','😊','🚀','👍','😴','🎮'] as $e): ?>
                                <input type="radio" name="avatar" value="<?=$e?>" style="display:none" id="a_<?=$e?>" required>
                                <label for="a_<?=$e?>" class="avatar-label"><?=$e?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>選擇專屬背景色</label>
                        <div class="color-picker">
                            <?php foreach(['#667eea','#764ba2','#f093fb','#4facfe','#00f2fe','#43e97b','#fa709a','#fee140','#30cfd0','#330867'] as $c): ?>
                                <input type="radio" name="color" value="<?=$c?>" style="display:none" id="c_<?=substr($c,1)?>" required>
                                <label for="c_<?=substr($c,1)?>" class="color-label" style="background:<?=$c?>;"></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="width:100%; margin-top: 10px;">完成設定，開始使用</button>
                <?php else: ?>
                    <div class="form-group">
                        <label>帳號</label>
                        <input type="text" name="username" required value="<?= escape(isset($_POST['username']) ? $_POST['username'] : '') ?>">
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
                    還沒有帳號？<a href="register.php" style="color: #667eea; text-decoration: none; font-weight: bold;">立即註冊</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>