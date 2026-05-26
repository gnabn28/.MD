<?php
/**
 * TRANG THANH TOÁN
 * Sử dụng session cart (giống huy) thay vì bảng giohang trong DB
 */
session_start();
require '../connect.php';

// 1. Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

$user_id     = (int)$_SESSION['user_id'];
$tenDangNhap = $_SESSION['user'] ?? '';

// 2. Kiểm tra giỏ hàng
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$cartItems = $_SESSION['cart'];
$total     = 0;
foreach ($cartItems as $item) {
    $total += $item['price'] * $item['quantity'];
}

// 3. Lấy thông tin khách hàng để điền sẵn
$stmt = $conn->prepare("SELECT HoTen, SoDienThoai, DiaChi, Email FROM khachhang WHERE MaKhachHang = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$khachHang = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán – CellPhoneK</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/shop.css">
    <style>
        .checkout-page { padding: 36px 20px; }
        .checkout-grid { display: grid; grid-template-columns: 1fr 380px; gap: 28px; align-items: start; margin-top: 24px; }
        .checkout-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; margin-bottom: 20px; }
        .checkout-card h2 { font-size: 1rem; font-weight: 700; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: .82rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }
        .form-group label .req { color: #ef4444; margin-left: 2px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 8px; font-size: .9rem; font-family: inherit; color: var(--text); background: var(--bg3); outline: none; transition: border-color .2s; box-sizing: border-box; }
        .form-group input:focus, .form-group textarea:focus { border-color: var(--primary); background: #fff; }
        .form-group textarea { resize: vertical; min-height: 70px; }
        .payment-option { display: flex; align-items: flex-start; gap: 12px; padding: 14px; border: 1.5px solid var(--border); border-radius: 10px; cursor: pointer; transition: .2s; margin-bottom: 10px; }
        .payment-option:hover { border-color: var(--primary); background: rgba(37,99,235,.03); }
        .payment-option input[type="radio"] { margin-top: 3px; accent-color: var(--primary); }
        .payment-option strong { display: block; font-size: .9rem; margin-bottom: 4px; }
        .payment-option p { font-size: .8rem; color: var(--text-muted); margin: 0; }
        .order-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border); }
        .order-item:last-child { border-bottom: none; }
        .order-item img { width: 60px; height: 60px; object-fit: contain; background: #fff; border-radius: 8px; flex-shrink: 0; }
        .order-item-name { font-size: .88rem; font-weight: 600; }
        .order-item-meta { font-size: .75rem; color: var(--text-muted); margin-top: 4px; }
        .order-item-price { font-size: .88rem; font-weight: 700; color: var(--accent); margin-top: 4px; }
        .summary-sep { border: none; border-top: 1px solid var(--border); margin: 14px 0; }
        .summary-row { display: flex; justify-content: space-between; align-items: center; font-size: .9rem; color: var(--text-muted); padding: 6px 0; }
        .summary-row.total { font-size: 1.05rem; font-weight: 700; color: var(--text); }
        .summary-row.total span:last-child { color: var(--accent); font-size: 1.2rem; }
        .btn-place-order { display: block; width: 100%; padding: 14px; border: none; border-radius: 12px; background: linear-gradient(135deg, var(--success), #059669); color: #fff; font-size: 1rem; font-weight: 700; cursor: pointer; text-align: center; margin-top: 16px; transition: .2s; }
        .btn-place-order:hover { opacity: .85; transform: translateY(-2px); }
        .alert-box { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .88rem; display: none; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        @media (max-width: 768px) { .checkout-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header class="navbar">
    <div class="container nav-inner">
        <a href="../index.php" class="logo">
            <span class="logo-icon">📱</span><span>CellPhone<strong>K</strong></span>
        </a>
        <nav class="nav-links">
            <a href="cart.php">← Giỏ hàng</a>
            <a href="profile.php" class="btn-nav-user">👤 <?= htmlspecialchars($_SESSION['username'] ?? '') ?></a>
            <a href="logout.php" class="btn-nav-logout">Đăng xuất</a>
        </nav>
    </div>
</header>

<div class="breadcrumb-bar">
    <div class="container">
        <a href="../index.php">Trang chủ</a>
        <span>›</span>
        <a href="cart.php">Giỏ hàng</a>
        <span>›</span>
        <span>Thanh toán</span>
    </div>
</div>

<main class="container checkout-page">
    <div id="alertBox" class="alert-box alert-error"></div>

    <form id="checkoutForm" class="checkout-grid">
        <!-- CỘT TRÁI: Thông tin giao hàng + thanh toán -->
        <div>
            <div class="checkout-card">
                <h2>📦 Thông tin người nhận</h2>
                <div class="form-group">
                    <label>Họ tên người nhận <span class="req">*</span></label>
                    <input type="text" id="hoTen" name="hoTen"
                           value="<?= htmlspecialchars($khachHang['HoTen'] ?? '') ?>"
                           placeholder="Nhập họ và tên" required>
                </div>
                <div class="form-group">
                    <label>Số điện thoại <span class="req">*</span></label>
                    <input type="tel" id="soDienThoai" name="soDienThoai"
                           value="<?= htmlspecialchars($khachHang['SoDienThoai'] ?? '') ?>"
                           placeholder="VD: 0901234567" required>
                </div>
                <div class="form-group">
                    <label>Địa chỉ nhận hàng <span class="req">*</span></label>
                    <textarea id="diaChi" name="diaChi" required
                              placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành"><?= htmlspecialchars($khachHang['DiaChi'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Ghi chú (không bắt buộc)</label>
                    <textarea id="ghiChu" name="ghiChu"
                              placeholder="VD: Giao giờ hành chính, gọi trước khi giao..."></textarea>
                </div>
            </div>

            <div class="checkout-card">
                <h2>💳 Phương thức thanh toán</h2>
                <label class="payment-option">
                    <input type="radio" name="phuongThuc" value="COD" checked>
                    <div>
                        <strong>Thanh toán khi nhận hàng (COD)</strong>
                        <p>Bạn sẽ thanh toán bằng tiền mặt khi nhận được hàng.</p>
                    </div>
                </label>
                <label class="payment-option">
                    <input type="radio" name="phuongThuc" value="BANK">
                    <div>
                        <strong>Chuyển khoản ngân hàng</strong>
                        <p>Chuyển khoản đến: <b>CellPhoneK</b> – STK <b>0123456789</b> – Vietcombank.<br>
                        Nội dung: <b>Họ tên + SĐT</b>.</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- CỘT PHẢI: Đơn hàng -->
        <aside>
            <div class="checkout-card">
                <h2>🛒 Đơn hàng (<?= count($cartItems) ?> sản phẩm)</h2>
                <?php foreach ($cartItems as $key => $item): ?>
                <div class="order-item">
                    <img src="../<?= htmlspecialchars($item['image'] ?? '') ?>"
                         alt="<?= htmlspecialchars($item['name']) ?>"
                         onerror="this.src='../img/no-image.svg'">
                    <div style="flex:1">
                        <div class="order-item-name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="order-item-meta">
                            🎨 <?= htmlspecialchars($item['color'] ?? '') ?> &nbsp;
                            💾 <?= htmlspecialchars($item['ram'] ?? '') ?> &nbsp;
                            SL: <?= (int)$item['quantity'] ?>
                        </div>
                        <div class="order-item-price">
                            <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <hr class="summary-sep">
                <div class="summary-row"><span>Tạm tính:</span><span><?= number_format($total, 0, ',', '.') ?>đ</span></div>
                <div class="summary-row"><span>Phí vận chuyển:</span><span style="color:var(--success);font-weight:600;">Miễn phí</span></div>
                <div class="summary-row total"><span>Tổng cộng:</span><span><?= number_format($total, 0, ',', '.') ?>đ</span></div>

                <button type="submit" class="btn-place-order" id="btnPlaceOrder">
                    ✅ Đặt hàng ngay
                </button>
                <a href="../index.php" class="btn-continue-shop" style="display:block;text-align:center;margin-top:12px;font-size:.85rem;color:var(--text-muted);">← Tiếp tục mua sắm</a>
            </div>
        </aside>
    </form>
</main>

<footer class="footer">
    <div class="container footer-inner">
        <p>© 2024 <strong>CellPhoneK</strong> – Điện thoại chính hãng giá tốt</p>
    </div>
</footer>
<script>
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnPlaceOrder');
    const alertBox = document.getElementById('alertBox');
    alertBox.style.display = 'none';

    // Client-side validation
    const hoTen = document.getElementById('hoTen').value.trim();
    const sdt   = document.getElementById('soDienThoai').value.trim();
    const diaChi = document.getElementById('diaChi').value.trim();

    if (!hoTen) { showError('Vui lòng nhập họ tên người nhận.'); return; }
    if (!sdt || !/^(0|\+84)[0-9]{9,10}$/.test(sdt)) { showError('Số điện thoại không đúng định dạng (VD: 0901234567).'); return; }
    if (!diaChi || diaChi.length < 10) { showError('Địa chỉ quá ngắn, vui lòng nhập đầy đủ.'); return; }

    btn.disabled = true;
    btn.textContent = '⏳ Đang xử lý...';

    const formData = new FormData(this);

    fetch('process_order.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = '✅ Đặt hàng ngay';
            if (data.success) {
                window.location.href = 'order_success.php?id=' + data.MaHoaDon;
            } else {
                showError(data.message || 'Đặt hàng thất bại. Vui lòng thử lại.');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = '✅ Đặt hàng ngay';
            showError('Lỗi kết nối máy chủ. Vui lòng thử lại.');
        });
});

function showError(msg) {
    const alertBox = document.getElementById('alertBox');
    alertBox.textContent = '⚠️ ' + msg;
    alertBox.style.display = 'block';
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>
</body>
</html>
