<?php
// Insert reply into database

header('Content-Type: text/html; charset=utf-8');
require 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method.');
}

$newsId = isset($_POST['news_id']) ? intval($_POST['news_id']) : 0;
$author = isset($_POST['author']) ? trim($_POST['author']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

// Validation
if ($newsId <= 0) {
    die('無效的討論 ID。<br><a href="index.php">返回</a>');
}

if (empty($author) || empty($content)) {
    die('所有欄位都必須填寫。<br><a href="show_news.php?id=' . $newsId . '">返回</a>');
}

// Check if news exists
try {
    $stmt = $pdo->prepare('SELECT id FROM news WHERE id = ?');
    $stmt->execute([$newsId]);
    if (!$stmt->fetch()) {
        die('找不到此討論。<br><a href="index.php">返回首頁</a>');
    }
} catch (PDOException $e) {
    die('驗證失敗: ' . $e->getMessage());
}

// Limit input length
$author = substr($author, 0, 100);
$content = substr($content, 0, 10000);

try {
    $stmt = $pdo->prepare('INSERT INTO replies (news_id, content, author) VALUES (?, ?, ?)');
    $stmt->execute([$newsId, $content, $author]);

    // Redirect back to discussion page
    header('Location: show_news.php?id=' . $newsId);
    exit;
} catch (PDOException $e) {
    die('發表回應失敗: ' . $e->getMessage() . '<br><a href="show_news.php?id=' . $newsId . '">返回</a>');
}
