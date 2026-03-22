<?php
/**
 * Export our categories to CSV for map_category_images.py
 * Run: php shared/data/export_our_categories.php
 *
 * Output: shared/data/our_categories.csv
 */

$config_path = __DIR__ . '/../../adminpage/config.php';
if (!is_file($config_path)) {
    fwrite(STDERR, "Config not found: $config_path\n");
    exit(1);
}
require $config_path;

$mysqli = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT ?: 3306);
if ($mysqli->connect_error) {
    fwrite(STDERR, "DB connect error: " . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');
$prefix = DB_PREFIX;

$sql = "SELECT c.category_id, cd.name, c.image
        FROM {$prefix}category c
        JOIN {$prefix}category_description cd ON c.category_id = cd.category_id
        WHERE cd.language_id = 1
        ORDER BY c.category_id";

$res = $mysqli->query($sql);
if (!$res) {
    fwrite(STDERR, "Query error: " . $mysqli->error . "\n");
    exit(1);
}

$out = __DIR__ . '/our_categories.csv';
$fp = fopen($out, 'w');
fputcsv($fp, ['category_id', 'name', 'image']);
while ($row = $res->fetch_assoc()) {
    fputcsv($fp, [$row['category_id'], $row['name'], $row['image'] ?? '']);
}
fclose($fp);
$mysqli->close();

echo "Exported to $out\n";
