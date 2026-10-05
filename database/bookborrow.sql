CREATE DATABASE IF NOT EXISTS shelfwise_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shelfwise_db;

DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS history;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('member','staff','admin') NOT NULL DEFAULT 'member',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE books (
  book_id          INT AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(255) NOT NULL,
  author           VARCHAR(100) NOT NULL,
  isbn             VARCHAR(20) UNIQUE,
  publisher        VARCHAR(100),
  publication_date DATE,
  category         VARCHAR(50) DEFAULT 'General',
  total_copies     INT NOT NULL DEFAULT 1,
  available_copies INT NOT NULL DEFAULT 1,
  CHECK (available_copies >= 0 AND available_copies <= total_copies)
);

CREATE TABLE loans (
  loan_id     INT AUTO_INCREMENT PRIMARY KEY,
  book_id     INT NOT NULL,
  user_id     INT NOT NULL,
  borrowed_at DATETIME NOT NULL,
  due_at      DATETIME NOT NULL,
  returned_at DATETIME NULL,
  renewals    INT NOT NULL DEFAULT 0,
  status      ENUM('active','returned','cancelled') NOT NULL DEFAULT 'active',
  FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_loans_status (status, due_at),
  INDEX idx_loans_user_status (user_id, status)
);

CREATE TABLE reservations (
  reservation_id INT AUTO_INCREMENT PRIMARY KEY,
  book_id        INT NOT NULL,
  user_id        INT NOT NULL,
  position       INT NOT NULL,
  queued_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_book_user (book_id, user_id),
  FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_res_book_pos (book_id, position)
);

CREATE TABLE history (
  history_id INT AUTO_INCREMENT PRIMARY KEY,
  type       ENUM('borrow','renew','checkin','reserve','notify','undo') NOT NULL,
  book_id    INT NULL,
  user_id    INT NULL,   -- the affected member
  actor_id   INT NULL,   -- who performed the action
  note       VARCHAR(255),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (book_id)  REFERENCES books(book_id) ON DELETE SET NULL,
  FOREIGN KEY (user_id)  REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (actor_id) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_history_user (user_id, created_at),
  INDEX idx_history_type (type, created_at)
);

CREATE TABLE login_attempts (
  attempt_id   INT AUTO_INCREMENT PRIMARY KEY,
  ip           VARCHAR(45) NOT NULL,
  email        VARCHAR(100) NOT NULL,
  successful   TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempts_ip    (ip, attempted_at),
  INDEX idx_attempts_email (email, attempted_at)
);

-- ---------------------------------------------------------------
-- Demo accounts
--   Member — member@library.test / member123
--   Staff  — staff@library.test  / staff123
--   Admin  — admin@library.test  / admin123
-- ---------------------------------------------------------------
INSERT INTO users (user_id, name, email, password_hash, role) VALUES
  (1, 'Maria Santos', 'member@library.test', '$2y$10$pyuMaw9KMdNRx/0gvPrn7O46Zud6bBl8NHcSCIdH9KiWtZ0jsx0em', 'member'),
  (2, 'Liam Cruz',    'staff@library.test',  '$2y$10$0DBX9IvzfyTM/rMFn7MNQ.UlYp.FAfAAFHAbi9w9kYHYuD4Ylyitm', 'staff'),
  (3, 'Ada Reyes',    'admin@library.test',  '$2y$10$suni9sqEW1SIHa2d/lTcA.PcXmpgEEhdLFTdv0Gczpk9lU1aY5ZZO', 'admin');

-- ---------------------------------------------------------------
-- Books — full stock (available_copies = total_copies)
-- ---------------------------------------------------------------
INSERT INTO books (book_id, title, author, category, isbn, total_copies, available_copies) VALUES
  (1,  'Introduction to Algorithms',              'Cormen, Leiserson, Rivest', 'Computer Science', '978-0262046305', 4, 4),
  (2,  'Clean Code',                              'Robert C. Martin',          'Computer Science', '978-0132350884', 3, 3),
  (3,  'Data Structures and Algorithms in Java',  'Goodrich & Tamassia',       'Computer Science', '978-1118771334', 5, 5),
  (4,  'The Pragmatic Programmer',                'Hunt & Thomas',             'Computer Science', '978-0135957059', 2, 2),
  (5,  'Sapiens: A Brief History of Humankind',   'Yuval Noah Harari',         'History',          '978-0062316097', 3, 3),
  (6,  'The Art of War',                          'Sun Tzu',                   'History',          '978-1599869773', 2, 2),
  (7,  'Norwegian Wood',                          'Haruki Murakami',           'Fiction',          '978-0375704024', 3, 3),
  (8,  'One Hundred Years of Solitude',           'Gabriel Garcia Marquez',    'Fiction',          '978-0060883287', 2, 2),
  (9,  'Thinking, Fast and Slow',                 'Daniel Kahneman',           'Psychology',       '978-0374533557', 4, 4),
  (10, 'Calculus: Early Transcendentals',         'James Stewart',             'Mathematics',      '978-1285741550', 6, 6),
  (11, 'Discrete Mathematics and Its Applications','Kenneth H. Rosen',          'Mathematics',      '978-1259676512', 4, 4),
  (12, 'Atomic Habits',                           'James Clear',               'Psychology',       '978-0735211292', 5, 5);

-- ---------------------------------------------------------------
-- Loans / reservations / history: intentionally empty.
-- Every title is fully in stock (43 / 43 copies on shelf).
-- ---------------------------------------------------------------

-- Least-privilege app user. Change the password before deploying.
-- CREATE USER IF NOT EXISTS 'library_app'@'localhost' IDENTIFIED BY 'CHANGE_ME';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON shelfwise_db.* TO 'library_app'@'localhost';
-- FLUSH PRIVILEGES;