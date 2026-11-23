<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'auth_guard.php';

$currentUser = mfcRequireRoles($conn, ['farmer', 'admin', 'inventory_manager']);
$role = $currentUser['role'];
if (empty($_SESSION['commerce_csrf'])) {
    $_SESSION['commerce_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['inventory_csrf'])) {
    $_SESSION['inventory_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($role, ['admin', 'inventory_manager'], true)
        || !hash_equals($_SESSION['inventory_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('You do not have permission to change inventory.');
    }

    $action = $_POST['inventory_action'] ?? '';
    if ($action === 'save_item') {
        $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT) ?: 0;
        $itemName = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $unitPrice = filter_input(INPUT_POST, 'unit_price', FILTER_VALIDATE_FLOAT);
        $quantity = filter_input(INPUT_POST, 'quantity_in_stock', FILTER_VALIDATE_INT);
        if ($itemName === '' || $category === '' || $unitPrice === false || $unitPrice <= 0 || $quantity === false || $quantity < 0) {
            http_response_code(400);
            exit('Enter a product name, category, valid price, and non-negative stock quantity.');
        }
        if ($itemId > 0) {
            $stmt = $conn->prepare('UPDATE inventory SET item_name = ?, category = ?, unit_price = ?, quantity_in_stock = ? WHERE item_id = ?');
            $stmt->bind_param('ssdii', $itemName, $category, $unitPrice, $quantity, $itemId);
        } else {
            $stmt = $conn->prepare('INSERT INTO inventory (item_name, category, unit_price, quantity_in_stock) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssdi', $itemName, $category, $unitPrice, $quantity);
        }
        $stmt->execute();
        $stmt->close();
        header('Location: inventory.php?inventory_msg=saved');
        exit();
    }
    http_response_code(400);
    exit('Invalid inventory action.');
}

// Fetch available inventory items
$sql = "SELECT * FROM inventory";
$inventory_result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Inputs Catalog | Meru Farmers Centre</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="page-shell">
        <div class="page-header">
            <div>
                <h1>Farm Inputs & Equipment Catalog</h1>
                <p><?php echo in_array($role, ['admin', 'inventory_manager'], true) ? 'Manage item details, prices, and stock levels.' : 'Select products to place an order.'; ?></p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>

        <div class="inventory-toolbar">
            <input type="search" id="productSearch" class="search-input" placeholder="Search by product name or category..." aria-label="Search products">
        </div>

        <?php if (isset($_GET['inventory_msg']) && $_GET['inventory_msg'] === 'saved'): ?><p role="status">Inventory item saved.</p><?php endif; ?>
        <?php if (in_array($role, ['admin', 'inventory_manager'], true)): ?>
            <section class="card" style="margin-bottom:24px;">
                <h2>Add Inventory Item</h2>
                <form method="POST" class="grid">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['inventory_csrf']); ?>">
                    <input type="hidden" name="inventory_action" value="save_item">
                    <label>Product name<input type="text" name="item_name" required maxlength="100"></label>
                    <label>Category<input type="text" name="category" required maxlength="50"></label>
                    <label>Unit price (KSh)<input type="number" name="unit_price" min="0.01" step="0.01" required></label>
                    <label>Initial stock<input type="number" name="quantity_in_stock" min="0" required></label>
                    <button class="btn" type="submit">Add item</button>
                </form>
            </section>
        <?php endif; ?>

        <div class="grid" id="productGrid">
            <?php if ($inventory_result && $inventory_result->num_rows > 0): ?>
                <?php while ($item = $inventory_result->fetch_assoc()): ?>
                    <div class="product-card" data-name="<?php echo strtolower(htmlspecialchars($item['item_name'])); ?>" data-category="<?php echo strtolower(htmlspecialchars($item['category'])); ?>">
                        <div>
                            <span class="category-tag"><?php echo htmlspecialchars($item['category']); ?></span>
                            <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
                            <p class="product-meta">In Stock: <strong><?php echo $item['quantity_in_stock']; ?> units</strong></p>
                            <div class="price">KSh <?php echo number_format($item['unit_price'], 2); ?></div>
                        </div>
                        <?php if ($role === 'farmer'): ?>
                        <form action="order_handler.php" method="POST" class="order-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['commerce_csrf']); ?>">
                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                            <label>Qty:
                                <input type="number" name="quantity" value="1" min="1" max="<?php echo $item['quantity_in_stock']; ?>" required>
                            </label>
                            <label>M-Pesa phone number
                                <input type="tel" name="payment_phone" inputmode="tel" placeholder="Use registered number" pattern="[+0-9 ()-]{9,16}" title="Enter a valid Kenyan mobile number, or leave blank to use your registered number.">
                            </label>
                            <label>Payment method
                                <select name="payment_method" required><option value="mpesa">M-Pesa</option><option value="cash">Cash on delivery</option></select>
                            </label>
                            <label>Delivery address
                                <input type="text" name="delivery_address" maxlength="255" required>
                            </label>
                            <button type="submit" class="btn" data-submit-label="Submitting order...">Place order</button>
                        </form>
                        <?php else: ?>
                            <form method="POST" class="order-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['inventory_csrf']); ?>">
                                <input type="hidden" name="inventory_action" value="save_item">
                                <input type="hidden" name="item_id" value="<?php echo (int) $item['item_id']; ?>">
                                <label>Product name<input type="text" name="item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" maxlength="100" required></label>
                                <label>Category<input type="text" name="category" value="<?php echo htmlspecialchars($item['category'] ?? ''); ?>" maxlength="50" required></label>
                                <label>Unit price (KSh)<input type="number" name="unit_price" value="<?php echo htmlspecialchars((string) $item['unit_price']); ?>" min="0.01" step="0.01" required></label>
                                <label>Stock quantity<input type="number" name="quantity_in_stock" value="<?php echo (int) $item['quantity_in_stock']; ?>" min="0" required></label>
                                <button type="submit" class="btn">Save item</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No inventory items available at the moment.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('productSearch');
        const productCards = document.querySelectorAll('.product-card');

        searchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();

            productCards.forEach(function (card) {
                const name = card.dataset.name || '';
                const category = card.dataset.category || '';
                const isMatch = name.includes(query) || category.includes(query);
                card.style.display = isMatch || query === '' ? 'flex' : 'none';
            });
        });

        document.querySelectorAll('.order-form').forEach(function (form) {
            form.addEventListener('submit', function () {
                const submitButton = form.querySelector('[type="submit"]');
                submitButton.disabled = true;
                submitButton.textContent = submitButton.dataset.submitLabel;
            });
        });
    </script>

</body>
</html>