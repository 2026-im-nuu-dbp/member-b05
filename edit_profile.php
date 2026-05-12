<?php
header('Content-Type: text/html; charset=utf-8');
require 'db_config.php';
require 'auth.php';

require_profile_complete();
$user = get_member_info() ?: [];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'update_profile') {
        $nickname = trim(isset($_POST['nickname']) ? $_POST['nickname'] : '');
        $color = trim(isset($_POST['color']) ? $_POST['color'] : '#667eea');
        $avatar = trim(isset($_POST['avatar']) ? $_POST['avatar'] : '');

        if (empty($nickname)) {
            $error = '昵稱不能為空';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE members SET nickname = ?, color = ?, avatar = ? WHERE id = ?');
                $stmt->execute([$nickname, $color, $avatar, $_SESSION['user_id']]);
                $success = '個人檔案已更新！';
                $user = get_member_info();
            } catch (PDOException $e) {
                $error = '更新失敗';
            }
        }
    } elseif ($action === 'change_password') {
        $old = isset($_POST['old_password']) ? $_POST['old_password'] : '';
        $new = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm = isset($_POST['new_password_confirm']) ? $_POST['new_password_confirm'] : '';

        if (empty($old) || empty($new)) {
            $error = '密碼不能為空';
        } elseif (!password_verify($old, $user['password'])) {
            $error = '舊密碼錯誤';
        } else {
            $pass_check = validate_password($new);
            $match_check = validate_passwords_match($new, $confirm);
            if (!$pass_check['valid']) {
                $error = $pass_check['error'];
            } elseif (!$match_check['valid']) {
                $error = $match_check['error'];
            } else {
                try {
                    $hashed = password_hash($new, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE members SET password = ? WHERE id = ?');
                    $stmt->execute([$hashed, $_SESSION['user_id']]);
                    $success = '密碼已變更！';
                    $user = get_current_user();
                } catch (PDOException $e) {
                    $error = '變更失敗';
                }
            }
        }
    }
}

// 取得當前登入會員的完整資料
function get_member_info() {
    global $pdo;
    
    // 如果沒有登入，回傳空陣列
    if (!isset($_SESSION['user_id'])) {
        return [];
    }

    // 從資料庫抓取該使用者的所有欄位資料
    $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 如果有抓到資料就回傳，沒有的話回傳空陣列
    return $user ?: [];
}

?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯個人檔案 - 討論區</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .container { 
            grid-template-columns: 1fr; 
            max-width: 800px; /* 限制整體容器寬度 */
            margin: 0 auto;
        }
        
        /* 解決卡片太小的問題 */
        .card {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            max-width: 650px !important; /* 加大卡片最大寬度 */
            width: 100%;
            box-sizing: border-box;
        }

        /* 改用 auto-fill 自動排版，防止超出邊界 */
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
            width: 100%;
            box-sizing: border-box;
        }

        .avatar-btn { 
            font-size: 32px; 
            height: 60px; /* 固定高度取代 min-height */
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 0;
        }

        .avatar-btn:hover, .color-btn:hover { 
            transform: scale(1.1); 
        }

        .avatar-btn.selected { 
            border-color: #667eea; 
            background: #f0f4ff; 
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3); 
        }

        .color-btn { 
            height: 60px; 
            padding: 0;
            border: none; /* 移除預設邊框 */
        }

        /* 新增：選取顏色時的精緻匡列效果 */
        .color-btn.selected { 
            outline: 3px solid #667eea; /* 外部匡線 */
            outline-offset: 3px; /* 匡線與按鈕的間距 */
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2); 
        }

        .current-info { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            background: #f9f9f9; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 20px; 
        }
        .avatar-display { font-size: 48px; }

        @media (max-width: 768px) {
            /* 手機版縮小按鈕最小寬度 */
            .avatar-picker, .color-picker { grid-template-columns: repeat(auto-fill, minmax(50px, 1fr)); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>編輯個人檔案</h1>
    </div>

    <div class="container">
        <a href="index.php" class="back-link">← 返回首頁</a>

        <?php if ($error): ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>

        <div class="card">
            <h2>個人檔案</h2>

            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label>昵稱</label>
                    <input type="text" name="nickname" required value="<?= escape($user['nickname']) ?>">
                </div>

                <div class="form-group">
                    <label>選擇大頭貼</label>
                    <div class="avatar-picker" id="avatarPicker">
                        <?php foreach ($avatars as $avatar): ?>
                            <button type="button" class="avatar-btn" data-avatar="<?= $avatar ?>"><?= $avatar ?></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="avatar" id="selectedAvatar" value="<?= escape($user['avatar']) ?>">
                </div>

                <div class="form-group">
                    <label>選擇顏色</label>
                    <div class="color-picker" id="colorPicker">
                        <?php foreach ($colors as $color): ?>
                            <button type="button" class="color-btn" data-color="<?= $color ?>" style="background-color: <?= $color ?>;"></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="color" id="selectedColor" value="<?= escape($user['color']) ?>">
                </div>

                <button type="submit" class="btn">更新檔案</button>
            </form>
        </div>

        <div class="card">
            <h2>變更密碼</h2>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label>舊密碼</label>
                    <input type="password" name="old_password" required>
                </div>

                <div class="row row-2">
                    <div class="form-group">
                        <label>新密碼 (至少6個字符)</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>確認新密碼</label>
                        <input type="password" name="new_password_confirm" required>
                    </div>
                </div>

                <button type="submit" class="btn">變更密碼</button>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.avatar-btn').forEach(btn => {
            if (btn.dataset.avatar === document.getElementById('selectedAvatar').value) btn.classList.add('selected');
            btn.addEventListener('click', e => {
                e.preventDefault();
                document.querySelectorAll('.avatar-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                document.getElementById('selectedAvatar').value = btn.dataset.avatar;
            });
        });

        document.querySelectorAll('.color-btn').forEach(btn => {
            if (btn.dataset.color === document.getElementById('selectedColor').value) btn.classList.add('selected');
            btn.addEventListener('click', e => {
                e.preventDefault();
                document.querySelectorAll('.color-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                document.getElementById('selectedColor').value = btn.dataset.color;
            });
        });
    </script>
</body>
</html>