<?php
session_start();
require 'connect.php';

// 1. XỬ LÝ ĐĂNG NHẬP ADMIN
if (isset($_POST['admin_login'])) {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    
    $stmt = $conn->prepare("SELECT * FROM admin_inf WHERE TenDangNhap = ? AND MatKhau = ?");
    $stmt->bind_param('ss', $user, $pass);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $admin = $res->fetch_assoc();
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $admin['TenDangNhap'];
        $_SESSION['admin_name'] = $admin['HoTen'];
        header('Location: admin.php');
        exit;
    } else {
        $error = "Sai tên đăng nhập hoặc mật khẩu!";
    }
}

// 2. XỬ LÝ ĐĂNG XUẤT
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_user'], $_SESSION['admin_name']);
    header('Location: admin.php');
    exit;
}

// 3. NẾU CHƯA ĐĂNG NHẬP -> HIỂN THỊ FORM LOGIN
if (!isset($_SESSION['admin_logged_in'])) {
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <style>
        body { background: #f8fafc; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h3 class="text-center mb-4">Quản trị viên</h3>
        <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label>Tên đăng nhập</label>
                <input type="text" name="username" class="form-control" required value="admin">
            </div>
            <div class="mb-3">
                <label>Mật khẩu</label>
                <input type="password" name="password" class="form-control" required value="admin123">
            </div>
            <button type="submit" name="admin_login" class="btn btn-primary w-100">Đăng nhập</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// 4. XỬ LÝ CÁC ACTION TỪ FORM AJAX/POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Cập nhật trạng thái đơn hàng
    if ($action === 'update_order_status') {
        $maHD = (int)$_POST['ma_hd'];
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE hoadon SET TrangThai = ? WHERE MaHoaDon = ?");
        $stmt->bind_param('si', $status, $maHD);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    
    // Xóa sản phẩm
    if ($action === 'delete_product') {
        $maSP = (int)$_POST['ma_sp'];
        // Tạm thời chỉ xóa sp trong bảng sanpham, các bảng con sẽ bị xóa do ràng buộc ON DELETE CASCADE (nếu có)
        // Lưu ý: Nếu DB chưa set ON DELETE CASCADE, có thể gặp lỗi ràng buộc khóa ngoại.
        // Để an toàn, xóa các bảng con trước:
        $conn->query("DELETE FROM image WHERE MaSanPham = $maSP");
        $conn->query("DELETE FROM video WHERE MaSanPham = $maSP");
        $conn->query("DELETE FROM giasanpham WHERE MaSanPham = $maSP");
        $conn->query("DELETE FROM chitiethoadon WHERE MaSanPham = $maSP");
        $conn->query("DELETE FROM colors WHERE MaSanPham = $maSP");
        $conn->query("DELETE FROM ram_rom_option WHERE MaSanPham = $maSP");
        $conn->query("DELETE FROM chitietsanpham WHERE MaSanPham = $maSP");
        
        if ($conn->query("DELETE FROM sanpham WHERE MaSanPham = $maSP")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    
    // Lấy tồn kho của 1 sản phẩm
    if ($action === 'get_stock') {
        $maSP = (int)$_POST['ma_sp'];
        $sql = "SELECT g.MaGia, r.KichThuoc as Ram, c.TenMau as Mau, g.SoLuong 
                FROM giasanpham g 
                LEFT JOIN ram_rom_option r ON g.MaRam = r.MaRam 
                LEFT JOIN colors c ON g.MaMau = c.MaMau 
                WHERE g.MaSanPham = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $maSP);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = [];
        while($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    
    // Cập nhật tồn kho (mảng các MaGia => SoLuong)
    if ($action === 'update_stock_bulk') {
        $stocks = $_POST['stocks'] ?? [];
        foreach ($stocks as $maGia => $soLuong) {
            $maGia = (int)$maGia;
            $soLuong = (int)$soLuong;
            $conn->query("UPDATE giasanpham SET SoLuong = $soLuong WHERE MaGia = $maGia");
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Thêm/Sửa sản phẩm cơ bản (bảng sanpham + image)
    if ($action === 'save_product') {
        $maSP = (int)$_POST['ma_sp'];
        $tenSP = $_POST['ten_sp'];
        $hang = $_POST['hang'];
        $imgUrl = trim($_POST['img_url'] ?? 'img/no-image.svg');
        if ($imgUrl === '') $imgUrl = 'img/no-image.svg';
        $ngayNhap = date('Y-m-d');
        
        $conn->begin_transaction();
        try {
            if ($maSP > 0) {
                // Update
                $stmt = $conn->prepare("UPDATE sanpham SET TenSanPham = ?, Hang = ? WHERE MaSanPham = ?");
                $stmt->bind_param('ssi', $tenSP, $hang, $maSP);
                $stmt->execute();
                
                // Update image (chỉ cập nhật ảnh đầu tiên hoặc chèn thêm nếu chưa có)
                $chkImg = $conn->query("SELECT MaHinhAnh FROM image WHERE MaSanPham = $maSP ORDER BY MaHinhAnh ASC LIMIT 1");
                if ($chkImg->num_rows > 0) {
                    $mha = $chkImg->fetch_assoc()['MaHinhAnh'];
                    $stmtImg = $conn->prepare("UPDATE image SET DiaChiAnh = ? WHERE MaHinhAnh = ?");
                    $stmtImg->bind_param('si', $imgUrl, $mha);
                    $stmtImg->execute();
                } else {
                    $stmtImg = $conn->prepare("INSERT INTO image (MaSanPham, DiaChiAnh) VALUES (?, ?)");
                    $stmtImg->bind_param('is', $maSP, $imgUrl);
                    $stmtImg->execute();
                }
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO sanpham (TenSanPham, Hang, NgayNhap) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $tenSP, $hang, $ngayNhap);
                $stmt->execute();
                $newMaSP = $conn->insert_id;
                
                // Tạo 1 bản ghi giá mặc định
                $conn->query("INSERT INTO giasanpham (MaSanPham, GiaCu, GiaMoi, SoLuong) VALUES ($newMaSP, 0, 0, 0)");
                
                // Tạo bản ghi ảnh
                $stmtImg = $conn->prepare("INSERT INTO image (MaSanPham, DiaChiAnh) VALUES (?, ?)");
                $stmtImg->bind_param('is', $newMaSP, $imgUrl);
                $stmtImg->execute();
            }
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// ==========================================
// LẤY DỮ LIỆU HIỂN THỊ
// ==========================================
// 1. Lấy đơn hàng và tính doanh thu
$orders = [];
$totalRevenue = 0;
$totalOrders = 0;
$deliveredOrders = 0;
$res = $conn->query("SELECT * FROM hoadon ORDER BY MaHoaDon DESC");
if ($res) {
    while($r = $res->fetch_assoc()) {
        $orders[] = $r;
        $totalOrders++;
        if ($r['TrangThai'] === 'Đã giao') {
            $totalRevenue += $r['TongTien'];
            $deliveredOrders++;
        }
    }
}

// 2. Lấy sản phẩm và ảnh đại diện
$products = [];
$res = $conn->query("
    SELECT sp.*, 
           (SELECT img.DiaChiAnh FROM image img WHERE img.MaSanPham = sp.MaSanPham ORDER BY img.MaHinhAnh ASC LIMIT 1) as DiaChiAnh 
    FROM sanpham sp 
    ORDER BY sp.MaSanPham DESC
");
if ($res) {
    while($r = $res->fetch_assoc()) $products[] = $r;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản trị hệ thống - CellPhoneK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: #f1f5f9; font-family: 'Inter', sans-serif; }
        .sidebar { width: 250px; background: #1e293b; color: #fff; position: fixed; top: 0; bottom: 0; left: 0; padding: 20px 0; }
        .sidebar-brand { font-size: 1.5rem; font-weight: bold; text-align: center; margin-bottom: 30px; color: #38bdf8; }
        .nav-link { color: #cbd5e1; padding: 12px 24px; display: block; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: #334155; color: #fff; }
        .nav-link i { width: 25px; }
        .main-content { margin-left: 250px; padding: 30px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .table th { background: #f8fafc; font-weight: 600; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <i class="fa-solid fa-mobile-screen-button"></i> CellPhoneK
    </div>
    <a href="#orders" class="nav-link active" data-bs-toggle="tab"><i class="fa-solid fa-cart-shopping"></i> Quản lý Đơn hàng</a>
    <a href="#products" class="nav-link" data-bs-toggle="tab"><i class="fa-solid fa-box"></i> Quản lý Sản phẩm</a>
    <a href="?action=logout" class="nav-link" style="margin-top:auto;"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Dashboard Quản trị</h2>
        <div>Xin chào, <strong><?= htmlspecialchars($_SESSION['admin_name']) ?></strong></div>
    </div>

    <div class="tab-content">
        <!-- TAB QUẢN LÝ ĐƠN HÀNG -->
        <div class="tab-pane fade show active" id="orders">
            <!-- Thống kê doanh thu -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card p-3 bg-primary text-white">
                        <h5>Doanh thu thực tế (Đã giao)</h5>
                        <h3><?= number_format($totalRevenue, 0, ',', '.') ?>đ</h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 bg-success text-white">
                        <h5>Tổng số đơn hàng</h5>
                        <h3><?= $totalOrders ?> đơn</h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 bg-info text-white">
                        <h5>Đơn đã hoàn thành</h5>
                        <h3><?= $deliveredOrders ?> đơn</h3>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <h4 class="mb-3">Danh sách đơn hàng</h4>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Mã ĐH</th>
                                <th>Khách hàng</th>
                                <th>SĐT</th>
                                <th>Ngày đặt</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orders as $o): ?>
                            <tr>
                                <td>#DH-<?= $o['MaHoaDon'] ?></td>
                                <td><?= htmlspecialchars($o['HoTenNhan']) ?></td>
                                <td><?= htmlspecialchars($o['SoDienThoaiNhan']) ?></td>
                                <td><?= date('d/m/Y', strtotime($o['NgayLap'])) ?></td>
                                <td class="text-danger fw-bold"><?= number_format($o['TongTien'], 0, ',', '.') ?>đ</td>
                                <td>
                                    <select class="form-select form-select-sm status-select" data-id="<?= $o['MaHoaDon'] ?>">
                                        <option value="Chưa xác nhận" <?= $o['TrangThai']=='Chưa xác nhận'?'selected':'' ?>>Chưa xác nhận</option>
                                        <option value="Đã xác nhận" <?= $o['TrangThai']=='Đã xác nhận'?'selected':'' ?>>Đã xác nhận</option>
                                        <option value="Đang đóng gói" <?= $o['TrangThai']=='Đang đóng gói'?'selected':'' ?>>Đang đóng gói</option>
                                        <option value="Đang vận chuyển" <?= $o['TrangThai']=='Đang vận chuyển'?'selected':'' ?>>Đang vận chuyển</option>
                                        <option value="Đã giao" <?= $o['TrangThai']=='Đã giao'?'selected':'' ?>>Đã giao</option>
                                        <option value="Đã hủy" <?= $o['TrangThai']=='Đã hủy'?'selected':'' ?>>Đã hủy</option>
                                    </select>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success btn-update-status" data-id="<?= $o['MaHoaDon'] ?>">Lưu</button>
                                    <a href="pages/print_bill.php?id=<?= $o['MaHoaDon'] ?>" target="_blank" class="btn btn-sm btn-secondary"><i class="fa-solid fa-print"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB QUẢN LÝ SẢN PHẨM -->
        <div class="tab-pane fade" id="products">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="m-0">Danh sách Sản phẩm</h4>
                    <button class="btn btn-primary" onclick="openProductModal()"><i class="fa-solid fa-plus"></i> Thêm mới</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên Sản Phẩm</th>
                                <th>Hãng</th>
                                <th>Ngày Nhập</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($products as $p): ?>
                            <tr>
                                <td><?= $p['MaSanPham'] ?></td>
                                <td><?= htmlspecialchars($p['TenSanPham']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($p['Hang']) ?></span></td>
                                <td><?= date('d/m/Y', strtotime($p['NgayNhap'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info text-white" onclick="openStockModal(<?= $p['MaSanPham'] ?>, '<?= htmlspecialchars($p['TenSanPham'], ENT_QUOTES) ?>')"><i class="fa-solid fa-boxes-stacked"></i> Kho</button>
                                    <button class="btn btn-sm btn-warning" onclick="openProductModal(<?= $p['MaSanPham'] ?>, '<?= htmlspecialchars($p['TenSanPham'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['Hang'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['DiaChiAnh'] ?? '', ENT_QUOTES) ?>')"><i class="fa-solid fa-pen"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?= $p['MaSanPham'] ?>)"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL THÊM/SỬA SẢN PHẨM -->
<div class="modal fade" id="productModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="productModalTitle">Sản phẩm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" id="pm_id" value="0">
          <div class="mb-3">
              <label>Tên Sản Phẩm</label>
              <input type="text" id="pm_name" class="form-control">
          </div>
          <div class="mb-3">
              <label>Hãng (Brand)</label>
              <input type="text" id="pm_brand" class="form-control">
          </div>
          <div class="mb-3">
              <label>Đường dẫn Ảnh (VD: img/XIAOMI/dt1.jpg)</label>
              <input type="text" id="pm_img" class="form-control" placeholder="Để trống sẽ dùng ảnh mặc định">
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
        <button type="button" class="btn btn-primary" onclick="saveProduct()">Lưu thông tin</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL QUẢN LÝ KHO (SỐ LƯỢNG) -->
<div class="modal fade" id="stockModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Quản lý kho: <strong id="stockTitle"></strong></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <table class="table table-bordered text-center">
              <thead class="table-light">
                  <tr>
                      <th>Phiên bản (RAM/ROM)</th>
                      <th>Màu sắc</th>
                      <th style="width: 150px;">Số lượng kho</th>
                  </tr>
              </thead>
              <tbody id="stockBody">
                  <!-- Dữ liệu render bằng JS -->
              </tbody>
          </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
        <button type="button" class="btn btn-success" onclick="saveStock()">Cập nhật Số Lượng</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
// --- CHUYỂN TAB ---
const triggerTabList = document.querySelectorAll('.sidebar .nav-link[data-bs-toggle="tab"]')

function switchTab(triggerEl) {
    // Remove active from all sidebar links
    document.querySelectorAll('.sidebar .nav-link').forEach(l => l.classList.remove('active'));
    triggerEl.classList.add('active');
    
    // Hide all panes
    document.querySelectorAll('.tab-pane').forEach(p => { p.classList.remove('show', 'active'); });
    // Show target pane
    const href = triggerEl.getAttribute('href');
    const target = document.querySelector(href);
    if(target) target.classList.add('show', 'active');
    
    // Save to localStorage
    localStorage.setItem('adminActiveTab', href);
}

triggerTabList.forEach(triggerEl => {
  triggerEl.addEventListener('click', event => {
    event.preventDefault();
    switchTab(triggerEl);
  })
});

// Restore tab on load
document.addEventListener('DOMContentLoaded', () => {
    const savedTab = localStorage.getItem('adminActiveTab');
    if (savedTab) {
        const trigger = document.querySelector(`.sidebar .nav-link[href="${savedTab}"]`);
        if (trigger) switchTab(trigger);
    }
});

// --- ĐƠN HÀNG ---
document.querySelectorAll('.btn-update-status').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const select = document.querySelector('.status-select[data-id="'+id+'"]');
        const status = select.value;
        
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        
        const fd = new FormData();
        fd.append('action', 'update_order_status');
        fd.append('ma_hd', id);
        fd.append('status', status);
        
        fetch('admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            this.innerHTML = 'Lưu';
            if(res.success) alert('Cập nhật trạng thái thành công!');
            else alert('Lỗi: ' + res.message);
        });
    });
});

// --- SẢN PHẨM ---
const productModal = new bootstrap.Modal(document.getElementById('productModal'));
const stockModal = new bootstrap.Modal(document.getElementById('stockModal'));

function openProductModal(id = 0, name = '', brand = '', imgUrl = '') {
    document.getElementById('pm_id').value = id;
    document.getElementById('pm_name').value = name;
    document.getElementById('pm_brand').value = brand;
    document.getElementById('pm_img').value = imgUrl;
    document.getElementById('productModalTitle').innerText = id === 0 ? 'Thêm Sản Phẩm Mới' : 'Sửa Thông Tin Sản Phẩm';
    productModal.show();
}

function saveProduct() {
    const id = document.getElementById('pm_id').value;
    const name = document.getElementById('pm_name').value;
    const brand = document.getElementById('pm_brand').value;
    const imgUrl = document.getElementById('pm_img').value;
    
    if(!name || !brand) { alert('Vui lòng nhập đủ thông tin (Tên và Hãng)!'); return; }
    
    const fd = new FormData();
    fd.append('action', 'save_product');
    fd.append('ma_sp', id);
    fd.append('ten_sp', name);
    fd.append('hang', brand);
    fd.append('img_url', imgUrl);
    
    fetch('admin.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.success) { location.reload(); }
        else { alert('Lỗi: ' + res.message); }
    });
}

function deleteProduct(id) {
    if(!confirm('Bạn có chắc chắn muốn xóa sản phẩm này? Mọi dữ liệu liên quan sẽ bị xóa!')) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_product');
    fd.append('ma_sp', id);
    
    fetch('admin.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.success) { location.reload(); }
        else { alert('Lỗi: ' + res.message); }
    });
}

// --- QUẢN LÝ KHO ---
function openStockModal(id, name) {
    document.getElementById('stockTitle').innerText = name;
    document.getElementById('stockBody').innerHTML = '<tr><td colspan="3">Đang tải...</td></tr>';
    stockModal.show();
    
    const fd = new FormData();
    fd.append('action', 'get_stock');
    fd.append('ma_sp', id);
    
    fetch('admin.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            const data = res.data;
            let html = '';
            if(data.length === 0) {
                html = '<tr><td colspan="3" class="text-danger">Sản phẩm này chưa có biến thể (RAM/Màu). Hãy thiết lập trong CSDL.</td></tr>';
            } else {
                data.forEach(item => {
                    const ram = item.Ram ? item.Ram : 'Mặc định';
                    const mau = item.Mau ? item.Mau : 'Mặc định';
                    html += `
                    <tr>
                        <td>${ram}</td>
                        <td>${mau}</td>
                        <td>
                            <input type="number" class="form-control stock-input" data-magia="${item.MaGia}" value="${item.SoLuong}" min="0">
                        </td>
                    </tr>`;
                });
            }
            document.getElementById('stockBody').innerHTML = html;
        }
    });
}

function saveStock() {
    const inputs = document.querySelectorAll('.stock-input');
    const fd = new FormData();
    fd.append('action', 'update_stock_bulk');
    
    inputs.forEach(input => {
        const maGia = input.getAttribute('data-magia');
        const qty = input.value;
        fd.append('stocks['+maGia+']', qty);
    });
    
    fetch('admin.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            alert('Đã cập nhật số lượng kho thành công!');
            stockModal.hide();
        } else {
            alert('Lỗi cập nhật kho.');
        }
    });
}
</script>
</body>
</html>
