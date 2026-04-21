<?php
// Read and display discussion topics

header('Content-Type: text/html; charset=utf-8');
require 'db_config.php';

// Fetch all news topics with reply count
try {
    $stmt = $pdo->query('
        SELECT n.id, n.title, n.author, n.created_at,
               COUNT(r.id) as reply_count
        FROM news n
        LEFT JOIN replies r ON n.id = r.news_id
        GROUP BY n.id, n.title, n.author, n.created_at
        ORDER BY n.created_at DESC
    ');
    $news = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = '讀取討論失敗: ' . $e->getMessage();
    $news = [];
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <title>討論區</title>
    <style>
        body {
            font-family: system-ui, -apple-system, Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .form-box {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            box-sizing: border-box;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        button {
            background: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #0056b3;
        }
        .news-list {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .news-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        .news-item:last-child {
            border-bottom: none;
        }
        .news-item:hover {
            background: #f9f9f9;
        }
        .news-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .news-title a {
            color: #007bff;
            text-decoration: none;
        }
        .news-title a:hover {
            text-decoration: underline;
        }
        .news-meta {
            font-size: 14px;
            color: #666;
        }
        .reply-count {
            display: inline-block;
            background: #28a745;
            color: #fff;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
        .empty {
            text-align: center;
            color: #999;
            padding: 40px;
            font-style: italic;
        }
        .error {
            color: #d32f2f;
            padding: 10px;
            background: #ffebee;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 討論區</h1>

        <?php if (isset($error)): ?>
            <div class="error"><?= escape($error) ?></div>
        <?php endif; ?>

        <div class="form-box">
            <h2>發表新討論</h2>
            <form action="post.php" method="post">
                <div class="form-group">
                    <label for="author">作者：</label>
                    <input type="text" id="author" name="author" maxlength="100" required>
                </div>

                <div class="form-group">
                    <label for="title">標題：</label>
                    <input type="text" id="title" name="title" maxlength="200" required>
                </div>

                <div class="form-group">
                    <label for="content">內容：</label>
                    <textarea id="content" name="content" required></textarea>
                </div>

                <button type="submit">發表討論</button>
            </form>
        </div>

        <h2>討論列表</h2>

        <?php if (empty($news)): ?>
            <div class="news-list">
                <p class="empty">目前沒有討論。</p>
            </div>
        <?php else: ?>
            <div class="news-list">
                <?php foreach ($news as $item): ?>
                    <div class="news-item">
                        <div class="news-title">
                            <a href="show_news.php?id=<?= $item['id'] ?>">
                                <?= escape($item['title']) ?>
                            </a>
                            <?php if ($item['reply_count'] > 0): ?>
                                <span class="reply-count">
                                    <?= $item['reply_count'] ?> 則回應
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="news-meta">
                            由 <strong><?= escape($item['author']) ?></strong> 發表於
                            <?= escape($item['created_at']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
