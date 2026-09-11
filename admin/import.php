<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$error = '';
$report = null;

function cleanPrice(string $v): float {
    $v = trim($v);
    $v = str_replace(' ', '', $v);
    // Quitar separador de miles chileno (punto + 3 cifras al final): 8.990 -> 8990
    $v = preg_replace('/\.(?=\d{3}$)/', '', $v);
    // Coma decimal -> punto
    $v = str_replace(',', '.', $v);
    return (float)$v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'La solicitud expiró. Vuelve a intentarlo.';
    } elseif (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Selecciona un archivo CSV válido.';
    } else {
        $file = $_FILES['csv'];

        if ($file['size'] > 2 * 1024 * 1024) {
            $error = 'El archivo supera el tamaño máximo de 2 MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'text/plain', 'text/csv', 'application/csv',
                'application/vnd.ms-excel', 'text/x-csv', 'application/octet-stream',
            ];

            if (!in_array($mime, $allowedMimes, true)) {
                $error = 'El archivo no parece un CSV (' . htmlspecialchars($mime) . '). Usa la plantilla exportada.';
            } else {
                $handle = fopen($file['tmp_name'], 'rb');
                $headers = fgetcsv($handle, 0, ';');

                if ($headers === false) {
                    fclose($handle);
                    $error = 'No se pudo leer el archivo CSV.';
                } else {
                    $headers = array_map('strtolower', $headers);
                    // Quitar BOM UTF-8 del primer encabezado si existe
                    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
                    $headers = array_map('trim', $headers);

                    if (!in_array('name', $headers, true) || !in_array('category', $headers, true) || !in_array('price', $headers, true)) {
                        fclose($handle);
                        $error = 'El CSV debe incluir al menos las columnas: name, category, price. Usa el archivo exportado como plantilla.';
                    } else {
                        $catRows = $pdo->query("SELECT name FROM categories")->fetchAll(PDO::FETCH_COLUMN);
                        $validCats = $catRows;

                        $created = 0;
                        $updated = 0;
                        $skipped = [];
                        $rowNum = 1; // fila de datos, la cabecera es la 1

                        while (($data = fgetcsv($handle, 0, ';')) !== false) {
                            $rowNum++;
                            if ($rowNum > 1501) {
                                $skipped[] = 'Se alcanzó el límite de 1500 filas; el resto se omitió.';
                                break;
                            }
                            if (count($data) < 2) {
                                continue; // fila vacía
                            }

                            $row = [];
                            foreach ($headers as $i => $h) {
                                $row[$h] = isset($data[$i]) ? trim($data[$i]) : '';
                            }

                            $name = $row['name'] ?? '';
                            $category = $row['category'] ?? '';
                            $priceRaw = $row['price'] ?? '';
                            $price = cleanPrice($priceRaw);

                            if ($name === '' || !in_array($category, $validCats, true)) {
                                $skipped[] = "Fila $rowNum: falta el nombre o la categoría '$category' no existe.";
                                continue;
                            }
                            if ($price <= 0) {
                                $skipped[] = "Fila $rowNum: precio inválido ('" . ($priceRaw !== '' ? $priceRaw : 'vacío') . "').";
                                continue;
                            }

                            $description = $row['description'] ?? '';
                            $oldPriceRaw = $row['old_price'] ?? '';
                            $oldPrice = $oldPriceRaw !== '' ? cleanPrice($oldPriceRaw) : null;

                            $emoji = $row['emoji'] ?? '';
                            $sortOrder = (int)($row['sort_order'] ?? 0);
                            $active = in_array(strtolower($row['active'] ?? ''), ['0', 'false', 'no', 'off', 'inactivo'], true) ? 0 : 1;

                            // Nota: image e image_thumb se ignoran; se suben desde el formulario del producto.
                            $idRaw = (int)($row['id'] ?? 0);

                            if ($idRaw > 0) {
                                $check = $pdo->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
                                $check->execute([$idRaw]);
                                if (!$check->fetch()) {
                                    $skipped[] = "Fila $rowNum: el id $idRaw no existe en la base de datos.";
                                    continue;
                                }
                                $upd = $pdo->prepare(
                                    "UPDATE products SET
                                        name = ?, category = ?, description = ?, price = ?,
                                        old_price = ?, emoji = ?, sort_order = ?, active = ?
                                     WHERE id = ?"
                                );
                                $upd->execute([$name, $category, $description, $price, $oldPrice, $emoji, $sortOrder, $active, $idRaw]);
                                $updated++;
                            } else {
                                $ins = $pdo->prepare(
                                    "INSERT INTO products (name, category, description, price, old_price, emoji, sort_order, active)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
                                );
                                $ins->execute([$name, $category, $description, $price, $oldPrice, $emoji, $sortOrder]);
                                $created++;
                            }
                        }

                        fclose($handle);
                        $report = ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Importar productos - SweetCut Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin-style.css">
</head>
<body>

  <div class="admin-topbar">
    <div class="admin-logo">Sweet<span>Cut</span> <small>Admin</small></div>
    <div class="admin-user">
      <span>👋 <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
      <a href="logout.php" class="btn-logout">Salir</a>
    </div>
  </div>

  <main class="admin-main admin-main-narrow">

    <div class="admin-header">
      <div>
        <h1>Importar productos desde CSV</h1>
        <p>Sube un archivo .csv separado por ";". El más fácil: exporta primero y edita sobre esa plantilla.</p>
      </div>
      <a href="index.php" class="btn-secondary">← Volver al panel</a>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($report): ?>
      <div class="alert alert-success">
        <strong>Importación terminada:</strong> <?= $report['created'] ?> creados, <?= $report['updated'] ?> actualizados,
        <?= count($report['skipped']) ?> omitidos.
      </div>
      <?php if (count($report['skipped']) > 0): ?>
        <div class="report-box">
          <h3>Filas omitidas (<?= count($report['skipped']) ?>)</h3>
          <ul class="report-list">
            <?php foreach (array_slice($report['skipped'], 0, 10) as $msg): ?>
              <li><?= htmlspecialchars($msg) ?></li>
            <?php endforeach; ?>
            <?php if (count($report['skipped']) > 10): ?>
              <li class="muted">Y <?= count($report['skipped']) - 10 ?> aviso(s) más.</li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>
      <div class="form-actions">
        <a href="index.php" class="btn-primary">Ir al panel</a>
      </div>
    <?php endif; ?>

    <div class="table-card">
      <form method="POST" action="import.php" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="form-group">
          <label for="csv">Archivo CSV *</label>
          <input type="file" id="csv" name="csv" accept=".csv,text/csv,text/plain">
          <small class="hint">Máximo 2 MB y 1500 filas. Separador ";". Las columnas image / image_thumb se ignoran: las imágenes se suben desde el formulario de cada producto.</small>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary">Importar CSV</button>
          <a href="export.php" class="btn-secondary">Descargar plantilla (exporta todo)</a>
        </div>
      </form>
    </div>

  </main>

</body>
</html>