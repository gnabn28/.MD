<?php
session_start();
require '../connect.php';

// =========================================
// ĐĂNG NHẬP
// =========================================
if (isset($_POST['btn'])) {
    $u = trim($_POST['user'] ?? '');
    $p = trim($_POST['pass'] ?? '');

    if ($u === '' || $p === '') {
        $error = 'Vui lòng nhập đầy đủ tài khoản và mật khẩu.';
    } else {
        $stmt = $conn->prepare("SELECT MaKhachHang, TenDangNhap, HoTen FROM khachhang WHERE TenDangNhap = ? AND MatKhau = ? LIMIT 1");
        $stmt->bind_param('ss', $u, $p);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['user_id']  = $user['MaKhachHang'];
            $_SESSION['username'] = $user['HoTen'] ?: $user['TenDangNhap'];
            $_SESSION['user']     = $user['TenDangNhap'];
            $_SESSION['cart']     = $_SESSION['cart'] ?? [];
            $stmt->close();

            $redirect = '../index.php';
            if (isset($_GET['redirect'])) {
                $rd = basename($_GET['redirect']);
                $redirect = $rd;
            }
            echo "<script>alert('Chào mừng " . htmlspecialchars($user['HoTen'] ?: $u, ENT_QUOTES) . "!'); window.location='" . $redirect . "';</script>";
            exit;
        } else {
            $stmt->close();
            $error = 'Sai tài khoản hoặc mật khẩu!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập – CellPhoneK</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/shop.css">
</head>
<body>

<!-- ===== HEADER ===== -->
<header class="site-header">
    <div class="top-bar">
        <div class="container top-bar__inner">
            <span>Miễn phí vận chuyển đơn từ 300.000đ</span>
            <span>Hàng chính hãng 100%</span>
            <span class="hide-mobile">Hotline: <strong>1800.2097</strong></span>
        </div>
    </div>
    <div class="main-header">
        <div class="container main-header__inner">
            <a href="../index.php" class="logo">CellPhone<span>K</span></a>
            <nav class="desktop-nav hide-mobile">
                <a href="../index.php">Trang chủ</a>
                <a href="../index.php">Sản phẩm</a>
                <a href="cart.php">Giỏ hàng</a>
            </nav>
            <div class="header-actions">
                <a href="cart.php" class="header-cart">&#128722; Giỏ hàng</a>
            </div>
        </div>
    </div>
</header>

<!-- ===== MAIN CONTENT ===== -->
<main class="page-main">
<div class="container" style="max-width:440px; padding-top:32px; padding-bottom:32px;">
    <h1 class="page-title">Đăng nhập</h1>
    <div class="card">
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="user" required value="<?= htmlspecialchars($_POST['user'] ?? '') ?>" placeholder="Nhập tên đăng nhập">
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="pass" required placeholder="Nhập mật khẩu">
            </div>
            <button type="submit" name="btn" class="btn btn-primary btn-block">Đăng nhập</button>
        </form>
        <p style="text-align:center; margin-top:16px; font-size:14px;">
            Chưa có tài khoản? <a href="register.php" style="color:var(--brand); font-weight:600;">Đăng ký</a>
        </p>
        <p style="text-align:center; margin-top:8px; font-size:14px;">
            <a href="../index.php">← Tiếp tục mua không đăng nhập</a>
        </p>
    </div>
</div>
</main>

<!-- ===== FOOTER ===== -->
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <h4>CellPhoneK</h4>
            <p>Website bán điện thoại &amp; laptop chính hãng.</p>
        </div>
        <div>
            <h4>Danh mục</h4>
            <a href="../index.php">Điện thoại</a>
            <a href="../index.php">Laptop</a>
        </div>
        <div>
            <h4>Hỗ trợ</h4>
            <a href="cart.php">Giỏ hàng</a>
            <a href="checkout.php">Thanh toán</a>
        </div>
    </div>
    <p class="footer-copy">© 2026 CellPhoneK. All rights reserved.</p>
</footer>

<nav class="bottom-nav" aria-label="Menu chinh">
    <a href="../index.php"><span class="icon">&#127968;</span><span>Trang chu</span></a>
    <a href="../index.php"><span class="icon">&#128241;</span><span>San pham</span></a>
    <a href="../pages/cart.php"><span class="icon">&#128722;</span><span>Gio hang</span></a>
    <a href="<?= isset($_SESSION['user_id']) ? '../pages/profile.php' : '../pages/login.php' ?>"><span class="icon">&#128100;</span><span><?= isset($_SESSION['user_id']) ? 'Tai khoan' : 'Dang nhap' ?></span></a>
</nav>

</body>
</html>
