<?php

header('Content-Type: text/html; charset=utf-8');

// 引入資料庫設定檔與認證函式庫
require 'db_config.php';
require 'auth.php';

// 呼叫 auth.php 的函式，如果當前使用者不是管理員，直接強行踢回首頁並中斷執行
require_admin();

// 透過 GET 請求獲取當前的頁面動作（例如：看首頁、看會員列表、看分類），預設值為 'home'（首頁）
$action = isset($_GET['action']) ? $_GET['action'] : 'home';

// 初始化全域的提示訊息與錯誤訊息變數
$message = '';
$error = '';

// 【第一大部分：處理表單提交 (POST 請求)】
// 當管理員點擊任何表單按鈕（新增、修改、刪除）時，會進入這個區塊
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 獲取表單傳過來的具體動作名稱
    $post_action = isset($_POST['action']) ? $_POST['action'] : '';

    // ==========================================
    // 1. 新增會員 (Add Member)
    // ==========================================
    if ($post_action === 'add_member') {
        // 接收並清理輸入資料
        $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $nickname = trim(isset($_POST['nickname']) ? $_POST['nickname'] : '');
        $is_admin = isset($_POST['is_admin']) ? 1 : 0; // 如果有勾選管理員就是 1，沒勾就是 0
        
        // 後端欄位防空驗證
        if (empty($username) || empty($password) || empty($nickname)) {
            $error = '請填寫所有必填欄位';
        } else {
            try {
                // 檢查此帳號是否已被他人註冊
                $stmt = $pdo->prepare('SELECT id FROM members WHERE username = ?');
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = '新增失敗：帳號已存在';
                } else {
                    // 將新密碼進行安全性雜湊加密
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    
                    // 執行寫入。這裡預設設定 profile_complete = 1，讓後台手動新增的會員免去填寫初始資料的步驟
                    $stmt = $pdo->prepare('INSERT INTO members (username, password, nickname, is_admin, profile_complete, color, avatar) VALUES (?, ?, ?, ?, 1, "#667eea", "😀")');
                    $stmt->execute([$username, $hashed, $nickname, $is_admin]);
                    
                    $message = '會員已成功新增！';
                    $action = 'members'; // 成功後，將頁面轉入會員列表
                }
            } catch (PDOException $e) {
                $error = '新增會員失敗: ' . $e->getMessage();
            }
        }
    } 

    // ==========================================
    // 2. 修改會員資料 (Update Member)
    // ==========================================
    elseif ($post_action === 'update_member') {

        // intval() 用於強制轉型為整數，確保安全
        // 先用三元運算子檢查是否有傳入 'member_id'，有就取值，沒有就給預設值 '0'。
        $member_id = intval(isset($_POST['member_id']) ? $_POST['member_id'] : 0);
        // 檢查是否有輸入 'nickname'，若有則取值，若無則給予空字串 ''
        $nickname = trim(isset($_POST['nickname']) ? $_POST['nickname'] : '');
        // 檢查表單是否有填寫 'new_password'（後台修改資料時，密碼通常是選填，留空代表不修改）
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        // 在 HTML 中，如果 Checkbox 沒有被勾選，表單送出時後端完全「不會收到這個欄位」（即 isset 為 false）。
        // 因此這裡判斷：如果 isset($_POST['is_admin']) 為 true（代表有勾選），就給值 1；沒勾選就給值 0。
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;

        if ($member_id > 0 && !empty($nickname)) {
            try {
                // 如果後台有填寫「新密碼」欄位，就連同新密碼一起加密更新
                if (!empty($new_password)) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE members SET nickname = ?, password = ?, is_admin = ? WHERE id = ?');
                    $stmt->execute([$nickname, $hashed, $is_admin, $member_id]);
                } else { 
                    // 如果「新密碼」欄位留空，代表不修改密碼，維持原樣，只更新暱稱與權限
                    $stmt = $pdo->prepare('UPDATE members SET nickname = ?, is_admin = ? WHERE id = ?');
                    $stmt->execute([$nickname, $is_admin, $member_id]);
                }
                $message = '會員資料已更新！';
                    $action = 'members'; // 成功後導向會員列表
                } catch (PDOException $e) {
                $error = '更新失敗: ' . $e->getMessage();
            }
        } else {
            $error = '暱稱不能為空！';
        }
    }

    // ==========================================
    // 3. 刪除會員 (Delete Member)
    // ==========================================
    elseif ($post_action === 'delete_member') {
        $member_id = intval(isset($_POST['member_id']) ? $_POST['member_id'] : 0);
        
        // 【核心安全檢查】檢查要刪除的 ID 是否大於 0，且「不能等於目前登入的使用者 ID」
        // 這能防止管理員不小心把自己刪除，導致再也進不來後台
        if ($member_id > 0 && $member_id != $_SESSION['user_id']) {
            try {
                $stmt = $pdo->prepare('DELETE FROM members WHERE id = ?');
                $stmt->execute([$member_id]);
                $message = '會員已刪除';
                $action = 'members';
            } catch (PDOException $e) {
                $error = '刪除失敗: ' . $e->getMessage();
            }
        } else {
            $error = '無法刪除您自己！';
        }
    }
    
