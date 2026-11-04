<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\News\Repository\PlaykitRepository;
use Doctrine\DBAL\DriverManager;

if (PHP_SAPI === 'cli-server') {
	$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	if ($path === '/media-lq.mp4' || $path === '/media-hq.mp4') {
		$response = new Symfony\Component\HttpFoundation\BinaryFileResponse(sys_get_temp_dir() . '/polar-seek-test.mp4', 200, ['Content-Type' => 'video/mp4']);
		$response->prepare(Symfony\Component\HttpFoundation\Request::createFromGlobals())->send();
		return;
	}
	if ($path !== '/') {
		return false;
	}
	$armed = false;
	$script = '';
	foreach (token_get_all(file_get_contents(dirname(__DIR__, 2) . '/templates/news/web/news/article.phtml')) as $token) {
		if (is_array($token) && $token[0] === T_COMMENT && trim($token[1]) === '// Player VideoJS') {
			$armed = true;
		} elseif ($armed && is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
			$script = str_replace(["\\\\", "\\'"], ["\\", "'"], substr($token[1], 1, -1));
			break;
		}
	}
	if ($script === '') {
		throw new RuntimeException('Article player script was not found.');
	}
	$baseUrl = Symfony\Component\HttpFoundation\Request::createFromGlobals()->getSchemeAndHttpHost();
	header('Content-Type: text/html; charset=utf-8');
	?>
	<!doctype html>
	<html lang="cs">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="/vendor/web/video-js/video-js.min.css">
		<link rel="stylesheet" href="/vendor/web/video-js/plugin/silvermine-videojs-quality-selector/quality-selector.css">
		<style>body { margin: 16px; } main { max-width: 715px; } video, .video-js { width: 100%; aspect-ratio: 16 / 9; }</style>
	</head>
	<body><main>
	<?php foreach (['seek' => ['2.25', '5.75'], 'legacy' => null, 'zero' => ['0', '3']] as $id => $bounds) { ?>
		<h2><?= $id ?></h2>
		<video id="<?= $id ?>" class="player-article video-js vjs-fluid vjs-16-9"<?php if ($bounds !== null) { ?> data-seek-from="<?= $bounds[0] ?>" data-seek-to="<?= $bounds[1] ?>"<?php } ?> data-setup='{"controls": true, "autoplay": false, "preload": "auto"}'>
			<source src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/media-lq.mp4" type="video/mp4" label="LQ" selected="true">
			<source src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/media-hq.mp4" type="video/mp4" label="HQ">
		</video>
	<?php } ?>
	</main>
	<script src="/vendor/web/jquery/js/jquery.min.js"></script>
	<script src="/vendor/web/video-js/video.min.js"></script>
	<script src="/vendor/web/video-js/plugin/silvermine-videojs-quality-selector/silvermine-videojs-quality-selector.min.js"></script>
	<script><?= $script ?></script>
	</body></html>
	<?php
	return;
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
foreach ([
	'CREATE TABLE polar_news_articles (id INTEGER PRIMARY KEY, title TEXT, redactor_id INTEGER, public_from TEXT, updated_date TEXT, vi_preview_folder TEXT)',
	'CREATE TABLE polar_news_redaction (id INTEGER, name TEXT, surname TEXT, url TEXT)',
	'CREATE TABLE polar_news_articles2cities (article_id INTEGER, city_id INTEGER, rank INTEGER)',
	'CREATE TABLE polar_news_cities (id INTEGER, city TEXT, url TEXT, latitude REAL, longitude REAL, region_id INTEGER)',
	'CREATE TABLE polar_news_regions (id INTEGER, region TEXT, url TEXT)',
	'CREATE TABLE polar_news_videos_parts (id INTEGER, sec_from REAL, medium TEXT)',
	'CREATE TABLE polar_news_tags (id INTEGER, tag TEXT, url TEXT)',
	'CREATE TABLE polar_news_articles2tags (article_id INTEGER, tag_id INTEGER)',
	'CREATE TABLE polar_news_articles2gallery_files (article_id INTEGER, file_id INTEGER, part INTEGER, checked INTEGER, rank INTEGER, seek_from REAL, seek_to REAL)',
	'CREATE TABLE gallery_files (id INTEGER, folder TEXT, type TEXT, module TEXT, file TEXT, folder_light TEXT, size INTEGER, size_hq INTEGER, ext TEXT, duration INTEGER, description TEXT, updated_date TEXT)',
] as $sql) {
	$connection->executeStatement($sql);
}
$connection->insert('gallery_files', [
	'id' => 10, 'folder' => 'original', 'type' => 'video', 'module' => 'polar/news', 'file' => 'whole-show',
	'folder_light' => '2026/11/03', 'size' => 1, 'size_hq' => 2, 'ext' => 'mp4', 'duration' => 10,
	'description' => '', 'updated_date' => '2000-01-01 00:00:00',
]);
$repository = new PlaykitRepository($connection, 1);
foreach ([
	[1, 2.25, 5.75, '20261103120000_123', true],
	[2, null, null, null, false],
	[3, 0, 0, null, false],
	[4, 0, 5, null, true],
	[5, 5, 2, null, false],
	[6, -1, 5, null, false],
	[7, 9.5, 10.5, null, true],
	[8, null, 5, null, false],
	[9, 'bad', 5, null, false],
	[10, 2, '1e309', null, false],
] as [$id, $from, $to, $preview, $hasSeek]) {
	$connection->insert('polar_news_articles', ['id' => $id, 'title' => 'Test', 'public_from' => '2026-11-03', 'updated_date' => '2000-01-01 00:00:00', 'vi_preview_folder' => $preview]);
	$connection->insert('polar_news_articles2gallery_files', ['article_id' => $id, 'file_id' => 10, 'checked' => 1, 'rank' => 1, 'seek_from' => $from, 'seek_to' => $to]);
	$file = $repository->getArticle($id)['files'][0];
	if ($hasSeek) {
		if (!isset($file['seek_from'], $file['seek_to']) || (float) $file['seek_from'] !== (float) $from || (float) $file['seek_to'] !== (float) $to) {
			throw new RuntimeException('Missing or changed seek boundaries for article ' . $id);
		}
	} elseif (isset($file['seek_from']) || isset($file['seek_to'])) {
		throw new RuntimeException('Invalid seek enabled for article ' . $id);
	}
	if ($file['folder'] !== ($preview ?? 'original')) {
		throw new RuntimeException('Article preview changed incorrectly.');
	}
	if ($file['link_lq'] !== 'https://light.polar.cz/videa/polar/zpravy/publikovano/2026/11/03/whole-show_lq.mp4') {
		throw new RuntimeException('Video source URL changed.');
	}
}
$connection->update('polar_news_articles', ['public_from' => '2014-01-01'], ['id' => 2]);
$file = $repository->getArticle(2)['files'][0];
if ($file['link_lq'] !== $file['link_hq'] || !str_ends_with($file['link_lq'], '_hq.mp4')) {
	throw new RuntimeException('Pre-2015 video source changed.');
}
$connection->close();
echo "PASS: article video seek, legacy sources and individual previews (11 cases).\n";