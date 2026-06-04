[![Review Assignment Due Date](https://classroom.github.com/assets/deadline-readme-button-22041afd0340ce965d47ae6ef1cefeee28c7c493a6346c4f15d667ab976d596c.svg)](https://classroom.github.com/a/JkTWvd7s)
# 作業 7 會員管理系統 集合群組討論應用

## 說明
1.	以群組討論應用程式為基礎，增加會員機制：
    * 註冊功能，欄位有帳號、密碼、暱稱、喜歡顏色及大頭貼(圖案)。
    * 登入登出密碼檢查
    * 在討論區，名稱改以登入者的暱稱，名稱之後有一個大頭貼，(選項)留言欄背景以所選的色彩呈現。
    * 有管理員介面，可以新增刪除修改會員資料。
2.	請降低程式碼複雜度，不必要的程式碼盡量不出現。
3.	請附上完整的資料庫匯出 .sql 檔案。
4. 請以所附的程式碼為基礎進行增修	

## 注意事項
1. 分組的每一位同學至少需要進行一次 commit push 
2. demo 時每位同學須找一個部份來說明

## 實現功能

### 1. 會員系統
- **註冊功能** (`register.php`)
  - 帳號至少3個字符
  - 密碼至少6個字符
  - 密碼驗證確認
  - 帳號唯一性驗證

- **首次登入設置** (`setup_profile.php`)
  - 設定暱稱
  - 選擇大頭貼（15種emoji可選）
  - 選擇喜愛的顏色（10種色系可選）

- **登入登出** (`login.php`, `logout.php`)
  - 密碼驗證（使用password_hash/password_verify）
  - Session管理
  - 自動導向首次登入設置頁面

### 2. 討論區功能 (`index.php`, `show_news.php`, `post.php`, `post_reply.php`)
- **討論列表**
  - 支援多分類篩選
  - 顯示發表者昵稱和大頭貼
  - 顯示回應數量
  - 依時間排序

- **發表討論**
  - 選擇分類
  - 需要登入且完成檔案設置
  - 支援標題和內容

- **回應討論**
  - 顯示回應者昵稱、大頭貼和顏色背景
  - 按時間順序顯示
  - 未登入提示登入

### 3. 會員資料編輯系統 (`edit_profile.php`)
- **編輯檔案**
  - 修改暱稱
  - 更換大頭貼
  - 更換喜愛的顏色

- **變更密碼**
  - 舊密碼驗證
  - 新密碼確認
  - 密碼強度要求

### 4. 管理員系統 (`admin_panel.php`)
- **會員管理**
  - 列表所有會員
  - 設定/移除管理員權限
  - 刪除會員

- **分類管理**
  - 新增分類
  - 刪除分類
  - 管理員介面只有管理員可訪問

- **統計資訊**
  - 會員總數
  - 討論總數
  - 回應總數
  - 分類總數

## 文件說明

| 檔案 | 功能 |
|-----|------|
| `auth.php` | 認證和授權函數 |
| `db_config.php` | 資料庫連接配置 |
| `register.php` | 會員註冊頁面 |
| `login.php` | 會員登入頁面 |
| `logout.php` | 會員登出功能 |
| `setup_profile.php` | 首次登入檔案設置 |
| `edit_profile.php` | 會員編輯自己的檔案 |
| `index.php` | 討論區首頁和列表 |
| `show_news.php` | 討論詳情和回應顯示 |
| `post.php` | 發表新討論 |
| `post_reply.php` | 回應討論 |
| `admin_panel.php` | 管理員面板 |
| `news.sql` | 資料庫架構和初始數據 |

## 資料庫設計

### Members 表
- 儲存會員帳號、密碼、昵稱、顏色、頭像
- 包含管理員權限和檔案完成狀態標記

### News 表
- 儲存討論主題
- 外鍵連接 members 和 categories 表

### Replies 表
- 儲存回應內容
- 外鍵連接 news 和 members 表

### Categories 表
- 儲存討論分類
- 包含初始5個分類

## 使用流程

1. **新會員註冊**
   - 進入 register.php
   - 輸入帳號和密碼
   - 點擊註冊按鈕

2. **首次登入**
   - 進入 login.php
   - 輸入帳號和密碼
   - 自動轉到 setup_profile.php 設置檔案

3. **發表討論**
   - 在首頁登入後可見發表表單
   - 選擇分類、輸入標題和內容
   - 點擊發表

4. **參與討論**
   - 點擊討論主題查看詳情
   - 在回應區輸入內容並發表

5. **管理員操作**
   - 以管理員帳號登入
   - 進入導覽列的管理員連結
   - 管理會員和分類

## 密碼安全性

- 使用 `password_hash()` 加密儲存密碼
- 使用 `password_verify()` 驗證密碼
- 採用 PASSWORD_DEFAULT 算法（目前為 bcrypt）

## 權限控制

- `is_logged_in()` - 檢查是否登入
- `require_login()` - 要求登入，否則重導到登入頁
- `require_profile_complete()` - 檢查檔案是否完成設置
- `require_admin()` - 檢查管理員權限

