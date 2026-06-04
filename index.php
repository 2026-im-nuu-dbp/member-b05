<?php
// 設定 HTTP 標頭，指定內容為 HTML 且編碼為 UTF-8
header('Content-Type: text/html; charset=utf-8');

// 引入資料庫設定檔與驗證函式庫
require 'db_config.php';
require 'auth.php';

// =========================================================================
// 1. 快捷登出處理 (Logout)
// =========================================================================
// 如果網址列帶有 ?action=logout (例如: index.php?action=logout)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy(); // 銷毀伺服器上的 Session 紀錄
    header('Location: index.php'); // 重新導向回乾淨的首頁
    exit; // 切斷後續程式執行
}

// 初始化提示訊息變數
$msg = '';

// =========================================================================
// 2. 處理發表新討論貼文 (Create Thread)
// =========================================================================
// 必須同時滿足：1. 使用者已登入、2. 瀏覽器發送的是 POST 發文表單
if (is_logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 接收並清理表單資料
    $cat = intval(isset($_POST['category_id']) ? $_POST['category_id'] : 0); // 所選分類 ID
    $title = substr(trim(isset($_POST['title']) ? $_POST['title'] : ''), 0, 200); // 標題，限制最大 200 字
    $content = substr(trim(isset($_POST['content']) ? $_POST['content'] : ''), 0, 10000); // 內文，限制最大 10000 字
    
    // 安全防空檢查：分類必須大於 0，且標題與內容不能為空
    if ($cat > 0 && $title && $content) {
        try {
            // 【安全雙重檢查】先去資料庫查查看這個分類 ID 是否真的存在（防止駭客竄改網頁表單數值）
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
            $stmt->execute([$cat]);
            
            // 如果該分類確實存在
            if ($stmt->fetch()) {
                // 執行新增貼文，記錄分類、標題、內文與發文者（從 Session 取得 user_id）
                $stmt = $pdo->prepare('INSERT INTO news (category_id, title, content, member_id) VALUES (?, ?, ?, ?)');
                $stmt->execute([$cat, $title, $content, $_SESSION['user_id']]);
                $msg = '✓ 討論已發表';
            }
        } catch (PDOException $e) {
            $msg = '✗ 發表失敗';
        }
    } else {
        $msg = '✗ 請填寫所有欄位';
    }
}

// 獲取當前登入者的完整資料（用來在首頁歡迎某某某，或控制是否顯示發文表單），未登入則給空陣列
$user = is_logged_in() ? get_current_user() : [];

// 【分類篩選記憶】從網址列抓取使用者點選了哪個分類（例如：index.php?category=3），沒選預設為 0（看全部文章）
$selected_category = intval(isset($_GET['category']) ? $_GET['category'] : 0);

