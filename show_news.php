<?php
// 設定 HTTP 標頭，指定內容為 HTML 且編碼為 UTF-8
header('Content-Type: text/html; charset=utf-8');

// 引入資料庫設定檔與驗證函式庫
require 'db_config.php';
require 'auth.php';

// 【安全與格式檢查】
// 從網址列（GET）獲取想瀏覽的文章 ID（例如：news_detail.php?id=12）
// intval() 強制轉成整數。若沒傳入 ID 則預設為 0
$news_id = intval(isset($_GET['id']) ? $_GET['id'] : 0);

// 如果 ID 小於或等於 0（代表是不合法的網址或惡意輸入），立刻中斷程式並輸出錯誤訊息
if ($news_id <= 0) die('無效的討論 ID。<br><a href="index.php">返回首頁</a>');

// 初始化提示訊息（不論是發表成功或失敗）
$msg = '';

// =========================================================================
// 區塊一：處理「發表回應」的表單提交 (POST 請求)
// =========================================================================
// 必須同時滿足：1. 使用者已經登入 (is_logged_in()) 2. 瀏覽器發送的是 POST 請求
if (is_logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 接收回應內容、去除首尾空白。
    // substr(..., 0, 10000) 是一個很棒的防禦設計：強制截斷字串，最多只收 10000 個字，防止資料庫爆失控
    $content = substr(trim(isset($_POST['content']) ? $_POST['content'] : ''), 0, 10000);
    
    // 如果處理完後，內容不是空的
    if ($content) {
        try {
            // 準備寫入留言資料表（欄位包括：屬於哪篇文章、留言內容、是哪位會員留的）
            $stmt = $pdo->prepare('INSERT INTO replies (news_id, content, member_id) VALUES (?, ?, ?)');
            
            // 執行並綁定參數。會員 ID 來自當前安全的 Session 機制
            $stmt->execute([$news_id, $content, $_SESSION['user_id']]);
            $msg = '✓ 回應已發表';
        } catch (PDOException $e) {
            $msg = '✗ 發表失敗'; // 資料庫出錯時提示
        }
    } else {
        // 如果使用者什麼都沒打就按送出
        $msg = '✗ 回應內容不能為空';
    }
}

// =========================================================================
// 區塊二：撈取網頁要顯示的資料 (主貼文內容 + 所有留言)
// =========================================================================
try {
    // 【步驟 1】撈取「主貼文資料」
    // 使用 LEFT JOIN 語法，同時把發文者的暱稱/頭像、以及這篇文章所屬的分類名稱一次撈出來
    $stmt = $pdo->prepare('
        SELECT n.id, n.title, n.content, n.created_at, 
               m.nickname, m.avatar, m.color,
               c.name as category_name
        FROM news n
        LEFT JOIN members m ON n.member_id = m.id
        LEFT JOIN categories c ON n.category_id = c.id
        WHERE n.id = ?
    ');
    $stmt->execute([$news_id]);
    $news = $stmt->fetch(); // 獲取單筆文章資料
    
    // 如果在資料庫根本找不到這個 ID 的文章，立刻中斷並噴出錯誤
    if (!$news) die('找不到此討論。<br><a href="index.php">返回首頁</a>');
    
    // 【步驟 2】撈取「該文章底下的所有回應 (Replies)」
    // 同樣用 LEFT JOIN 串接會員資料表，這樣才能知道每一則留言是「誰」留的
    // ORDER BY ... ASC 代表留言依照時間由舊到新排序（符合一般論壇由上往下閱讀的習慣）
    $stmt = $pdo->prepare('
        SELECT r.id, r.content, r.created_at,
               m.nickname, m.avatar, m.color
        FROM replies r
        LEFT JOIN members m ON r.member_id = m.id
        WHERE r.news_id = ? 
        ORDER BY r.created_at ASC
    ');
    $stmt->execute([$news_id]);
    $replies = $stmt->fetchAll(); // 撈取所有留言陣列
    
} catch (PDOException $e) {
    // 整個資料庫讀取流程只要有任何一處出錯，就會被這裡捕捉
    die('讀取討論失敗');
}
?>


<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($news['title']) ?> - 討論區</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .news-title {
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        .news-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        .news-body {
            line-height: 1.8;
            color: #333;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .reply-item {
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        .reply-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        .reply-author {
            font-weight: bold;
            color: #333;
        }
        .reply-time {
            font-size: 12px;
            color: #999;
            margin-left: auto;
        }
        .reply-content {
            margin-top: 8px;
            line-height: 1.6;
            color: #333;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .empty {
            text-align: center;
            color: #999;
            padding: 30px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>討論詳情</h1>
    </div>

    <div class="container">
        <a href="index.php" class="back-link">← 返回討論列表</a>

        <div class="news-content">
            <div class="news-title"><?= escape($news['title']) ?></div>
            <div class="news-meta">
                <span class="author-info">
                    <span class="avatar"><?= escape($news['avatar']) ?></span>
                    <strong class="reply-author"><?= escape($news['nickname']) ?></strong>
                </span>
                <span><?= escape($news['created_at']) ?></span>
                <?php if ($news['category_name']): ?>
                    <span class="category-badge"><?= escape($news['category_name']) ?></span>
                <?php endif; ?>
            </div>
            <div class="news-body"><?= escape($news['content']) ?></div>
        </div>

        <div class="reply-section">
            <h2>回應 (<?= count($replies) ?>)</h2>

            <?php if (empty($replies)): ?>
                <p class="empty">目前沒有回應。</p>
            <?php else: ?>
                <?php foreach ($replies as $reply): ?>
                    <div class="reply-item" style="background-color: <?= $reply['color'] ? $reply['color'] . '20' : '#f9f9f9' ?>; border-left-color: <?= $reply['color'] ? $reply['color'] : '#667eea' ?>;">
                        <div class="reply-header">
                            <span class="avatar"><?= escape($reply['avatar']) ?></span>
                            <span class="reply-author"><?= escape($reply['nickname']) ?></span>
                            <span class="reply-time"><?= escape($reply['created_at']) ?></span>
                        </div>
                        <div class="reply-content">
                            <?= escape($reply['content']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="form-box">
            <h2>發表回應</h2>
            
            <?php if ($msg): ?>
                <div style="padding:10px;margin-bottom:15px;background:<?=$msg[0]==='✓'?'#e8f5e9':'#ffebee'?>;color:<?=$msg[0]==='✓'?'#2e7d32':'#c62828'?>;border-radius:5px;text-align:center"><?=$msg?></div>
            <?php endif; ?>

            <?php if (!is_logged_in()): ?>
                <div class="login-prompt">
                    請 <a href="login.php">登入</a> 或 <a href="register.php">註冊</a> 後才能發表回應
                </div>
            <?php else: ?>
                <form method="post">
                    <div class="form-group">
                        <label for="content">回應內容：</label>
                        <textarea id="content" name="content" required placeholder="輸入您的回應..."></textarea>
                    </div>
                    <button type="submit">送出回應</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
