<?php
$BASE = '..'; $ROLE = 'admin'; $PAGE_TITLE = 'Manage Gallons';
$PAGE_JS = 'pages/admin-gallons.js';
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('admin');

$GALLON_TYPES  = ['Purified', 'Mineral', 'Alkaline', 'Distilled', 'Container'];
$GALLON_SIZES  = ['5 Gallons', '1 Gallon'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $action = $_POST['action'] ?? 'add';

        if ($action === 'add') {
            $name  = trim((string)($_POST['name'] ?? ''));
            $type  = trim((string)($_POST['type'] ?? ''));
            $size  = trim((string)($_POST['size'] ?? ''));
            $price = (float)($_POST['price'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $desc  = trim((string)($_POST['description'] ?? ''));

            if ($name === '' || $price < 0 || $stock < 0
                || !in_array($type, $GALLON_TYPES, true)
                || !in_array($size, $GALLON_SIZES, true)) {
                flash_set('error', 'Please provide a valid gallon name, type, size, price, and stock.');
            } else {
                $ok = create_gallon(
                    ['name' => $name, 'type' => $type, 'size' => $size,
                     'price' => $price, 'stock' => $stock, 'description' => $desc],
                    $err
                );
                flash_set($ok ? 'success' : 'error', $ok ? 'Gallon saved.' : ($err ?: 'Could not save gallon.'));
            }
        } elseif ($action === 'update_stock') {
            $id    = (int)($_POST['id'] ?? 0);
            $price = (float)($_POST['price'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            if ($price < 0 || $stock < 0) {
                flash_set('error', 'Price and stock cannot be negative.');
            } else {
                $ok = db_exec(
                    'UPDATE gallons SET price_per_gallon = ?, stock = ? WHERE id = ?',
                    [$price, $stock, $id], $err
                );
                flash_set($ok ? 'success' : 'error', $ok ? 'Gallon updated.' : ($err ?: 'Update failed.'));
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $used = db_one('SELECT COUNT(*) AS c FROM delivery_items WHERE gallon_id = ?', [$id]);
            if ($used && (int)$used['c'] > 0) {
                flash_set('error', 'This gallon has delivery history and cannot be deleted. Mark it unavailable instead.');
            } else {
                $ok = db_exec('DELETE FROM gallons WHERE id = ?', [$id], $err);
                flash_set($ok ? 'success' : 'error', $ok ? 'Gallon deleted.' : ($err ?: 'Delete failed.'));
            }
        }
    }
    header('Location: gallons.php');
    exit;
}

$GALLONS = load_gallons();
include __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <div class="card__head">
    <h3>List Per Gallon</h3>
    <div class="spacer toolbar no-print">
      <input type="search" data-search="gallonTable" placeholder="Search gallon...">
      <select data-filter="gallonTable">
        <option value="">All types</option>
        <?php foreach (array_unique(array_column($GALLONS, 'type')) as $t): ?>
          <option value="<?= e($t) ?>"><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--ghost btn--sm" onclick="exportTable('gallonTable','tubigko-gallons')">Export Excel File</button>
      <button class="btn btn--sm" onclick="openModal('addGallonModal')">Add gallon</button>
    </div>
  </div>
  <div class="table-wrap">
    <table id="gallonTable">
      <thead><tr><th>Code</th><th>Gallon</th><th>Type</th><th>Size</th><th>Price</th><th>Stock</th><th class="no-print">Action</th></tr></thead>
      <tbody>
      <?php foreach ($GALLONS as $g):
        $detail = json_encode([
          '__title'        => $g['name'],
          'Gallon code'    => $g['id'],
          'Name'           => $g['name'],
          'Water type'     => $g['type'],
          'Size'           => $g['size'],
          'Price'          => 'PHP ' . number_format($g['price'], 2),
          'Available stock'=> $g['stock'],
          'Description'    => $g['desc'],
        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG); ?>
        <tr data-filter-value="<?= e($g['type']) ?>">
          <td><strong><?= e($g['id']) ?></strong></td>
          <td><?= e($g['name']) ?><br><small class="muted"><?= e($g['desc']) ?></small></td>
          <td><?= e($g['type']) ?></td>
          <td><?= e($g['size']) ?></td>
          <td><?= peso($g['price']) ?></td>
          <td><span class="<?= $g['stock'] < 50 ? 'badge badge--warn' : 'badge badge--ok' ?>"><?= (int)$g['stock'] ?> pcs</span></td>
          <td class="no-print" style="display:flex;gap:.35rem;flex-wrap:wrap">
            <button class="btn btn--ghost btn--sm" data-detail='<?= $detail ?>'>View</button>
            <button class="btn btn--ghost btn--sm" data-open-gallon-edit
                    data-id="<?= (int)$g['db_id'] ?>"
                    data-price="<?= e((string)$g['price']) ?>"
                    data-stock="<?= (int)$g['stock'] ?>">Edit</button>
            <form method="post" action="gallons.php" style="display:inline" onsubmit="return confirm('Delete this gallon?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$g['db_id'] ?>">
              <button class="btn btn--ghost btn--sm" type="submit" style="color:var(--bad)">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
        <tr data-empty hidden><td colspan="7" class="empty">No gallons match your search.</td></tr>
      </tbody>
    </table>
  </div>
</section>

<div class="modal" id="detailModal" hidden>
  <div class="modal__box">
    <div class="modal__head"><h3 id="detailTitle">Gallon details</h3><button class="icon-btn" onclick="closeModal('detailModal')">&times;</button></div>
    <div class="modal__body" id="detailBody"></div>
  </div>
</div>

<div class="modal" id="addGallonModal" hidden>
  <div class="modal__box">
    <div class="modal__head"><h3>Add new gallon</h3><button class="icon-btn" onclick="closeModal('addGallonModal')">&times;</button></div>
    <div class="modal__body">
      <form method="post" action="gallons.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="field"><label for="gname">Gallon name</label><input id="gname" name="name" required></div>
        <div class="form-row">
          <div class="field"><label for="gtype">Water type</label>
            <select id="gtype" name="type">
              <?php foreach ($GALLON_TYPES as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label for="gsize">Size</label>
            <select id="gsize" name="size">
              <?php foreach ($GALLON_SIZES as $s): ?><option><?= e($s) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="field"><label for="gprice">Price (PHP)</label><input id="gprice" name="price" type="number" min="0" step="1" required></div>
          <div class="field"><label for="gstock">Stock</label><input id="gstock" name="stock" type="number" min="0" required></div>
        </div>
        <div class="field"><label for="gdesc">Description</label><textarea id="gdesc" name="description"></textarea></div>
        <button class="btn btn--block" type="submit">Save gallon</button>
      </form>
    </div>
  </div>
</div>

<div class="modal" id="editGallonModal" hidden>
  <div class="modal__box">
    <div class="modal__head"><h3>Update price &amp; stock</h3><button class="icon-btn" onclick="closeModal('editGallonModal')">&times;</button></div>
    <div class="modal__body">
      <form method="post" action="gallons.php" id="editGallonForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_stock">
        <input type="hidden" name="id" id="editGallonId">
        <div class="form-row">
          <div class="field"><label for="editPrice">Price (PHP)</label><input id="editPrice" name="price" type="number" min="0" step="0.01" required></div>
          <div class="field"><label for="editStock">Stock</label><input id="editStock" name="stock" type="number" min="0" required></div>
        </div>
        <button class="btn btn--block" type="submit">Save changes</button>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>