// =========================================================================
// 3. 讀取首頁所需資料 (分類側邊欄 + 動態文章列表)
// =========================================================================
try {
    // 【步驟 A】撈取所有的分類，用來渲染首頁的「分類導覽側邊欄」或發文的「下拉選單」
    $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name');
    $categories = $stmt->fetchAll();
    
    // 【步驟 B】動態構建貼文列表的 SQL 語法
    // 先寫好共用的前半段 SQL：
    // 這裡用了 3 個 LEFT JOIN 來串接留言表、會員表、分類表，並用 COUNT(r.id) 來計算每篇文章的總回覆數
    $sql = 'SELECT n.id, n.title, c.name as category_name, m.nickname, m.avatar, m.color, n.created_at, COUNT(r.id) as reply_count
            FROM news n
            LEFT JOIN replies r ON n.id = r.news_id
            LEFT JOIN members m ON n.member_id = m.id
            LEFT JOIN categories c ON n.category_id = c.id';

    $params = []; // 準備一個空口袋，用來裝安全綁定的參數

    // 【動態拼接條件】如果使用者有選取



<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>討論區</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 28px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .avatar-display {
            font-size: 28px;
        }
        .user-name {
            font-size: 14px;
        }
        .nav-links {
            display: flex;
            gap: 10px;
        }
        .nav-links a {
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .nav-links a:hover {
            background: rgba(255,255,255,0.3);
        }
        .row {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
        }
        .sidebar {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: fit-content;
        }
        .sidebar h3 {
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .category-list {
            list-style: none;
        }
        .category-list li {
            margin-bottom: 8px;
        }
        .category-list a {
            display: block;
            padding: 8px 12px;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .category-list a:hover,
        .category-list a.active {
            background: #f0f4ff;
            font-weight: bold;
        }
        .main-content {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .post-form {
            padding: 20px;
            background: #f9f9f9;
            border-bottom: 1px solid #eee;
        }
        .post-form h2 {
            margin-bottom: 15px;
            font-size: 20px;
            color: #333;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .news-list {
            max-height: 800px;
            overflow-y: auto;
        }
        .news-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }
        .news-item:hover {
            background: #fafafa;
        }
        .news-item:last-child {
            border-bottom: none;
        }
        .news-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        .news-title {
            font-size: 16px;
            font-weight: bold;
        }
        .news-title a {
            color: #667eea;
            text-decoration: none;
        }
        .news-title a:hover {
            text-decoration: underline;
        }
        .news-meta {
            font-size: 12px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .author-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .reply-count {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .empty {
            text-align: center;
            color: #999;
            padding: 40px;
            font-style: italic;
        }
        @media (max-width: 768px) {
            .row {
                grid-template-columns: 1fr;
            }
            .header-content {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            .nav-links {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php
    // 設置 header 背景色和風格
    $header_style = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
    if (is_logged_in() && isset($user['color']) && $user['color']) {
        $header_color = escape($user['color']);
        // 根據使用者設定的顏色生成漸層效果
        $header_style = 'linear-gradient(135deg, ' . $header_color . ' 0%, ' . $header_color . ' 100%)';
    }
    ?>
    <div class="header" style="background: <?= $header_style ?>; color: white;">
        <div class="header-content">
            <h1>📋 討論區</h1>
            <div class="user-info">
                <?php if (is_logged_in()): ?>
                    <!-- <span class="avatar-display" style="
                        background: #fff; 
                        border: 4px solid <?= isset($user['color']) ? escape($user['color']) : '#fff' ?>; 
                        border-radius: 50%; 
                        width: 50px; 
                        height: 50px; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        font-size: 32px; 
                        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                    ">
                        <?= isset($user['avatar']) && $user['avatar'] ? escape($user['avatar']) : '😀' ?>
                    </span>
                    
                    <div class="user-name" style="color: white; text-shadow: 1px 1px 2px rgba(0,0,0,0.2);">
                        <strong style="font-size: 16px;"><?= isset($user['nickname']) ? escape($user['nickname']) : 'User' ?></strong><br>
                        <small style="opacity: 0.8;">@<?= isset($user['username']) ? escape($user['username']) : '' ?></small>
                    </div> -->

                    <div class="nav-links">
                        <a href="edit_profile.php">編輯檔案</a>
                        <?php if (is_admin()): ?>
                            <a href="admin_panel.php">管理員</a>
                        <?php endif; ?>
                        <a href="logout.php">登出</a>
                    </div>
                <?php else: ?>
                    <div class="nav-links">
                        <a href="login.php">登入</a>
                        <a href="register.php">註冊</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="sidebar">
                <h3>分類</h3>
                <ul class="category-list">
                    <li><a href="index.php" <?= $selected_category == 0 ? 'class="active"' : '' ?>>全部討論</a></li>
                    <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="?category=<?= $cat['id'] ?>" 
                               <?= $selected_category == $cat['id'] ? 'class="active"' : '' ?>>
                                <?= escape($cat['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="main-content">
                <?php if (is_logged_in()): ?>
                    <div class="post-form">
                        <h2>發表新討論</h2>
                        <form action="index.php" method="POST">
                            <div class="form-group">
                                <label for="category_id">分類：</label>
                                <select id="category_id" name="category_id" required>
                                    <option value="">請選擇分類</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= escape($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="title">標題：</label>
                                <input type="text" id="title" name="title" maxlength="200" required>
                            </div>
                            <div class="form-group">
                                <label for="content">內容：</label>
                                <textarea id="content" name="content" required></textarea>
                            </div>
                            <button type="submit" class="btn">發表討論</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="news-list">
    <?php if (empty($news)): ?>
        <p class="empty">目前沒有討論。</p>
    <?php else: ?>
        <?php foreach ($news as $item): ?>
            <div class="news-item" style="background-color: <?= isset($item['color']) && $item['color'] ? escape($item['color']) . '15' : '#ffffff' ?>; border-left: 4px solid <?= isset($item['color']) && $item['color'] ? escape($item['color']) : '#eee' ?>;">
                
                <div class="news-header">
                    <div style="flex-grow: 1;">
                        <div class="news-title" style="display: flex; align-items: center; gap: 8px;">
                            
                            <?php if ($item['category_name']): ?>
                                <span class="category-badge" style="background-color: <?= isset($item['color']) && $item['color'] ? escape($item['color']) : '#667eea' ?>; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: normal; white-space: nowrap;">
                                    <?= escape($item['category_name']) ?>
                                </span>
                            <?php endif; ?>
                            
                            <a href="show_news.php?id=<?= $item['id'] ?>">
                                <?= escape($item['title']) ?>
                            </a>
                        </div>
                    </div>
                    <?php if ($item['reply_count'] > 0): ?>
                        <span class="reply-count" style="white-space: nowrap; margin-left: 10px;"><?= $item['reply_count'] ?> 則</span>
                    <?php endif; ?>
                </div>

                <div class="news-meta">
                    <span class="author-info">
                        <span style="
                            display: inline-flex; 
                            align-items: center; 
                            justify-content: center; 
                            width: 28px; 
                            height: 28px; 
                            border: 2px solid <?= isset($item['color']) && $item['color'] ? escape($item['color']) : '#ccc' ?>; 
                            border-radius: 50%; 
                            background: #fff;
                            font-size: 16px;
                        ">
                            <?= isset($item['avatar']) && $item['avatar'] ? escape($item['avatar']) : '😀' ?>
                        </span>
                        <strong><?= isset($item['nickname']) ? escape($item['nickname']) : '匿名' ?></strong>
                    </span>
                    <span><?= isset($item['created_at']) ? escape($item['created_at']) : '' ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
            </div>
        </div>
    </div>
</body>
</html>
