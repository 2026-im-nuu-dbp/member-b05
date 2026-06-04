<?php
// 設定 HTTP 標頭，指定內容為 HTML 且編碼為 UTF-8，防止中文亂碼
header('Content-Type: text/html; charset=utf-8');

// 引入資料庫設定檔與驗證函式庫
require 'db_config.php';
require 'auth.php';

// 初始化錯誤訊息變數
$error = '';

// 【雙重身分切換開關】
// 檢查網址列（GET）有沒有帶 `setup` 參數（例如：login.php?setup=1）
// 如果有，變數 $setup 就會是 1（代表現在是「首次設定檔案」模式）；沒有就是 0（代表是「一般登入」模式）
$setup = isset($_GET['setup']) ? 1 : 0;

// =========================================================================
// 區塊一：處理「首次設定個人檔案」的表單提交（必須滿足是在 setup 模式下，且用 POST 送出表單）
// =========================================================================
if ($setup && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 接收並清理使用者選取的暱稱、Emoji頭像、代表色
    $nickname = trim(isset($_POST['nickname']) ? $_POST['nickname'] : '');
    $avatar = trim(isset($_POST['avatar']) ? $_POST['avatar'] : '');
    $color = trim(isset($_POST['color']) ? $_POST['color'] : '');
    
    // 確保三個欄位都有確實填寫或選取
    if ($nickname && $avatar && $color) {
        try {
            // 更新當前登入使用者的資料，並將關鍵的 `profile_complete` 欄位改為 1（代表未來不用再被強制導引到這頁了）
            $stmt = $pdo->prepare('UPDATE members SET nickname = ?, avatar = ?, color = ?, profile_complete = 1 WHERE id = ?');
            $stmt->execute([$nickname, $avatar, $color, $_SESSION['user_id']]);
            
            // 將最新的暱稱同步寫入 Session 中，方便發文或留言時不用一直查資料庫，提升網頁效能
            $_SESSION['nickname'] = $nickname;
            
            // 完美通關！重導向到論壇首頁
            header('Location: index.php');
            exit; // 中斷後續 PHP 程式碼執行
        } catch (PDOException $e) {
            $error = '設定失敗';
        }
    } else {
        $error = '請填寫所有欄位並選擇頭像與顏色';
    }
} 

// =========================================================================
// 區塊二：處理「一般會員登入」的表單提交（當不是 setup 模式，且用 POST 送出表單時）
// =========================================================================
elseif (!$setup && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 接收並清理登入時輸入的帳號與密碼
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // 防空檢查
    if (empty($username) || empty($password)) {
        $error = '帳號和密碼不能為空';
    } else {
        try {
            // 依據帳號去資料庫撈取使用者的加密密碼、管理員標記與個人資料完成度
            $stmt = $pdo->prepare('SELECT id, password, is_admin, profile_complete FROM members WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // 如果撈得到這個帳號，且利用 password_verify() 檢查密碼比對正確
            if ($user && password_verify($password, $user['password'])) {
                // 【核心：建立登入狀態】將會員的基本身分證存入 Session 空間
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = $user['is_admin'];

                // 【分支判斷 1】如果是剛註冊完、從未設定過頭像暱稱的新手（profile_complete 為 0）
                if ($user['profile_complete'] == 0) {
                    // 立刻原地強制轉向到「新手導引設定」模式
                    header('Location: login.php?setup=1');
                } 
                // 【分支判斷 2】如果是資料健全的老會員
                else {
                    // 檢查先前有沒有被「權限攔截器（如 require_login）」攔截並留下的本來想看網址（redirect_to）
                    // 如果有，就送他去原本想去的頁面；如果沒有，就預設去論壇首頁 index.php
                    header('Location: ' . (isset($_SESSION['redirect_to']) ? $_SESSION['redirect_to'] : 'index.php'));
                    
                    // 用完就立刻把這個記憶的網址擦掉，避免下次登入又莫名其妙跳去舊網頁
                    unset($_SESSION['redirect_to']);
                }
                exit; // 成功轉向，收工中斷
            } else {
                // 為了防範駭客暴力破解，通常不把話說死（不說是用戶名錯還是密碼錯），一律顯示模糊的錯誤訊息
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