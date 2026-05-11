<?php
header('Content-Type: text/html; charset=utf-8');
require 'db_config.php';
require 'auth.php';

$news_id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
if ($news_id <= 0) die('無效的討論 ID。<br><a href="index.php">返回首頁</a>');

// 处理发表回应
$msg = '';
if (is_logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = substr(trim(isset($_POST['content']) ? $_POST['content'] : ''), 0, 10000);
    if ($content) {
        try {
            $stmt = $pdo->prepare('INSERT INTO replies (news_id, content, member_id) VALUES (?, ?, ?)');
            $stmt->execute([$news_id, $content, $_SESSION['user_id']]);
            $msg = '✓ 回應已發表';
        } catch (PDOException $e) {
            $msg = '✗ 發表失敗';
        }
    } else {
        $msg = '✗ 回應內容不能為空';
    }
}

try {
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
    $news = $stmt->fetch();
    if (!$news) die('找不到此討論。<br><a href="index.php">返回首頁</a>');
    
    $stmt = $pdo->prepare('
        SELECT r.id, r.content, r.created_at,
               m.nickname, m.avatar, m.color
        FROM replies r
        LEFT JOIN members m ON r.member_id = m.id
        WHERE r.news_id = ? 
        ORDER BY r.created_at ASC
    ');
    $stmt->execute([$news_id]);
    $replies = $stmt->fetchAll();
} catch (PDOException $e) {
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
