<?php
/**
 * TRANG ĐẶT HÀNG THÀNH CÔNG
 */
session_start();
require '../connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$maHoaDon    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tenDangNhap = $_SESSION['user'] ?? '';

if ($maHoaDon <= 0) {
    header('Location: ../index.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM hoadon WHERE MaHoaDon = ? AND TenDangNhap = ?");
$stmt->bind_param('is', $maHoaDon, $tenDangNhap);
$stmt->execute();
$hoaDon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$hoaDon) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt hàng thành công – CellPhoneK</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/shop.css">
    <style>
        .success-wrap { min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .success-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 48px 40px; max-width: 560px; width: 100%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,.08); }
        .success-icon { width: 80px; height: 80px; background: linear-gradient(135deg, var(--success), #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 20px; }
        .success-card h1 { font-size: 1.8rem; font-weight: 800; color: var(--text); margin-bottom: 8px; }
        .success-card .sub { color: var(--text-muted); font-size: .95rem; margin-bottom: 32px; }
        .info-box { background: var(--bg3); border-radius: 10px; padding: 20px; text-align: left; margin-bottom: 24px; }
        .info-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid var(--border); font-size: .88rem; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); }
        .info-value { font-weight: 600; color: var(--text); }
        .info-value.highlight { color: var(--accent); font-size: 1rem; }
        .bank-info { background: rgba(14,165,233,.08); border: 1px solid rgba(14,165,233,.2); border-radius: 10px; padding: 16px; text-align: left; margin-bottom: 24px; font-size: .85rem; }
        .bank-info h3 { color: var(--primary); margin-bottom: 8px; }
        .bank-info p { margin: 4px 0; color: var(--text-muted); }
        .action-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    </style>
</head>
<body>
<header class="navbar">
    <div class="container nav-inner">
        <a href="../index.php" class="logo">
            <span class="logo-icon">📱</span><span>CellPhone<strong>K</strong></span>
        </a>
        <nav class="nav-links">
            <a href="../index.php">Trang chủ</a>
            <a href="profile.php" class="btn-nav-user">👤 <?= htmlspecialchars($_SESSION['username'] ?? '') ?></a>
            <a href="logout.php" class="btn-nav-logout">Đăng xuất</a>
        </nav>
    </div>
</header>

<div class="success-wrap">
    <div class="success-card">
        <div class="success-icon">✓</div>
        <h1>Đặt hàng thành công!</h1>
        <p class="sub">Cảm ơn bạn đã mua hàng tại CellPhoneK. Chúng tôi sẽ liên hệ sớm.</p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Mã đơn hàng:</span>
                <span class="info-value highlight">#DH-<?= str_pad($hoaDon['MaHoaDon'], 5, '0', STR_PAD_LEFT) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Ngày đặt:</span>
                <span class="info-value"><?= date('d/m/Y', strtotime($hoaDon['NgayLap'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Người nhận:</span>
                <span class="info-value"><?= htmlspecialchars($hoaDon['HoTenNhan']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Số điện thoại:</span>
                <span class="info-value"><?= htmlspecialchars($hoaDon['SoDienThoaiNhan']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Địa chỉ:</span>
                <span class="info-value"><?= htmlspecialchars($hoaDon['DiaChiNhan']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Thanh toán:</span>
                <span class="info-value"><?= $hoaDon['PhuongThucThanhToan'] === 'COD' ? 'Tiền mặt (COD)' : 'Chuyển khoản' ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tổng tiền:</span>
                <span class="info-value highlight"><?= number_format($hoaDon['TongTien'], 0, ',', '.') ?>đ</span>
            </div>
            <div class="info-row">
                <span class="info-label">Trạng thái:</span>
                <span class="info-value"><?= htmlspecialchars($hoaDon['TrangThai']) ?></span>
            </div>
        </div>

        <?php if ($hoaDon['PhuongThucThanhToan'] === 'BANK'): ?>
        <div class="bank-info">
            <h3>💳 Thông tin chuyển khoản</h3>
            <p>Ngân hàng: <b>Vietcombank</b></p>
            <p>Số tài khoản: <b>0123456789</b></p>
            <p>Chủ tài khoản: <b>CellPhoneK</b></p>
            <p>Nội dung: <b>#<?= $hoaDon['MaHoaDon'] ?> <?= htmlspecialchars($hoaDon['HoTenNhan']) ?></b></p>
        </div>
        <?php endif; ?>

        <div class="action-btns">
            <a href="print_bill.php?id=<?= $hoaDon['MaHoaDon'] ?>" target="_blank" class="btn-primary" style="background:#0ea5e9;border-color:#0ea5e9;"><i class="fa-solid fa-print"></i> In hóa đơn</a>
            <a href="profile.php?tab=orders" class="btn-primary">📋 Xem đơn hàng của tôi</a>
            <a href="../index.php" style="display:inline-block;padding:12px 28px;border-radius:10px;border:1px solid var(--border);color:var(--text-muted);font-weight:600;transition:.2s;">Tiếp tục mua sắm</a>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="container footer-inner">
        <p>© 2024 <strong>CellPhoneK</strong> – Điện thoại chính hãng giá tốt</p>
    </div>
</footer>
</body>
</html>