// ==========================================
// 4. 新增文章分類 (Add Category)
// ==========================================
// 當管理員提交的表單動作（$post_action）剛好是 'add_category' 時，執行以下區塊
elseif ($post_action === 'add_category') {
    
    // 接收並清理前端傳來的分類名稱，用 trim() 拔除前後不小心的空格
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    
    // 接收並清理分類的詳細描述（選填），同樣用 trim() 清理空格
    $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
    
    // 【欄位檢查】分類名稱是必填項目，如果發現是空的（empty）
    if (empty($name)) { 
        // 儲存錯誤訊息，阻擋後續的資料庫寫入
        $error = '分類名稱不能為空'; 
    } else {
        // 欄位檢查通過，準備安全地寫入資料庫
        try {
            // 使用「?」佔位符準備 SQL 語法，防止 SQL 注入（SQL Injection）
            $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            
            // 執行 SQL 語法，並把真實的「分類名稱」與「分類描述」帶入對應的問號中
            $stmt->execute([$name, $description]);
            
            // 寫入成功，設定提示訊息
            $message = '分類已新增';
            
            // 【重要狀態切換】將當前的頁面動作（$action）切換為 'categories'
            // 這樣在程式碼後續的讀取區塊中，就會自動去撈取最新的分類列表，讓網頁直接秀出新分類
            $action = 'categories'; 
            
        } catch (PDOException $e) { 
            // 如果資料庫層面發生錯誤（例如：分類名稱設定了 UNIQUE 鍵且重複了），則捕捉異常並回報
            $error = '新增失敗: ' . $e->getMessage(); 
        }
    }
}
    
    // ==========================================
    // 5. 刪除文章分類 (Delete Category)
    // ==========================================
    elseif ($post_action === 'delete_category') {
        $cat_id = intval(isset($_POST['category_id']) ? $_POST['category_id'] : 0);
        if ($cat_id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
                $stmt->execute([$cat_id]);
                $message = '分類已刪除';
                $action = 'categories';
            } catch (PDOException $e) { 
                $error = '刪除失敗: ' . $e->getMessage(); 
            }
        }
    }
}

// 【第二大部分：為前端畫面準備資料 (根據 $action 讀取資料庫)】
// 無論有沒有經過上面的 POST 處理，最後都會來到這裡，依據當前的 $action 撈取對應的資料
$data = []; // 建立一個乾淨的容器陣列用來裝撈出來的資料

