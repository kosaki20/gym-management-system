<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';
require_once __DIR__ . '/notification_functions.php';

$user_id = $_SESSION['user_id'];
$unread_count = getUnreadCount($user_id, $conn);
$notification_count = getUnreadNotificationCount($conn, $user_id);
$notifications = getAdminNotifications($conn);

// Handle product actions
$action_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_product'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $price = floatval($_POST['price']);
        $stock_quantity = intval($_POST['stock_quantity']);
        
        if ($conn->query("INSERT INTO products (name, price, stock_quantity) VALUES ('$name', '$price', '$stock_quantity')")) {
            $action_message = "Product added successfully!";
            createNotification($conn, null, 'admin', 'New Product Added', "Product '$name' has been added to inventory", 'system', 'medium');
        } else {
            $action_message = "Error adding product: " . $conn->error;
        }
    } elseif (isset($_POST['update_product'])) {
        $id = intval($_POST['product_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $price = floatval($_POST['price']);
        $stock_quantity = intval($_POST['stock_quantity']);
        
        $old_product = $conn->query("SELECT name, stock_quantity FROM products WHERE id=$id")->fetch_assoc();
        
        if ($conn->query("UPDATE products SET name='$name', price='$price', stock_quantity='$stock_quantity' WHERE id=$id")) {
            $action_message = "Product updated successfully!";
            if ($old_product['stock_quantity'] > 0 && $stock_quantity == 0) {
                createNotification($conn, null, 'admin', 'Product Out of Stock', "Product '$name' is now out of stock", 'system', 'high');
            } elseif ($old_product['stock_quantity'] >= 10 && $stock_quantity < 10 && $stock_quantity > 0) {
                createNotification($conn, null, 'admin', 'Low Stock Alert', "Product '$name' is running low (only $stock_quantity left)", 'system', 'medium');
            }
        } else {
            $action_message = "Error updating product: " . $conn->error;
        }
    } elseif (isset($_POST['delete_product'])) {
        $id = intval($_POST['product_id']);
        $product_res = $conn->query("SELECT name FROM products WHERE id=$id");
        $product_name = $product_res ? ($product_res->fetch_assoc()['name'] ?? 'Item') : 'Item';
        
        if ($conn->query("DELETE FROM products WHERE id=$id")) {
            $action_message = "Product deleted successfully!";
            createNotification($conn, null, 'admin', 'Product Deleted', "Product '$product_name' has been removed from inventory", 'system', 'medium');
        } else {
            $action_message = "Error deleting product: " . $conn->error;
        }
    } elseif (isset($_POST['sell_product'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $member_id = !empty($_POST['member_id']) ? intval($_POST['member_id']) : NULL;
        
        $product_query = $conn->query("SELECT * FROM products WHERE id = $product_id");
        if ($product_query && $product_query->num_rows > 0) {
            $product = $product_query->fetch_assoc();
            
            if ($product['stock_quantity'] >= $quantity) {
                $total_amount = $product['price'] * $quantity;
                $conn->begin_transaction();
                
                try {
                    $new_stock = $product['stock_quantity'] - $quantity;
                    $conn->query("UPDATE products SET stock_quantity = $new_stock WHERE id = $product_id");
                    
                    $items = json_encode([[
                        'id' => $product_id,
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'quantity' => $quantity
                    ]]);
                    
                    $sold_by = $_SESSION['user_id'];
                    $member_sql_val = $member_id ? $member_id : "NULL";
                    $conn->query("INSERT INTO sales (items, total_amount, payment_method, member_id, sold_by) 
                                 VALUES ('$items', $total_amount, '$payment_method', $member_sql_val, $sold_by)");
                    
                    $description = "Sale of $quantity x {$product['name']}";
                    $reference_name = $member_id ? "Member Purchase" : "Walk-in Purchase";
                    
                    $revenue_sql = "INSERT INTO revenue_entries (category_id, amount, description, payment_method, 
                                  reference_id, reference_name, revenue_date, recorded_by) 
                                  VALUES (1, ?, ?, ?, ?, ?, CURDATE(), ?)";
                    
                    $stmt = $conn->prepare($revenue_sql);
                    $stmt->bind_param("dssisi", $total_amount, $description, $payment_method, 
                                    $member_id, $reference_name, $_SESSION['user_id']);
                    $stmt->execute();
                    $conn->commit();
                    
                    $action_message = "Product sold successfully! ₱" . number_format($total_amount, 2) . " revenue recorded.";
                    createNotification($conn, null, 'admin', 'Product Sold', "Sold $quantity x {$product['name']} for ₱" . number_format($total_amount, 2), 'system', 'medium');
                } catch (Exception $e) {
                    $conn->rollback();
                    $action_message = "Error processing sale: " . $e->getMessage();
                }
            } else {
                $action_message = "Error: Insufficient stock available.";
            }
        }
    }
}

// Fetch products & members
$products_result = $conn->query("SELECT * FROM products ORDER BY id DESC");
$low_stock_products = $conn->query("SELECT * FROM products WHERE stock_quantity < 10 ORDER BY stock_quantity ASC");
$members_result = $conn->query("SELECT id, full_name FROM members WHERE status = 'active' ORDER BY full_name");

// Calculate statistics
$total_products = $products_result ? $products_result->num_rows : 0;
$total_value_result = $conn->query("SELECT SUM(price * stock_quantity) as total_value FROM products");
$total_value = $total_value_result ? ($total_value_result->fetch_assoc()['total_value'] ?? 0) : 0;
$low_stock_count = $low_stock_products ? $low_stock_products->num_rows : 0;

$today_sales_result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as today_sales FROM sales WHERE DATE(sold_at) = CURDATE()");
$today_sales = $today_sales_result ? ($today_sales_result->fetch_assoc()['today_sales'] ?? 0) : 0;

$page_title = "Products & Inventory — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="package" style="color: var(--accent);"></i>
        Products & Inventory Management
      </h1>
      <p class="gym-page-subtitle">Track product stock levels, inventory valuation, daily POS revenue, and low-stock alerts.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
      <a href="countersales.php" class="gym-btn gym-btn-yellow">
        <i data-lucide="shopping-cart"></i> Open POS Terminal
      </a>
    </div>
  </div>

  <?php if (!empty($action_message)): ?>
    <div style="background: <?php echo strpos($action_message, 'Error') !== false ? 'rgba(239, 68, 68, 0.15)' : 'rgba(34, 197, 94, 0.15)'; ?>; border: 1px solid <?php echo strpos($action_message, 'Error') !== false ? 'rgba(239, 68, 68, 0.4)' : 'rgba(34, 197, 94, 0.4)'; ?>; color: <?php echo strpos($action_message, 'Error') !== false ? '#f87171' : '#4ade80'; ?>; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="<?php echo strpos($action_message, 'Error') !== false ? 'alert-triangle' : 'check-circle-2'; ?>" style="width: 18px; height: 18px; color: <?php echo strpos($action_message, 'Error') !== false ? '#ef4444' : '#22c55e'; ?>;"></i>
      <span><?php echo htmlspecialchars($action_message); ?></span>
    </div>
  <?php endif; ?>

  <!-- KPI Statistics Grid -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Products</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo number_format($total_products); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Active inventory items</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="package"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Inventory Valuation</div>
        <div class="gym-stat-number" style="color: #22c55e;">₱<?php echo number_format($total_value, 2); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Total asset value</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #22c55e; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="dollar-sign"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Today's POS Sales</div>
        <div class="gym-stat-number" style="color: var(--accent);">₱<?php echo number_format($today_sales, 2); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Counter sales revenue</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
        <i data-lucide="trending-up"></i>
      </div>
    </div>

    <div class="gym-stat-card" style="border-top-color: var(--red);">
      <div>
        <div class="gym-stat-label">Low Stock Alerts</div>
        <div class="gym-stat-number" style="color: var(--red);"><?php echo number_format($low_stock_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Items under 10 units</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(239, 68, 68, 0.15); color: var(--red); border-color: rgba(239, 68, 68, 0.3);">
        <i data-lucide="alert-triangle"></i>
      </div>
    </div>
  </div>

  <!-- Products Inventory Table Card -->
  <div class="gym-card" style="margin-top: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
      <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="list" style="color: var(--accent);"></i>
        All Products Inventory
      </h2>
      <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <div style="position: relative; width: 280px; max-width: 100%;">
          <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-dim);"></i>
          <input type="text" id="searchProducts" placeholder="Search products by name..." class="gym-form-control" style="padding-left: 38px; height: 40px; margin: 0;">
        </div>
        <button onclick="openModal()" class="gym-btn gym-btn-yellow" style="min-height: 40px; padding: 0 16px;">
          <i data-lucide="plus"></i> Add Product
        </button>
      </div>
    </div>

    <div class="gym-table-wrapper" style="margin-bottom: 0;">
      <table class="gym-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Product Name</th>
            <th>Price</th>
            <th>Stock Quantity</th>
            <th>Status</th>
            <th>Added Date</th>
            <th style="text-align: center;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($products_result && $products_result->num_rows > 0): ?>
            <?php while($product = $products_result->fetch_assoc()): ?>
            <?php
            $stock_badge = '';
            if ($product['stock_quantity'] == 0) {
                $stock_badge = '<span class="gym-badge gym-badge-inactive">Out of Stock</span>';
            } elseif ($product['stock_quantity'] < 10) {
                $stock_badge = '<span class="gym-badge gym-badge-pending">Low Stock</span>';
            } else {
                $stock_badge = '<span class="gym-badge gym-badge-active">In Stock</span>';
            }
            ?>
            <tr>
              <td style="font-weight: 700; color: var(--text-dim);"><?php echo $product['id']; ?></td>
              <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($product['name']); ?></td>
              <td style="font-weight: 700; color: #22c55e;">₱<?php echo number_format($product['price'], 2); ?></td>
              <td style="font-weight: 700;"><?php echo number_format($product['stock_quantity']); ?></td>
              <td><?php echo $stock_badge; ?></td>
              <td style="color: var(--text-dim);"><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
              <td>
                <div style="display: flex; gap: 6px; align-items: center; justify-content: center;">
                  <button onclick="editProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, <?php echo $product['stock_quantity']; ?>)" 
                          class="gym-btn gym-btn-outline" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important; color: #60a5fa !important; border-color: rgba(96, 165, 250, 0.3) !important;">
                    <i data-lucide="edit" style="width: 14px; height: 14px;"></i> Edit
                  </button>
                  <button onclick="openSellModal(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, <?php echo $product['stock_quantity']; ?>)" 
                          class="gym-btn gym-btn-yellow" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                    <i data-lucide="shopping-cart" style="width: 14px; height: 14px;"></i> Sell
                  </button>
                  <form method="POST" onsubmit="return confirm('Are you sure you want to delete this product? This action cannot be undone.')" style="margin: 0;">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <button type="submit" name="delete_product" class="gym-btn gym-btn-danger" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                      <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i> Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
                <i data-lucide="package-search" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
                <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0 0 1rem;">No products found in inventory.</p>
                <button onclick="openModal()" class="gym-btn gym-btn-yellow" style="margin: 0 auto;">
                  <i data-lucide="plus"></i> Add First Product
                </button>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Add/Edit Product Modal -->
  <div id="productModal" class="modal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.7); align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); width: 100%; max-width: 480px; padding: 24px; margin: auto;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 id="modalTitle" style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.2rem; color: var(--accent); margin: 0;">Add New Product</h3>
        <button type="button" onclick="closeModal()" style="background: transparent; border: none; color: var(--text-dim); cursor: pointer; font-size: 1.2rem;">
          <i data-lucide="x"></i>
        </button>
      </div>

      <form method="POST" id="productForm" style="display: flex; flex-direction: column; gap: 16px;">
        <input type="hidden" id="productId" name="product_id">
        
        <div>
          <label class="gym-form-label">Product Name *</label>
          <input type="text" id="productName" name="name" class="gym-form-control" placeholder="e.g. Whey Protein Powder 1kg" required>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div>
            <label class="gym-form-label">Price (₱) *</label>
            <input type="number" step="0.01" min="0" id="productPrice" name="price" class="gym-form-control" placeholder="0.00" required>
          </div>
          <div>
            <label class="gym-form-label">Stock Quantity *</label>
            <input type="number" min="0" id="productStock" name="stock_quantity" class="gym-form-control" placeholder="0" required>
          </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 10px;">
          <button type="button" onclick="closeModal()" class="gym-btn gym-btn-outline" style="flex: 1;">Cancel</button>
          <button type="submit" id="submitBtn" name="add_product" class="gym-btn gym-btn-yellow" style="flex: 1;">Add Product</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Sell Product Modal -->
  <div id="sellModal" class="modal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.7); align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); width: 100%; max-width: 480px; padding: 24px; margin: auto;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.2rem; color: #22c55e; margin: 0; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="shopping-cart"></i> Sell Product
        </h3>
        <button type="button" onclick="closeSellModal()" style="background: transparent; border: none; color: var(--text-dim); cursor: pointer; font-size: 1.2rem;">
          <i data-lucide="x"></i>
        </button>
      </div>

      <form method="POST" id="sellForm" style="display: flex; flex-direction: column; gap: 16px;">
        <input type="hidden" id="sellProductId" name="product_id">
        
        <div style="background: var(--bg-surface); border: 1px solid var(--border); padding: 14px; border-radius: var(--radius-sm);">
          <h4 style="font-family: 'Outfit', sans-serif; font-weight: 700; color: var(--accent); margin: 0 0 8px;" id="sellProductName">Product Name</h4>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.85rem;">
            <div>
              <span style="color: var(--text-dim);">Price:</span>
              <strong id="sellProductPrice" style="color: #22c55e; margin-left: 6px;">₱0.00</strong>
            </div>
            <div>
              <span style="color: var(--text-dim);">Stock:</span>
              <strong id="sellProductStock" style="color: var(--text-primary); margin-left: 6px;">0</strong>
            </div>
          </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div>
            <label class="gym-form-label">Quantity *</label>
            <input type="number" id="sellQuantity" name="quantity" min="1" value="1" class="gym-form-control" required onchange="calculateTotal()" oninput="calculateTotal()">
          </div>
          <div>
            <label class="gym-form-label">Payment Method *</label>
            <select name="payment_method" class="gym-form-control" required>
              <option value="cash">Cash</option>
              <option value="gcash">GCash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="card">Card</option>
            </select>
          </div>
        </div>
        
        <div>
          <label class="gym-form-label">Customer (Optional)</label>
          <select name="member_id" class="gym-form-control">
            <option value="">Walk-in Customer</option>
            <?php
            if ($members_result && $members_result->num_rows > 0) {
                $members_result->data_seek(0);
                while($member = $members_result->fetch_assoc()): ?>
                  <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['full_name']); ?></option>
                <?php endwhile;
            }
            ?>
          </select>
        </div>
        
        <div style="background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.3); padding: 14px; border-radius: var(--radius-sm); display: flex; justify-content: space-between; align-items: center;">
          <span style="color: #4ade80; font-weight: 600; font-size: 0.9rem;">Total Amount:</span>
          <span id="totalAmount" style="color: #4ade80; font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.3rem;">₱0.00</span>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 6px;">
          <button type="button" onclick="closeSellModal()" class="gym-btn gym-btn-outline" style="flex: 1;">Cancel</button>
          <button type="submit" name="sell_product" class="gym-btn gym-btn-yellow" style="flex: 1; background: #22c55e !important; color: #fff !important; border-color: #22c55e !important;">
            <i data-lucide="shopping-cart"></i> Complete Sale
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  let currentProductPrice = 0;

  document.addEventListener('DOMContentLoaded', function() {
      if (typeof lucide !== 'undefined') {
          lucide.createIcons();
      }
      
      const searchInput = document.getElementById('searchProducts');
      if (searchInput) {
          searchInput.addEventListener('input', function(e) {
              const searchTerm = e.target.value.toLowerCase().trim();
              const rows = document.querySelectorAll('.gym-table tbody tr');
              
              rows.forEach(row => {
                  const text = row.textContent.toLowerCase();
                  row.style.display = text.includes(searchTerm) ? '' : 'none';
              });
          });
      }
  });

  function openModal() {
      const modal = document.getElementById('productModal');
      if (modal) {
          modal.style.display = 'flex';
          resetModal();
      }
  }

  function editProduct(id, name, price, stock) {
      const modal = document.getElementById('productModal');
      if (modal) {
          modal.style.display = 'flex';
          document.getElementById('modalTitle').textContent = 'Edit Product';
          document.getElementById('productId').value = id;
          document.getElementById('productName').value = name;
          document.getElementById('productPrice').value = price;
          document.getElementById('productStock').value = stock;
          document.getElementById('submitBtn').name = 'update_product';
          document.getElementById('submitBtn').textContent = 'Update Product';
      }
  }

  function resetModal() {
      document.getElementById('modalTitle').textContent = 'Add New Product';
      const form = document.getElementById('productForm');
      if (form) form.reset();
      document.getElementById('productId').value = '';
      document.getElementById('submitBtn').name = 'add_product';
      document.getElementById('submitBtn').textContent = 'Add Product';
  }

  function closeModal() {
      const modal = document.getElementById('productModal');
      if (modal) modal.style.display = 'none';
  }

  function openSellModal(id, name, price, stock) {
      const sellModal = document.getElementById('sellModal');
      if (sellModal) {
          sellModal.style.display = 'flex';
          document.getElementById('sellProductId').value = id;
          document.getElementById('sellProductName').textContent = name;
          document.getElementById('sellProductPrice').textContent = '₱' + parseFloat(price).toFixed(2);
          document.getElementById('sellProductStock').textContent = stock;
          
          const qtyInput = document.getElementById('sellQuantity');
          qtyInput.max = stock;
          qtyInput.value = 1;
          
          currentProductPrice = price;
          calculateTotal();
      }
  }

  function closeSellModal() {
      const sellModal = document.getElementById('sellModal');
      if (sellModal) sellModal.style.display = 'none';
  }

  function calculateTotal() {
      const qtyInput = document.getElementById('sellQuantity');
      const quantity = parseInt(qtyInput ? qtyInput.value : 1) || 0;
      const total = quantity * currentProductPrice;
      const totalEl = document.getElementById('totalAmount');
      if (totalEl) totalEl.textContent = '₱' + total.toFixed(2);
  }

  window.onclick = function(event) {
      const modal = document.getElementById('productModal');
      const sellModal = document.getElementById('sellModal');
      if (event.target === modal) closeModal();
      if (event.target === sellModal) closeSellModal();
  };
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
