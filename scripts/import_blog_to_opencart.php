#!/usr/bin/env php
<?php
/**
 * Import blog posts from blog_import/blog_posts.json into OpenCart CMS.
 *
 * Run from OpenCart root:
 *   php scripts/import_blog_to_opencart.php
 *   php scripts/import_blog_to_opencart.php /path/to/blog_import
 *
 * Prerequisites:
 * 1. Run: python3 scripts/extract_telegram_to_blog.py [--no-translate]
 * 2. Upload blog_import/ (blog_posts.json + images/) to OpenCart root
 * 3. Run this script from OpenCart root (where config.php is)
 */

$script_file = __FILE__;
$opencart_root = dirname(dirname($script_file));
if (!is_file($opencart_root . '/config.php')) {
    $opencart_root = getcwd();
}
if (!is_file($opencart_root . '/config.php')) {
    die("Error: Run from OpenCart root. Config not found.\n");
}

chdir($opencart_root);
define('DIR_OPENCART', rtrim($opencart_root, '/') . '/');

if (is_file(DIR_OPENCART . 'adminpage/config.php')) {
    require_once DIR_OPENCART . 'adminpage/config.php';
} elseif (is_file(DIR_OPENCART . 'config.php')) {
    require_once DIR_OPENCART . 'config.php';
} else {
    die("Error: config.php not found.\n");
}
if (!defined('DB_PREFIX') || !defined('DIR_IMAGE')) {
    die("Error: config must define DB_* and DIR_IMAGE.\n");
}

$import_dir = isset($argv[1]) && is_dir($argv[1]) ? rtrim($argv[1], '/') : (DIR_OPENCART . 'blog_import');
$json_file = $import_dir . '/blog_posts.json';
$images_src = $import_dir . '/images';
$images_dest = DIR_IMAGE . 'catalog/blog';

if (!is_file($json_file)) {
    die("Error: $json_file not found. Run: python3 scripts/extract_telegram_to_blog.py\n");
}

$data = json_decode(file_get_contents($json_file), true);
if (!$data || empty($data['posts'])) {
    die("Error: No posts in JSON.\n");
}

if (!is_dir($images_dest)) {
    mkdir($images_dest, 0755, true);
}

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT ?: 3306);
$db->set_charset('utf8mb4');
$prefix = DB_PREFIX;

$q = $db->query("SELECT language_id FROM `{$prefix}language` WHERE code IN ('uk-ua','uk','uk_UA') LIMIT 1");
$language_id = 1;
if ($q && $row = $q->fetch_assoc()) {
    $language_id = (int)$row['language_id'];
}
echo "Using language_id: $language_id\n";

$posts = $data['posts'];
$imported = 0;

foreach ($posts as $idx => $post) {
    $num = $idx + 1;
    $desc = $post['article_description']['1'] ?? reset($post['article_description']);
    $image_rel = $desc['image'] ?? '';

    if ($image_rel && is_dir($images_src)) {
        $img_name = basename($image_rel);
        $src = $images_src . '/' . $img_name;
        $dest = $images_dest . '/' . $img_name;
        if (is_file($src) && !is_file($dest)) {
            copy($src, $dest);
        }
        $image_db = is_file($dest) ? 'catalog/blog/' . $img_name : '';
    } else {
        $image_db = '';
    }

    $date_added = $db->real_escape_string($post['date_added'] ?? date('Y-m-d H:i:s'));
    $author = $db->real_escape_string($post['author'] ?? 'Fun-Dacha');
    $status = (int)($post['status'] ?? 1);
    $topic_id = (int)($post['topic_id'] ?? 0);

    $name = $db->real_escape_string($desc['name'] ?? 'Blog post ' . $num);
    $description = $db->real_escape_string($desc['description'] ?? '');
    $tag = $db->real_escape_string($desc['tag'] ?? '');
    $meta_title = $db->real_escape_string($desc['meta_title'] ?? substr($name, 0, 255));
    $meta_desc = $db->real_escape_string($desc['meta_description'] ?? '');
    $meta_kw = $db->real_escape_string($desc['meta_keyword'] ?? '');
    $image_db = $db->real_escape_string($image_db);

    $keyword = 'blog-post-' . $num;
    $store_id = 0;

    $db->query("INSERT INTO `{$prefix}article` SET `topic_id`=$topic_id, `author`='$author', `rating`=0, `status`=$status, `date_added`='$date_added', `date_modified`='$date_added'");
    $article_id = $db->insert_id;
    if (!$article_id) {
        echo "Error post $num\n";
        continue;
    }

    $db->query("INSERT INTO `{$prefix}article_description` SET `article_id`=$article_id, `language_id`=$language_id, `name`='$name', `description`='$description', `image`='$image_db', `tag`='$tag', `meta_title`='$meta_title', `meta_description`='$meta_desc', `meta_keyword`='$meta_kw'");
    $db->query("INSERT INTO `{$prefix}article_to_store` SET `article_id`=$article_id, `store_id`=$store_id");
    $keyword_esc = $db->real_escape_string($keyword);
    $db->query("INSERT INTO `{$prefix}seo_url` SET `store_id`=$store_id, `language_id`=$language_id, `key`='article_id', `value`='$article_id', `keyword`='$keyword_esc'");

    $imported++;
    if ($imported % 50 === 0) {
        echo "  $imported / " . count($posts) . "\n";
    }
}

echo "\nDone! Imported $imported articles.\n";