// 情況 A：管理員目前在看「分類管理」頁面
if ($action === 'categories') {
    try {
        // query() 用於不需要綁定參數的純查詢，依照分類名稱排序（A-Z / 中文編碼）
        $stmt = $pdo->query('SELECT * FROM categories ORDER BY name');
        $data['categories'] = $stmt->fetchAll(); // 把所有分類撈出來存進 $data
    } catch (PDOException $e) {
        $error = '讀取分類失敗: ' . $e->getMessage();
    }
} 
// 情況 B：管理員目前在看「會員管理列表」頁面
elseif ($action === 'members') {
    try {
        // 撈出所有會員，並依據加入時間由新到舊（降冪）排序
        $stmt = $pdo->query('SELECT * FROM members ORDER BY created_at DESC');
        $data['members'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = '讀取會員失敗: ' . $e->getMessage();
    }
} 
// 情況 C：管理員點擊了某個會員的「修改」按鈕
elseif ($action === 'edit_member') {
    // 從網址列獲取要修改的會員 ID (例如：admin.php?action=edit_member&id=5)
    $edit_id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
    try {
        $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
        $stmt->execute([$edit_id]);
        $data['edit_user'] = $stmt->fetch(); // 撈出該名會員的單筆資料
        
        // 安全機制：防範管理員在網址列亂打不存在的 ID
        if (!$data['edit_user']) {
            $error = '找不到該會員資料';
            $action = 'members'; // 找不到就強制轉回列表頁
        }
    } catch (PDOException $e) {
        $error = '讀取會員失敗: ' . $e->getMessage();
    }
} 
// 情況 D：預設狀況（也就是 $action === 'home'，後台儀表板主頁）
else {
    try {
        // 利用 COUNT(*) 快速計算整個網站的總體數據，用來在後台主頁顯示統計小卡
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM members');
        $data['member_count'] = $stmt->fetch()['count']; // 總會員數
        
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM news');
        $data['news_count'] = $stmt->fetch()['count']; // 總文章/公告數
        
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM replies');
        $data['reply_count'] = $stmt->fetch()['count']; // 總回覆數
        
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM categories');
        $data['category_count'] = $stmt->fetch()['count']; // 總分類數
    } catch (PDOException $e) {
        $error = '讀取統計資訊失敗: ' . $e->getMessage();
    }
}
?>



<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理員面板 - 討論區</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .container { display: grid; grid-template-columns: 200px 1fr; gap: 20px; max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .sidebar { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); height: fit-content; }
        .sidebar nav { list-style: none; }
        .sidebar nav li { margin-bottom: 10px; }
        .sidebar nav a { display: block; padding: 10px 15px; color: #667eea; text-decoration: none; border-radius: 5px; transition: background 0.3s; }
        .sidebar nav a:hover, .sidebar nav a.active { background: #f0f4ff; font-weight: bold; }
        .back-link { display: block; padding: 10px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; text-align: center; margin-top: 10px; transition: background 0.3s; }
        .back-link:hover { background: #5a6268; }
        .main-content { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 32px; font-weight: bold; margin-bottom: 5px; }
        .stat-label { font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th { background: #f0f4ff; padding: 12px; text-align: left; border-bottom: 2px solid #667eea; }
        table td { padding: 12px; border-bottom: 1px solid #eee; }
        table tr:hover { background: #f9f9f9; }
        .table-actions { display: flex; gap: 5px; }
        .btn-small { padding: 6px 12px; font-size: 12px; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #f0ad4e; color: white; }
        .empty { text-align: center; color: #999; padding: 20px; font-style: italic; }
        .admin-form-box { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #eee; }
        @media (max-width: 768px) { .container { grid-template-columns: 1fr; } .stats { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛡️ 管理員面板</h1>
    </div>

    <div class="container">
        <div class="sidebar">
            <h3>菜單</h3>
            <nav>
                <li><a href="?action=home" <?= $action == 'home' ? 'class="active"' : '' ?>>首頁</a></li>
                <li><a href="?action=members" <?= $action == 'members' ? 'class="active"' : '' ?>>會員管理</a></li>
                <li><a href="?action=categories" <?= $action == 'categories' ? 'class="active"' : '' ?>>分類管理</a></li>
            </nav>
            <a href="index.php" class="back-link">← 返回首頁</a>
        </div>

        <div class="main-content">
            <?php if ($message): ?>
                <div class="alert alert-success"><?= escape($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <?php if ($action === 'home'): ?>
                <h1>管理統計</h1>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-number"><?= $data['member_count'] ?></div>
                        <div class="stat-label">會員總數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $data['news_count'] ?></div>
                        <div class="stat-label">討論總數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $data['reply_count'] ?></div>
                        <div class="stat-label">回應總數</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $data['category_count'] ?></div>
                        <div class="stat-label">分類總數</div>
                    </div>
                </div>

            <?php elseif ($action === 'members'): ?>
                <h1>會員管理</h1>
                
                <div class="admin-form-box">
                    <h2>➕ 新增會員</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_member">
                        <div class="row row-2">
                            <div class="form-group">
                                <label>登入帳號</label>
                                <input type="text" name="username" required>
                            </div>
                            <div class="form-group">
                                <label>登入密碼</label>
                                <input type="password" name="password" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>顯示暱稱</label>
                            <input type="text" name="nickname" required>
                        </div>
                        <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" id="is_admin" name="is_admin" value="1" style="width:auto;">
                            <label for="is_admin" style="margin:0;">設為系統管理員</label>
                        </div>
                        <button type="submit" class="btn">新增會員</button>
                    </form>
                </div>

                <h2>👥 會員列表</h2>
                <table>
                    <thead>
                        <tr>
                            <th>帳號</th>
                            <th>暱稱</th>
                            <th>狀態</th>
                            <th>註冊時間</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data['members'])): ?>
                            <tr><td colspan="5" class="empty">沒有會員</td></tr>
                        <?php else: ?>
                            <?php foreach ($data['members'] as $member): ?>
                                <tr>
                                    <td><?= escape($member['username']) ?></td>
                                    <td><?= escape($member['nickname']) ?></td>
                                    <td>
                                        <?php if($member['is_admin']): ?>
                                            <span class="badge badge-primary">管理員</span>
                                        <?php else: ?>
                                            <span style="color:#666;">一般會員</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= escape($member['created_at']) ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="?action=edit_member&id=<?= $member['id'] ?>" class="btn btn-small btn-warning" style="text-decoration:none;">編輯</a>
                                            
                                            <?php if ($member['id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_member">
                                                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                    <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('確定要刪除此會員嗎？此操作無法恢復！')">刪除</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($action === 'edit_member'): ?>
                <h1>✏️ 編輯會員資料</h1>
                <div class="admin-form-box">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_member">
                        <input type="hidden" name="member_id" value="<?= $data['edit_user']['id'] ?>">
                        
                        <div class="form-group">
                            <label>登入帳號 (不可修改)</label>
                            <input type="text" value="<?= escape($data['edit_user']['username']) ?>" disabled style="background:#eee;">
                        </div>

                        <div class="form-group">
                            <label>暱稱</label>
                            <input type="text" name="nickname" value="<?= escape($data['edit_user']['nickname']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>重設密碼 (若不修改請留空)</label>
                            <input type="password" name="new_password" placeholder="輸入新密碼...">
                        </div>

                        <?php if ($data['edit_user']['id'] != $_SESSION['user_id']): ?>
                        <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" id="edit_is_admin" name="is_admin" value="1" <?= $data['edit_user']['is_admin'] ? 'checked' : '' ?> style="width:auto;">
                            <label for="edit_is_admin" style="margin:0; color:red; font-weight:bold;">設為系統管理員</label>
                        </div>
                        <?php else: ?>
                            <input type="hidden" name="is_admin" value="1">
                            <p style="color:red; font-size:12px;">(無法取消自己的管理員權限)</p>
                        <?php endif; ?>

                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn">儲存變更</button>
                            <a href="?action=members" class="btn btn-secondary" style="margin-left: 10px;">取消返回</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($action === 'categories'): ?>
                <h1>分類管理</h1>
                <div class="admin-form-box">
                    <h2>新增分類</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_category">
                        <div class="form-group">
                            <label for="name">分類名稱</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="description">描述</label>
                            <textarea id="description" name="description"></textarea>
                        </div>
                        <button type="submit" class="btn">新增分類</button>
                    </form>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>分類名稱</th>
                            <th>描述</th>
                            <th>建立時間</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data['categories'])): ?>
                            <tr><td colspan="4" class="empty">沒有分類</td></tr>
                        <?php else: ?>
                            <?php foreach ($data['categories'] as $cat): ?>
                                <tr>
                                    <td><?= escape($cat['name']) ?></td>
                                    <td><?= escape($cat['description']) ?></td>
                                    <td><?= escape($cat['created_at']) ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('確定要刪除此分類嗎？')">刪除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>