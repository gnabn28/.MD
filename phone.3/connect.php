<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "cellphone_k_2";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Không kết nối được với MySQL: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Đảm bảo đồng bộ múi giờ giữa PHP và MySQL (khắc phục lỗi OTP)
date_default_timezone_set('Asia/Ho_Chi_Minh');
$conn->query("SET time_zone = '+07:00'");

// =========================================
// AUTO-MIGRATE: Tạo bảng mới nếu chưa có
// Dùng IF NOT EXISTS → an toàn, không ảnh hưởng bảng cũ
// =========================================
$migrations = [

    // 1. OTP đăng nhập
    "CREATE TABLE IF NOT EXISTS `otp_logs` (
        `otp_id`     INT NOT NULL AUTO_INCREMENT,
        `ma_khach`   INT NOT NULL,
        `otp_code`   VARCHAR(10) NOT NULL,
        `otp_type`   ENUM('login','register','forgot') DEFAULT 'login',
        `is_used`    TINYINT(1) DEFAULT 0,
        `attempts`   TINYINT DEFAULT 0,
        `ip_address` VARCHAR(45),
        `expired_at` DATETIME NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`otp_id`),
        KEY `idx_otp_lookup` (`ma_khach`,`otp_code`,`is_used`,`expired_at`),
        CONSTRAINT `fk_otp_khach` FOREIGN KEY (`ma_khach`)
            REFERENCES `khachhang` (`MaKhachHang`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 2. Thanh toán QR
    "CREATE TABLE IF NOT EXISTS `qr_payments` (
        `qr_id`            INT NOT NULL AUTO_INCREMENT,
        `ma_hoa_don`       INT NOT NULL,
        `bank_code`        VARCHAR(20) DEFAULT 'VCB',
        `account_number`   VARCHAR(30) DEFAULT '1234567890',
        `account_name`     VARCHAR(150) DEFAULT 'CONG TY TNHH CELLPHONEK',
        `amount`           DECIMAL(15,2) NOT NULL,
        `transfer_content` VARCHAR(100) NOT NULL,
        `qr_image_url`     VARCHAR(500),
        `status`           ENUM('pending','verified','expired','failed') DEFAULT 'pending',
        `expired_at`       DATETIME,
        `verified_at`      DATETIME,
        `verified_by`      INT DEFAULT NULL,
        `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`qr_id`),
        UNIQUE KEY `uk_qr_hoadon` (`ma_hoa_don`),
        KEY `idx_qr_status` (`status`,`expired_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 3. Cấu hình hạng VIP
    "CREATE TABLE IF NOT EXISTS `vip_tiers` (
        `tier_id`      INT NOT NULL AUTO_INCREMENT,
        `tier_name`    ENUM('Bronze','Silver','Gold','Platinum','Diamond') UNIQUE NOT NULL,
        `tier_level`   INT NOT NULL,
        `min_spent`    DECIMAL(15,2) DEFAULT 0,
        `min_orders`   INT DEFAULT 0,
        `discount_pct` DECIMAL(5,2) DEFAULT 0,
        `badge_color`  VARCHAR(20) DEFAULT '#CD7F32',
        `badge_icon`   VARCHAR(10) DEFAULT '🥉',
        `description`  VARCHAR(255),
        PRIMARY KEY (`tier_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 4. Lịch sử thay đổi hạng VIP
    "CREATE TABLE IF NOT EXISTS `vip_history` (
        `history_id` INT NOT NULL AUTO_INCREMENT,
        `ma_khach`   INT NOT NULL,
        `old_tier`   VARCHAR(20),
        `new_tier`   VARCHAR(20),
        `reason`     VARCHAR(100) DEFAULT 'auto',
        `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`history_id`),
        CONSTRAINT `fk_vip_hist_khach` FOREIGN KEY (`ma_khach`)
            REFERENCES `khachhang` (`MaKhachHang`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 5. Chương trình khuyến mãi VIP
    "CREATE TABLE IF NOT EXISTS `vip_promotions` (
        `promo_id`        INT NOT NULL AUTO_INCREMENT,
        `promo_name`      VARCHAR(255) NOT NULL,
        `min_tier`        ENUM('Bronze','Silver','Gold','Platinum','Diamond') DEFAULT 'Silver',
        `discount_type`   ENUM('percent','fixed') DEFAULT 'percent',
        `discount_value`  DECIMAL(10,2) NOT NULL,
        `max_discount`    DECIMAL(15,2) DEFAULT NULL,
        `min_order_value` DECIMAL(15,2) DEFAULT 0,
        `start_date`      DATE NOT NULL,
        `end_date`        DATE NOT NULL,
        `is_active`       TINYINT(1) DEFAULT 1,
        `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`promo_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 6. Lịch sử sử dụng khuyến mãi
    "CREATE TABLE IF NOT EXISTS `promotion_usage` (
        `usage_id`        INT NOT NULL AUTO_INCREMENT,
        `promo_id`        INT NOT NULL,
        `ma_khach`        INT NOT NULL,
        `ma_hoa_don`      INT NOT NULL,
        `discount_amount` DECIMAL(15,2),
        `used_at`         DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`usage_id`),
        UNIQUE KEY `uk_promo_order` (`promo_id`,`ma_hoa_don`),
        CONSTRAINT `fk_pu_promo` FOREIGN KEY (`promo_id`)
            REFERENCES `vip_promotions` (`promo_id`),
        CONSTRAINT `fk_pu_khach` FOREIGN KEY (`ma_khach`)
            REFERENCES `khachhang` (`MaKhachHang`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($migrations as $sql) {
    $conn->query($sql);
}

// Seed dữ liệu mặc định vip_tiers (chỉ insert nếu bảng rỗng)
$check = $conn->query("SELECT COUNT(*) as cnt FROM vip_tiers");
if ($check && $check->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO vip_tiers
        (tier_name, tier_level, min_spent, min_orders, discount_pct, badge_color, badge_icon, description)
        VALUES
        ('Bronze',   1,         0,         0,   0,  '#CD7F32','🥉','Thành viên mới'),
        ('Silver',   2,   5000000,         5,   5,  '#9CA3AF','🥈','Chi tiêu từ 5 triệu hoặc 5+ đơn'),
        ('Gold',     3,  20000000,        16,  10,  '#F59E0B','🥇','Chi tiêu từ 20 triệu hoặc 16+ đơn'),
        ('Platinum', 4,  50000000,        31,  15,  '#6B7280','💎','Chi tiêu từ 50 triệu hoặc 31+ đơn'),
        ('Diamond',  5, 100000000,        51,  20,  '#06B6D4','💠','Chi tiêu từ 100 triệu hoặc 51+ đơn')");
}

// Seed dữ liệu mặc định vip_promotions (chỉ insert nếu bảng rỗng)
$check2 = $conn->query("SELECT COUNT(*) as cnt FROM vip_promotions");
if ($check2 && $check2->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO vip_promotions
        (promo_name, min_tier, discount_type, discount_value, start_date, end_date)
        VALUES
        ('Ưu đãi Silver 5%',    'Silver',   'percent',  5, '2026-01-01', '2099-12-31'),
        ('Ưu đãi Gold 10%',     'Gold',     'percent', 10, '2026-01-01', '2099-12-31'),
        ('Ưu đãi Platinum 15%', 'Platinum', 'percent', 15, '2026-01-01', '2099-12-31'),
        ('Ưu đãi Diamond 20%',  'Diamond',  'percent', 20, '2026-01-01', '2099-12-31')");
}

// Thêm cột TrangThaiThanhToan vào hoadon nếu chưa có
$colCheck = $conn->query("SHOW COLUMNS FROM hoadon LIKE 'TrangThaiThanhToan'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE hoadon ADD COLUMN `TrangThaiThanhToan` VARCHAR(30) DEFAULT 'chua_thanh_toan'");
}

// Đảm bảo HangThanhVien mặc định = 'Bronze' cho khách hàng cũ
$conn->query("UPDATE khachhang SET HangThanhVien='Bronze' WHERE HangThanhVien IS NULL OR HangThanhVien=''");
?>
