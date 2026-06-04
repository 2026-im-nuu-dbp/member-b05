-- Members table (must be created first for foreign key references)
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nickname VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#FFFFFF',
    avatar VARCHAR(100),
    is_admin TINYINT(1) DEFAULT 0,
    profile_complete TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table for discussion topics
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Main news table
CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    member_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_created_at (created_at),
    INDEX idx_member_id (member_id),
    INDEX idx_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Replies table
CREATE TABLE replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    news_id INT NOT NULL,
    content TEXT NOT NULL,
    member_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_news_id (news_id),
    INDEX idx_member_id (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO categories (name, description) VALUES 
('一般討論', '任何與本平台相關的一般討論'),
('問題求助', '尋求幫助和解決問題的地方'),
('公告', '平台重要公告和消息'),
('建議反饋', '提供建議和反饋意見'),
('活動', '討論各類活動和事件');

-- 1. 建立預設管理員帳號 
-- (登入帳號：admin / 登入密碼：password)
INSERT INTO members (id, username, password, nickname, color, avatar, is_admin, profile_complete) VALUES 
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系統管理員', '#667eea', '😎', 1, 1);

-- 2. 建立各分類的預設測試文章
INSERT INTO news (category_id, title, content, member_id, created_at) VALUES 
-- 一般討論
(1, '大家平時都用什麼編輯器寫程式？', '最近在考慮要不要從 VS Code 換到其他編輯器，想聽聽大家的建議與使用心得！', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, '分享一個好用的 CSS 漸層產生器', '今天發現一個超棒的網站，可以直接視覺化調整漸層並複製 CSS 程式碼，推薦給大家試試看。', 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),

-- 問題求助
(2, '【發問】PHP session 無法跨頁面存取的問題', '各位好，我在 login.php 設置了 session，但是跳轉到 index.php 後 session 就消失了，有人遇過這種情況嗎？', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 'CSS Flexbox 排版跑版問題', '當子元素內容太長的時候，flex 容器就會被撐破，有沒有什麼屬性可以強制讓它換行或是限制寬度？', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),

-- 公告
(3, '【系統公告】論壇將於本週日凌晨進行維護', '為了提供更好的服務品質，本討論區將於 5/17 (日) 凌晨 02:00 - 04:00 進行系統升級，期間將暫停服務，造成不便敬請見諒。', 1, DATE_SUB(NOW(), INTERVAL 6 DAY)),

-- 建議反饋
(4, '建議可以新增「文章按讚」的功能', '論壇目前整體用起來很順暢！但如果能加入按讚或愛心功能，感覺大家互動會更熱絡一點～', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- 活動
(5, '🎉 第一屆線上程式黑客松報名開始！', '歡迎大家組隊參加今年的線上黑客松，主題為「生活中的小幫手」，獲勝隊伍將有豐厚獎品喔！詳情請見內文。', 1, NOW()),
(5, '週末技術分享會：React 入門實戰', '這週六下午將舉辦一場針對新手的 React 實戰講座，採 Google Meet 線上進行，有興趣的同學請在下方留言+1！', 1, NOW());