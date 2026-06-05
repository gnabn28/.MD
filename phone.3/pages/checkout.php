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
// =============================================
// CẤU HÌNH NGÂN HÀNG – chỉnh sửa 1 chỗ duy nhất
// =============================================
$bankId      = 'MB';               // Mã ngân hàng theo VietQR: MB, VCB, TCB, ACB...
$bankName    = 'MB Bank';          // Tên hiển thị
$bankAccount = '20068683979';      // Số tài khoản
$bankOwner   = 'CONG TY TNHH CELLPHONEK'; // Chủ tài khoản
$transferContent = 'CK' . $user_id . 'T' . time(); // Nội dung chuyển khoản
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
        .payment-option.active { border-color: var(--primary); background: rgba(37,99,235,.06); }
        .qr-panel { display:none; margin-top:14px; border:1.5px solid #bfdbfe; border-radius:14px; background: linear-gradient(135deg,#eff6ff 0%,#f0fdf4 100%); padding:20px; text-align:center; animation: fadeIn .3s ease; }
        .qr-panel.show { display:block; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
        .qr-panel img.qr-img { width:200px; height:200px; border-radius:12px; border:4px solid #fff; box-shadow:0 4px 20px rgba(0,0,0,.12); display:block; margin:0 auto 14px; }
        .qr-bank-info { font-size:.82rem; line-height:1.9; color:#374151; text-align:left; display:inline-block; margin-top:8px; }
        .qr-bank-info span { font-weight:700; color:#1e40af; }
        .qr-amount { font-size:1.4rem; font-weight:800; color:#ef4444; margin:10px 0 4px; letter-spacing:-.5px; }
        .qr-note { font-size:.75rem; color:#6b7280; margin-top:10px; line-height:1.6; }
        .qr-loading { width:200px; height:200px; border-radius:12px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; color:#9ca3af; font-size:13px; flex-direction:column; gap:8px; }
        .qr-spinner { width:32px; height:32px; border:3px solid #e5e7eb; border-top-color:#3b82f6; border-radius:50%; animation:spin .8s linear infinite; }
        @keyframes spin { to{transform:rotate(360deg)} }
        @media (max-width: 768px) { .checkout-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
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
                <a href="../pages/cart.php">Giỏ hàng</a>
            </nav>
            <form class="search-form" action="../index.php" method="get">
                <input type="search" name="search" placeholder="Bạn muốn mua gì hôm nay?" aria-label="Tìm kiếm">
                <button type="submit" aria-label="Tìm">&#128269;</button>
            </form>
            <div class="header-actions">
                <a href="../pages/cart.php" class="header-cart">
                    &#128722; Giỏ hàng
                    <?php
                    $__cartCount = 0;
                    if (!empty($_SESSION['cart'])) {
                        foreach ($_SESSION['cart'] as $__item) { $__cartCount += $__item['quantity']; }
                    }
                    if ($__cartCount > 0): ?>
                        <span class="badge"><?= $__cartCount ?></span>
                    <?php endif; ?>
                </a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../pages/profile.php" class="btn btn-outline btn-sm hide-mobile">&#128100; <?= htmlspecialchars($_SESSION['username'] ?? 'Tài khoản') ?></a>
                    <a href="../pages/logout.php" class="btn btn-outline btn-sm hide-mobile">Đăng xuất</a>
                <?php else: ?>
                    <a href="../pages/login.php" class="btn btn-outline btn-sm hide-mobile">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 24px;">
    <h1 class="page-title">💳 Thanh toán</h1>

    <div id="alertBox" class="alert-box alert-error" style="display:none; margin-bottom: 16px;"></div>

    <form id="checkoutForm" class="card">
        <h2 style="margin-bottom:16px;font-size:1.1rem;">Thông tin giao hàng</h2>
        <div class="form-row">
            <div class="form-group">
                <label>Họ và tên *</label>
                <input type="text" id="hoTen" name="hoTen" required value="<?= htmlspecialchars($khachHang['HoTen'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Số điện thoại *</label>
                <input type="tel" id="soDienThoai" name="soDienThoai" required value="<?= htmlspecialchars($khachHang['SoDienThoai'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Địa chỉ nhận hàng *</label>
            <textarea id="diaChi" name="diaChi" rows="3" required placeholder="Số nhà, đường, phường, quận, tỉnh..."><?= htmlspecialchars($khachHang['DiaChi'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>Ghi chú (không bắt buộc)</label>
            <textarea id="ghiChu" name="ghiChu" rows="2" placeholder="Giao giờ hành chính, gọi trước khi giao..."></textarea>
        </div>

        <h2 style="margin:20px 0 12px;font-size:1.1rem;">Phương thức thanh toán</h2>
        <div class="payment-options">
            <label class="payment-option active" id="optCOD">
                <input type="radio" name="phuongThuc" value="COD" checked id="radioCOD">
                <div>
                    <strong>💵 Thanh toán khi nhận hàng (COD)</strong>
                    <p style="font-size:13px;color:var(--muted);">Trả tiền mặt khi shipper giao hàng</p>
                </div>
            </label>
            <label class="payment-option" id="optBANK">
                <input type="radio" name="phuongThuc" value="BANK" id="radioBANK">
                <div style="flex:1">
                    <strong>🏦 Chuyển khoản ngân hàng (QR Code)</strong>
                    <p style="font-size:13px;color:var(--muted);">Quét mã QR – tự động điền đúng số tiền &amp; nội dung</p>
                </div>
            </label>
            <!-- QR Panel -->
            <div class="qr-panel" id="qrPanel">
                <div id="qrLoading" class="qr-loading">
                    <div class="qr-spinner"></div>
                    <span>Đang tạo mã QR...</span>
                </div>
                <img id="qrImage" class="qr-img" src="" alt="QR Chuyển khoản" style="display:none;">
                <div id="qrError" style="display:none;color:#ef4444;font-size:13px;margin-bottom:10px;">⚠️ Không tải được QR. Vui lòng chuyển khoản theo thông tin bên dưới.</div>
                <div class="qr-amount"><?= number_format($total, 0, ',', '.') ?>đ</div>
                <div class="qr-bank-info">
                    🏦 Ngân hàng: <span><?= htmlspecialchars($bankName) ?></span><br>
                    💳 Số tài khoản: <span><?= htmlspecialchars($bankAccount) ?></span><br>
                    👤 Chủ tài khoản: <span><?= htmlspecialchars($bankOwner) ?></span><br>
                    📝 Nội dung CK: <span id="qrContent"><?= htmlspecialchars($transferContent) ?></span>
                </div>
                <div class="qr-note">⏱ Vui lòng chuyển khoản trong <strong>15 phút</strong> sau khi đặt hàng.<br>Đơn hàng sẽ được xác nhận sau khi chúng tôi nhận được tiền.</div>
            </div>
        </div>

        <div class="cart-summary" style="margin:20px 0; border-top: 1px solid #eee; padding-top: 16px;">
            Tổng thanh toán: <strong><?= number_format($total, 0, ',', '.') ?>đ</strong>
        </div>

        <button type="submit" class="btn btn-primary btn-block" id="btnPlaceOrder" style="padding: 14px; font-size: 1.1rem;">Xác nhận đặt hàng</button>
        
        <p style="text-align:center;margin-top:12px;font-size:13px;color:var(--muted);">
            <a href="../index.php" style="color:var(--brand);">← Tiếp tục mua sắm</a>
        </p>
    </form>

    <div class="card" style="margin-top:16px;">
        <h3 style="margin-bottom:10px;">Đơn hàng của bạn (<?= count($cartItems) ?> sản phẩm)</h3>
        <ul style="list-style:none;font-size:14px;">
            <?php foreach ($cartItems as $item): ?>
                <li style="display:flex; gap:12px; padding:12px 0; border-bottom:1px solid #eee;">
                    <img src="../<?= htmlspecialchars($item['image'] ?? '') ?>" alt="" style="width:60px; height:60px; object-fit:contain; border-radius:8px; background:#f5f5f7;">
                    <div>
                        <div style="font-weight:600; margin-bottom:4px;"><?= htmlspecialchars($item['name']) ?></div>
                        <div style="font-size:12px; color:var(--muted); margin-bottom:4px;">
                            Màu: <?= htmlspecialchars($item['color'] ?? '') ?> - RAM: <?= htmlspecialchars($item['ram'] ?? '') ?>
                        </div>
                        <div style="color:var(--brand); font-weight:600;">
                            <?= number_format($item['price'], 0, ',', '.') ?>đ × <?= (int)$item['quantity'] ?> = <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

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
            <a href="../pages/cart.php">Giỏ hàng</a>
            <a href="../pages/checkout.php">Thanh toán</a>
        </div>
    </div>
    <p class="footer-copy">© 2026 CellPhoneK. All rights reserved.</p>
</footer>



<script>
// ===== Payment method toggle =====
const optCOD  = document.getElementById('optCOD');
const optBANK = document.getElementById('optBANK');
const radioCOD  = document.getElementById('radioCOD');
const radioBANK = document.getElementById('radioBANK');
const qrPanel   = document.getElementById('qrPanel');
let qrLoaded = false;

// VietQR config – lấy từ PHP để luôn đồng bộ
const BANK_ID      = '<?= $bankId ?>';         // Mã chuẩn VietQR
const ACCOUNT      = '<?= $bankAccount ?>';
const ACCOUNT_NAME = '<?= addslashes($bankOwner) ?>';
const TOTAL        = <?= $total ?>;
const BANK_NAME_DISPLAY = '<?= addslashes($bankName) ?>';
const TRANSFER_CONTENT = document.getElementById('qrContent').textContent.trim();

function selectCOD() {
    radioCOD.checked = true;
    optCOD.classList.add('active');
    optBANK.classList.remove('active');
    qrPanel.classList.remove('show');
}

function selectBANK() {
    radioBANK.checked = true;
    optBANK.classList.add('active');
    optCOD.classList.remove('active');
    qrPanel.classList.add('show');
    if (!qrLoaded) loadQR();
}

function loadQR() {
    qrLoaded = true;
    const qrImg     = document.getElementById('qrImage');
    const qrLoading = document.getElementById('qrLoading');
    const qrError   = document.getElementById('qrError');

    const amount  = Math.round(TOTAL);
    const content = TRANSFER_CONTENT;

    // Dùng PHP proxy để tránh lỗi CORS / firewall trên localhost
    const proxyUrl  = `qr_proxy.php?bank=${encodeURIComponent(BANK_ID)}&account=${encodeURIComponent(ACCOUNT)}&amount=${amount}&content=${encodeURIComponent(content)}&name=${encodeURIComponent(ACCOUNT_NAME)}`;
    // Fallback: gọi thẳng VietQR nếu proxy lỗi
    const directUrl = `https://img.vietqr.io/image/${BANK_ID}-${ACCOUNT}-compact.png?amount=${amount}&addInfo=${encodeURIComponent(content)}&accountName=${encodeURIComponent(ACCOUNT_NAME)}`;

    console.log('[VietQR] Proxy URL:', proxyUrl);

    qrImg.onload = function() {
        qrLoading.style.display = 'none';
        qrImg.style.display = 'block';
    };
    qrImg.onerror = function() {
        if (qrImg.src.indexOf('qr_proxy') !== -1) {
            // Proxy lỗi → thử gọi thẳng
            console.warn('[VietQR] Proxy thất bại, thử direct URL...');
            qrImg.src = directUrl;
        } else {
            qrLoading.style.display = 'none';
            qrError.style.display = 'block';
            console.error('[VietQR] Cả proxy và direct đều thất bại.');
        }
    };
    qrImg.src = proxyUrl;
}

// Click handlers
optCOD.addEventListener('click',  selectCOD);
optBANK.addEventListener('click', selectBANK);

// ===== Form submit =====
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnPlaceOrder');
    const alertBox = document.getElementById('alertBox');
    alertBox.style.display = 'none';

    const hoTen  = document.getElementById('hoTen').value.trim();
    const sdt    = document.getElementById('soDienThoai').value.trim();
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
<nav class="bottom-nav" aria-label="Menu chinh">
    <a href="../index.php"><span class="icon">&#127968;</span><span>Trang chu</span></a>
    <a href="../index.php"><span class="icon">&#128241;</span><span>San pham</span></a>
    <a href="../pages/cart.php"><span class="icon">&#128722;</span><span>Gio hang</span></a>
    <a href="<?= isset($_SESSION['user_id']) ? '../pages/profile.php' : '../pages/login.php' ?>"><span class="icon">&#128100;</span><span><?= isset($_SESSION['user_id']) ? 'Tai khoan' : 'Dang nhap' ?></span></a>
</nav>
</body>
</html>
