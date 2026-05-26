<?php
session_start();
require '../connect.php';

if (isset($_POST['btn'])) {
    $u     = trim($_POST['user']  ?? '');
    $p     = trim($_POST['pass']  ?? '');
    $e     = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $messages = [];

    // Dùng Prepared Statements – chống SQL Injection
    $chkUser = $conn->prepare("SELECT 1 FROM khachhang WHERE TenDangNhap = ? LIMIT 1");
    $chkUser->bind_param('s', $u);
    $chkUser->execute();
    if ($chkUser->get_result()->num_rows > 0) {
        $messages[] = 'Tên đăng nhập này đã được đăng ký.';
    }
    $chkUser->close();

    $chkEmail = $conn->prepare("SELECT 1 FROM khachhang WHERE Email = ? LIMIT 1");
    $chkEmail->bind_param('s', $e);
    $chkEmail->execute();
    if ($chkEmail->get_result()->num_rows > 0) {
        $messages[] = 'Email này đã được đăng ký.';
    }
    $chkEmail->close();

    $chkPhone = $conn->prepare("SELECT 1 FROM khachhang WHERE SoDienThoai = ? LIMIT 1");
    $chkPhone->bind_param('s', $phone);
    $chkPhone->execute();
    if ($chkPhone->get_result()->num_rows > 0) {
        $messages[] = 'Số điện thoại này đã được đăng ký.';
    }
    $chkPhone->close();

    if (!empty($messages)) {
        $error = implode(' ', $messages);
    } else {
        $ins = $conn->prepare("INSERT INTO khachhang (TenDangNhap, MatKhau, Email, SoDienThoai) VALUES (?, ?, ?, ?)");
        $ins->bind_param('ssss', $u, $p, $e, $phone);
        if ($ins->execute()) {
            echo "<script>alert('Đăng ký thành công!'); window.location='login.php';</script>";
            exit;
        } else {
            $error = 'Lỗi đăng ký: ' . $conn->error;
        }
        $ins->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký – CellPhoneK</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <form method="POST">
        <h2 style="color:#ef4444">ĐĂNG KÝ</h2>
        <?php if (!empty($error)): ?>
            <p style="color:#e74c3c; background:#fff0f0; padding:10px; border-radius:6px; margin-bottom:12px; text-align:left;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>
        <input type="text"     name="user"  placeholder="Tên đăng nhập" required value="<?= htmlspecialchars($_POST['user'] ?? '') ?>">
        <input type="password" name="pass"  placeholder="Mật khẩu" required>
        <input type="email"    name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <input type="tel"      name="phone" placeholder="Số điện thoại" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        <button type="submit" name="btn" class="btn-red">XÁC NHẬN ĐĂNG KÝ</button>
        <p>Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>
        <p><a href="../index.php">← Quay về trang chủ</a></p>
    </form>
</body>
</html>
