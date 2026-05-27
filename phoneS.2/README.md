# CellPhoneK – Trang Web Thương Mại Điện Tử

## Cấu trúc thư mục

```
output/
├── index.php            ← Trang chủ (danh sách sản phẩm, lọc hãng, phân trang)
├── connect.php          ← Kết nối MySQL
├── cellphone_k.sql      ← File SQL import database
├── css/
│   ├── shop.css         ← CSS chính (navbar, hero, sản phẩm, giỏ hàng, detail)
│   ├── profile.css      ← CSS trang hồ sơ (sidebar, form, modal, bảng đơn hàng)
│   └── style.css        ← CSS trang đăng nhập / đăng ký
├── js/
│   ├── shop.js          ← JS chính (AJAX giỏ hàng, toast, variant selection, pagination)
│   └── profile.js       ← JS hồ sơ (tab nav, form AJAX, modal, password strength)
├── img/
│   ├── 13_SERIES/       ← Ảnh iPhone 13
│   ├── 14_SERIES/       ← Ảnh iPhone 14
│   ├── 15_SERIES/       ← Ảnh iPhone 15
│   ├── 16_SERIES/       ← Ảnh iPhone 16
│   └── 17_SERIES/       ← Ảnh iPhone 17
└── pages/
    ├── login.php         ← Đăng nhập (Prepared Statement, session unified)
    ├── register.php      ← Đăng ký (Prepared Statement, không SQL injection)
    ├── logout.php        ← Đăng xuất (xóa session)
    ├── detail.php        ← Chi tiết sản phẩm (chọn RAM/màu, thêm giỏ)
    ├── cart.php          ← Giỏ hàng (xem, sửa số lượng, xóa)
    ├── cart_action.php   ← AJAX endpoint giỏ hàng (add/update/remove)
    ├── checkout.php      ← Thanh toán (form giao hàng, COD/Bank)
    ├── process_order.php ← AJAX xử lý đặt hàng (transaction, trừ kho)
    ├── order_success.php ← Trang thành công sau đặt hàng
    └── profile.php       ← Hồ sơ cá nhân (sửa thông tin, đổi MK, lịch sử đơn)
```

## Cách chạy

1. Copy thư mục `output/` vào `D:\Xampp\htdocs\`
2. Import `cellphone_k.sql` vào phpMyAdmin (database tên `cellphone_k`)
3. Truy cập: `http://localhost/output/`

## Nguồn gốc file (tổng hợp từ 4 folder)

| File | Nguồn | Ghi chú |
|------|-------|---------|
| `index.php` | huy | Đầy đủ nhất – lọc hãng, phân trang, AJAX |
| `shop.css` | huy | Thiết kế hoàn chỉnh nhất |
| `shop.js` | huy | AJAX cart, variant, toast |
| `detail.php` | huy | Chọn RAM/màu, tồn kho real-time |
| `cart.php` | huy | Session cart, AJAX update |
| `cart_action.php` | huy | add/update/remove/get_default_variant |
| `profile.php` | huy | AJAX, modal, lịch sử đơn hàng |
| `profile.css` | huy | Sidebar dark, form, modal đẹp |
| `profile.js` | huy | jQuery AJAX, tab, toast |
| `login.php` | huy + fix | Thêm `$_SESSION['user']` cho profile |
| `register.php` | huy + fix | Chuyển sang Prepared Statements |
| `checkout.php` | khoa + rewrite | Viết lại dùng session cart (không cần bảng giohang) |
| `process_order.php` | khoa + rewrite | Transaction, trừ kho, tương thích session cart |
| `order_success.php` | khoa + rewrite | Branding CellPhoneK |

## Session Keys (đồng bộ toàn bộ)

```
$_SESSION['user_id']  = MaKhachHang (int)
$_SESSION['username'] = HoTen hoặc TenDangNhap (string, hiển thị UI)
$_SESSION['user']     = TenDangNhap (string, dùng cho DB queries)
$_SESSION['cart']     = [] (mảng giỏ hàng)
```
