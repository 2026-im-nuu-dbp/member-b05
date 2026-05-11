<?php
header('Content-Type: text/html; charset=utf-8');
require 'db_config.php';
require 'auth.php';

require_admin();

function escape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

$action = isset($_GET['action']) ? $_GET['action'] : 'home';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = isset($_POST['action']) ? $_POST['action'] : '';

    // ==========================================
    // 1. 新增會員 (Add Member)
    // ==========================================
    if ($post_action === 'add_member') {
        $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $nickname = trim(isset($_POST['nickname']) ? $_POST['nickname'] : '');
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        if (empty($username) || empty($password) || empty($nickname)) {
            $error = '請填寫所有必填欄位';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id FROM members WHERE username = ?');
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = '新增失敗：帳號已存在';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    // 預設給予基礎顏色與頭像，並標記 profile_complete = 1 讓其不必強制走設定流程
                    $stmt = $pdo->prepare('INSERT INTO members (username, password, nickname, is_admin, profile_complete, color, avatar) VALUES (?, ?, ?, ?, 1, "#667eea", "😀")');
                    $stmt->execute([$username, $hashed, $nickname, $is_admin]);
                    $message = '會員已成功新增！';
                    $action = 'members';
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
        $member_id = intval(isset($_POST['member_id']) ? $_POST['member_id'] : 0);
        $nickname = trim(isset($_POST['nickname']) ? $_POST['nickname'] : '');
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;

        if ($member_id > 0 && !empty($nickname)) {
            try {
                if (!empty($new_password)) { // 如果有輸入新密碼，就連密碼一起改
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE members SET nickname = ?, password = ?, is_admin = ? WHERE id = ?');
                    $stmt->execute([$nickname, $hashed, $is_admin, $member_id]);
                } else { // 沒輸入密碼就只改其他資料
                    $stmt = $pdo->prepare('UPDATE members SET nickname = ?, is_admin = ? WHERE id = ?');
                    $stmt->execute([$nickname, $is_admin, $member_id]);
                }
                $message = '會員資料已更新！';
                $action = 'members';
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
    // 分類管理的 POST 行為保留
    elseif ($post_action === 'add_category') {
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
        if (empty($name)) { $error = '分類名稱不能為空'; } 
        else {
            try {
                $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
                $stmt->execute([$name, $description]);
                $message = '分類已新增';
                $action = 'categories';
            } catch (PDOException $e) { $error = '新增失敗: ' . $e->getMessage(); }
        }
    } elseif ($post_action === 'delete_category') {
        $cat_id = intval(isset($_POST['category_id']) ? $_POST['category_id'] : 0);
        if ($cat_id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
                $stmt->execute([$cat_id]);
                $message = '分類已刪除';
                $action = 'categories';
            } catch (PDOException $e) { $error = '刪除失敗: ' . $e->getMessage(); }
        }
    }
}

$data = [];
if ($action === 'categories') {
    try {
        $stmt = $pdo->query('SELECT * FROM categories ORDER BY name');
        $data['categories'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = '讀取分類失敗: ' . $e->getMessage();
    }
} elseif ($action === 'members') {
    try {
        $stmt = $pdo->query('SELECT * FROM members ORDER BY created_at DESC');
        $data['members'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = '讀取會員失敗: ' . $e->getMessage();
    }
} elseif ($action === 'edit_member') {
    $edit_id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
    try {
        $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
        $stmt->execute([$edit_id]);
        $data['edit_user'] = $stmt->fetch();
        if (!$data['edit_user']) {
            $error = '找不到該會員資料';
            $action = 'members';
        }
    } catch (PDOException $e) {
        $error = '讀取會員失敗: ' . $e->getMessage();
    }
} else {
    try {
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM members');
        $data['member_count'] = $stmt->fetch()['count'];
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM news');
        $data['news_count'] = $stmt->fetch()['count'];
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM replies');
        $data['reply_count'] = $stmt->fetch()['count'];
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM categories');
        $data['category_count'] = $stmt->fetch()['count'];
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