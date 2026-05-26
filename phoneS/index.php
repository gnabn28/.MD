<?php
session_start();
require 'connect.php';

// =========================================
// XỬ LÝ PHÂN TRANG & TÌM KIẾM
// =========================================
$limit        = 12;
$page         = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset       = ($page - 1) * $limit;
$search       = isset($_GET['search']) ? trim($_GET['search']) : '';
$brand_filter = isset($_GET['hang'])   ? trim($_GET['hang'])   : '';

// =========================================
// ĐẾM TỔNG SỐ SẢN PHẨM (để phân trang)
// =========================================
$where_clauses = ["1=1"];
$params        = [];
$types         = '';

if ($search !== '') {
    $where_clauses[] = "sp.TenSanPham LIKE ?";
    $params[]        = "%$search%";
    $types          .= 's';
}
if ($brand_filter !== '') {
    $where_clauses[] = "sp.Hang = ?";
    $params[]        = $brand_filter;
    $types          .= 's';
}
$where_sql = implode(' AND ', $where_clauses);

$count_sql  = "SELECT COUNT(DISTINCT sp.MaSanPham) as total
               FROM sanpham sp
               INNER JOIN giasanpham gsp ON sp.MaSanPham = gsp.MaSanPham
               INNER JOIN image img ON img.MaSanPham = sp.MaSanPham
               WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($types !== '') {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows  = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

// =========================================
// TRUY VẤN DANH SÁCH SẢN PHẨM
// =========================================
$list_sql = "
    SELECT
        sp.MaSanPham,
        sp.TenSanPham,
        sp.Hang,
        MIN(gsp.GiaMoi)  AS GiaMoi,
        MIN(gsp.GiaCu)   AS GiaCu,
        SUM(gsp.SoLuong) AS TongTonKho,
        (
            SELECT img2.DiaChiAnh
            FROM image img2
            WHERE img2.MaSanPham = sp.MaSanPham
            ORDER BY img2.MaHinhAnh ASC
            LIMIT 1
        ) AS DiaChiAnh
    FROM sanpham sp
    INNER JOIN giasanpham gsp ON sp.MaSanPham = gsp.MaSanPham
    INNER JOIN image img     ON img.MaSanPham  = sp.MaSanPham
    WHERE $where_sql
    GROUP BY sp.MaSanPham, sp.TenSanPham, sp.Hang
    ORDER BY sp.MaSanPham ASC
    LIMIT ? OFFSET ?
";

$param_types = $types . 'ii';
$all_params  = array_merge($params, [$limit, $offset]);

$list_stmt = $conn->prepare($list_sql);
$list_stmt->bind_param($param_types, ...$all_params);
$list_stmt->execute();
$products = $list_stmt->get_result();
$list_stmt->close();

// =========================================
// LẤY DANH SÁCH HÃNG ĐỂ LỌC
// =========================================
$brands_result = $conn->query("SELECT DISTINCT Hang FROM sanpham ORDER BY Hang ASC");
$brands = [];
while ($b = $brands_result->fetch_assoc()) {
    $brands[] = $b['Hang'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CellPhone Store – Mua điện thoại chính hãng</title>
    <meta name="description" content="Cửa hàng điện thoại chính hãng – Samsung, iPhone, Xiaomi, Oppo, Realme với giá tốt nhất.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/shop.css">
</head>
<body>

<!-- ===== HEADER / NAVBAR ===== -->
<header class="navbar">
    <div class="container nav-inner">
        <a href="index.php" class="logo">
            <span>CellPhone<strong>K</strong></span>
        </a>

        <form method="GET" action="index.php" class="search-form" id="searchForm">
            <input type="text" name="search" id="searchInput"
                   placeholder="Tìm kiếm điện thoại..."
                   value="<?= htmlspecialchars($search) ?>"
                   autocomplete="off">
            <?php if ($brand_filter): ?>
                <input type="hidden" name="hang" value="<?= htmlspecialchars($brand_filter) ?>">
            <?php endif; ?>
            <button type="submit" class="search-btn">🔍</button>
        </form>

        <nav class="nav-links">
            <a href="index.php">Trang chủ</a>
            <a href="pages/cart.php" class="cart-link" id="cartNavLink">
                🛒 Giỏ hàng
                <?php
                $cartCount = 0;
                if (!empty($_SESSION['cart'])) {
                    foreach ($_SESSION['cart'] as $item) {
                        $cartCount += $item['quantity'];
                    }
                }
                ?>
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge" id="cartBadge"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="pages/profile.php" class="btn-nav-user">👤 <?= htmlspecialchars($_SESSION['username'] ?? 'Tài khoản') ?></a>
                <a href="pages/logout.php" class="btn-nav-logout">Đăng xuất</a>
            <?php else: ?>
                <a href="pages/login.php" class="btn-nav-login">Đăng nhập</a>
                <a href="pages/register.php" class="btn-nav-register">Đăng ký</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<!-- ===== HERO BANNER ===== -->
<section class="hero">
    <div class="container hero-content">
        <h1>Điện Thoại Chính Hãng</h1>
        <p>Khám phá hàng ngàn mẫu điện thoại với giá tốt nhất thị trường</p>
        <div class="hero-stats">
            <div class="stat"><strong><?= $total_rows ?>+</strong><span>Sản phẩm</span></div>
            <div class="stat"><strong>50+</strong><span>Thương hiệu</span></div>
            <div class="stat"><strong>100%</strong><span>Chính hãng</span></div>
        </div>
    </div>
</section>

<!-- ===== MAIN CONTENT ===== -->
<main class="container main-layout">

    <!-- Sidebar lọc -->
    <aside class="sidebar">
        <div class="filter-card">
            <h3 class="filter-title">🏷️ Lọc theo hãng</h3>
            <ul class="brand-list">
                <li>
                    <a href="index.php<?= $search ? '?search='.urlencode($search) : '' ?>"
                       class="brand-item <?= $brand_filter === '' ? 'active' : '' ?>">
                        Tất cả hãng
                    </a>
                </li>
                <?php foreach ($brands as $brand): ?>
                    <li>
                        <?php
                        $href = 'index.php?hang=' . urlencode($brand);
                        if ($search) $href .= '&search=' . urlencode($search);
                        ?>
                        <a href="<?= $href ?>"
                           class="brand-item <?= $brand_filter === $brand ? 'active' : '' ?>">
                            <?= htmlspecialchars($brand) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>

    <!-- Khu vực sản phẩm -->
    <section class="products-area">
        <!-- Thông báo -->
        <div id="toastMessage" class="toast hidden"></div>

        <!-- Tiêu đề + bộ lọc -->
        <div class="products-header">
            <div class="results-info">
                <?php if ($search || $brand_filter): ?>
                    <span>
                        Kết quả cho
                        <?php if ($search): ?> "<strong><?= htmlspecialchars($search) ?></strong>"<?php endif; ?>
                        <?php if ($brand_filter): ?> hãng <strong><?= htmlspecialchars($brand_filter) ?></strong><?php endif; ?>
                        — <strong><?= $total_rows ?></strong> sản phẩm
                    </span>
                <?php else: ?>
                    <span>Hiển thị <strong><?= min($offset + $limit, $total_rows) ?></strong> / <strong><?= $total_rows ?></strong> sản phẩm</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Grid sản phẩm -->
        <?php if ($products->num_rows === 0): ?>
            <div class="no-products">
                <p>😕 Không tìm thấy sản phẩm nào phù hợp.</p>
                <a href="index.php" class="btn-primary">Xem tất cả sản phẩm</a>
            </div>
        <?php else: ?>
        <div class="product-grid" id="productGrid">
            <?php while ($p = $products->fetch_assoc()): ?>
                <?php
                $imgPath = !empty($p['DiaChiAnh'])
                    ? htmlspecialchars($p['DiaChiAnh'])
                    : 'img/no-image.svg';
                $discount = ($p['GiaCu'] > 0 && $p['GiaCu'] > $p['GiaMoi'])
                    ? round((1 - $p['GiaMoi'] / $p['GiaCu']) * 100)
                    : 0;
                ?>
                <div class="product-card" data-id="<?= $p['MaSanPham'] ?>">
                    <?php if ($discount > 0): ?>
                        <span class="badge-discount">-<?= $discount ?>%</span>
                    <?php endif; ?>

                    <a href="pages/detail.php?id=<?= $p['MaSanPham'] ?>" class="product-img-wrap">
                        <img src="<?= $imgPath ?>"
                             alt="<?= htmlspecialchars($p['TenSanPham']) ?>"
                             loading="lazy"
                             onerror="this.src='img/no-image.svg'">
                    </a>

                    <div class="product-info">
                        <span class="product-brand"><?= htmlspecialchars($p['Hang']) ?></span>
                        <h2 class="product-name">
                            <a href="pages/detail.php?id=<?= $p['MaSanPham'] ?>">
                                <?= htmlspecialchars($p['TenSanPham']) ?>
                            </a>
                        </h2>
                        <div class="product-price">
                            <span class="price-new"><?= number_format($p['GiaMoi'], 0, ',', '.') ?>đ</span>
                            <?php if ($p['GiaCu'] > $p['GiaMoi']): ?>
                                <span class="price-old"><?= number_format($p['GiaCu'], 0, ',', '.') ?>đ</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:5px;">
                            <i class="fa-solid fa-boxes-stacked"></i> Còn lại: <?= $p['TongTonKho'] ?: 0 ?> sản phẩm
                        </div>
                    </div>

                    <div class="product-actions">
                        <a href="pages/detail.php?id=<?= $p['MaSanPham'] ?>" class="btn-detail">
                            Xem chi tiết
                        </a>
                        <button class="btn-add-cart"
                                data-id="<?= $p['MaSanPham'] ?>"
                                data-name="<?= htmlspecialchars($p['TenSanPham'], ENT_QUOTES) ?>"
                                onclick="quickAddToCart(this)">
                            🛒 Thêm vào giỏ
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- PHÂN TRANG -->
        <?php if ($total_pages > 1): ?>
            <nav class="pagination" aria-label="Phân trang">
                <?php
                $query_base = '';
                if ($search)       $query_base .= '&search=' . urlencode($search);
                if ($brand_filter) $query_base .= '&hang='   . urlencode($brand_filter);
                ?>

                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $query_base ?>" class="page-btn">‹ Trước</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($total_pages, $page + 2);
                if ($start > 1): ?>
                    <a href="?page=1<?= $query_base ?>" class="page-btn">1</a>
                    <?php if ($start > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?page=<?= $i ?><?= $query_base ?>"
                       class="page-btn <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
                    <a href="?page=<?= $total_pages ?><?= $query_base ?>" class="page-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $query_base ?>" class="page-btn">Sau ›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<!-- ===== FOOTER ===== -->
<footer class="footer">
    <div class="container footer-inner">
        <p>© 2026 <strong>CellPhoneK</strong> – Điện thoại chính hãng giá tốt</p>
    </div>
</footer>

<script src="js/shop.js"></script>
</body>
</html>
