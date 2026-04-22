<?php

namespace App\Program\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\News\Repository\NewsRepository;
use App\Program\Repository\ProgramRepository;
use App\Program\Repository\ShowRepository;
use App\Program\Repository\VideoRepository;
use DateTime;
use Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ShowController
{
	public function shows(
		Request $request,
		PhtmlRenderer $renderer,
		ShowRepository $showRepository,
	): Response
	{
		$params = $request->query->all();
		$query = isset($params['q']) ? $params['q'] : '';

		try {
			if ($query !== '' && mb_strlen($query, 'UTF-8') >= 3) {     // zakázat hledání krátkých slov
				// Ošetření dotazu
				$query = preg_replace('/\s+/', ' ', $query);
				$query = trim($query);
				$query = stripslashes($query);
				$query = filter_var($query, FILTER_SANITIZE_SPECIAL_CHARS);
			}
			$shows = $showRepository->fetchAllByCategories($query);
			$showsForAutocomplete = $showRepository->fetchForAutocomplete();
		} catch (Exception) {
			return new RedirectResponse('/');
		}

		return new Response($renderer->renderWithLayout('program/web/shows', [
			'shows' => $shows,
			'query' => $query,
			'showsForAutocomplete' => $showsForAutocomplete,
		]));
	}

	public function show(
		Request $request,
		PhtmlRenderer $renderer,
		ShowRepository $showRepository,
		VideoRepository $videoRepository,
		NewsRepository $newsRepository,
		UrlGeneratorInterface $urlGenerator,
	): Response
	{
		// Route je statická (např. /porady/regionalni-zpravy), nemá {url} parametr.
		// $url odvozujeme z 2. segmentu path: /porady/{url}
		$segments = explode('/', trim($request->getPathInfo(), '/'));
		$url = $segments[1] ?? '';
		if (!$url) {
			return new RedirectResponse($urlGenerator->generate('program_web_shows'));
		}

		try {
			$show = $showRepository->findPostBy('url', $url);
			if (!$show || !(bool)$show['status']) {
				return new RedirectResponse($urlGenerator->generate('program_web_shows'));
			}

			$times = $showRepository->fetchTimesForWeb((int)$show['id']);

			$page = (int) $request->query->get('strana', 1);
			$limit = 10;

			$videos = $videoRepository->getPaginatorByShow((int)$show['id'], $page, $limit);
			$videosTotal = $videoRepository->getCountByShow((int)$show['id']);
		} catch (Exception) {
			return new RedirectResponse($urlGenerator->generate('program_web_shows'));
		}

		// PR články
		$pr = $newsRepository->getPrArticles(2);

		return new Response($renderer->renderWithLayout('program/web/show', [
			'show' => $show,
			'times' => $times,
			'videos' => $videos,
			'videosTotal' => $videosTotal,
			'page' => $page,
			'limit' => $limit,
			'pr' => $pr,
		]));
	}

	public function video(
		string $program_url,
		Request $request,
		PhtmlRenderer $renderer,
		ShowRepository $showRepository,
		ProgramRepository $programRepository,
		VideoRepository $videoRepository,
		NewsRepository $newsRepository,
		UrlGeneratorInterface $urlGenerator,
		string $LIGHT_URL,
	): Response
	{
		// Route je statická (např. /porady/regionalni-zpravy/{program_url}), nemá {url} parametr.
		// $url odvozujeme z 2. segmentu path: /porady/{url}/{program_url}
		$segments = explode('/', trim($request->getPathInfo(), '/'));
		$url = $segments[1] ?? '';
		if (!$url) {
			return new RedirectResponse($urlGenerator->generate('program_web_shows'));
		}

		$program = null;
		$video = null;
		$newVideos = null;
		$mostWatchedShows = null;

		try {
			$show = $showRepository->findPostBy('url', $url);

			$program = $programRepository->findPostBy('url', $program_url);

			if ($program && $program['video_id']) {
				$video = $videoRepository->findPostBy('id', $program['video_id']);
			} else {
				return new RedirectResponse($urlGenerator->generate('program_show_' . $show['id']));
			}

			$newVideos = $videoRepository->getNewVideosForWeb(3);
			$mostWatchedShows = $videoRepository->getMostWatchedShowsForWeb(5);
		} catch (Exception) {
			return new RedirectResponse($urlGenerator->generate('program_web_shows'));
		}

		// PR články
		$pr = $newsRepository->getPrArticles(2);

		return new Response($renderer->renderWithLayout('program/web/video', [
			'show' => $show,
			'program' => $program,
			'video' => $video,
			'newVideos' => $newVideos,
			//'todayPremieres' => $todayPremieres,
			'mostWatchedShows' => $mostWatchedShows,
			'pr' => $pr,
			'LIGHT_URL' => $LIGHT_URL,
		]));
	}

	public function download(
		int $video_id,
		string $quality,
		VideoRepository $videoRepository,
		string $LIGHT_URL,
	): Response
	{
		$video = $videoRepository->findPostBy('id', $video_id);

		$filePath = $LIGHT_URL . 'porady/publikovano/' . $video['path'] . '/' . $video['name'] . '_' . $quality . '.mp4';

		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename="' . $video['name'] . '_' . $quality . '.mp4' . '"');
		header('Content-Type: application/force-download');
		readfile($filePath);

		return new Response('', 200);
	}

	public function overwriteDocx(
		int $video_id,
		ProgramRepository $programRepository,
		ShowRepository $showRepository,
		UrlGeneratorInterface $urlGenerator,
		string $PUBLIC_PATH,
	): Response
	{
		try {
			$program = $programRepository->findPremiereByVideoId($video_id);
			$show = $showRepository->findPostByProgram((int)$program['id']);

			$html = (string) $program['overwrite'];
			$html = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html);

			// 1) &nbsp; rozbíjí XML uvnitř DOCX
			$html = str_replace('&nbsp;', ' ', $html);

			// 2) PhpWord (loadXML) potřebuje XML-friendly <br/>
			$html = preg_replace('~</br\s*>~i', '<br/>', $html);
			$html = preg_replace('~<br\s*>~i', '<br/>', $html);

			// 3) odstranit prázdné odstavce typu <p><br></p>
			$html = preg_replace('~<p\b[^>]*>\s*(<br\s*/?>|&nbsp;|\s)*\s*</p>~i', '', $html);

			// 4) převod <div class="synchron"...> na <p>
			$dom = new \DOMDocument('1.0', 'UTF-8');

			libxml_use_internal_errors(true);
			$dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			libxml_clear_errors();

			$xpath = new \DOMXPath($dom);

			foreach ($xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " synchron ")]') as $node) {
				$p = $dom->createElement('p');

				while ($node->firstChild) {
					$p->appendChild($node->firstChild);
				}

				$node->parentNode->replaceChild($p, $node);
			}

			$html = '';
			foreach ($dom->documentElement->childNodes as $child) {
				$html .= $dom->saveHTML($child);
			}
			// END 4)

			// 5) odstranit prázdné seznamy
			$html = preg_replace('~<ul\b[^>]*>\s*</ul>~i', '', $html);
			$html = preg_replace('~<ol\b[^>]*>\s*</ol>~i', '', $html);

			// 6) odstranit prázdné em odstavce
			$html = preg_replace('~<p\b[^>]*>\s*<em>\s*(<br\s*/?>|&nbsp;|\s|-)*\s*</em>\s*</p>~i', '', $html);

			// 7) odstranit <br>
			$html = preg_replace('~<br\s*/?>~i', ' ', $html);

			$phpWord = new PhpWord();
			$phpWord->getSettings()->setThemeFontLang(
				new \PhpOffice\PhpWord\Style\Language('cs-CZ')
			);
			$phpWord->setDefaultParagraphStyle([
				'spaceAfter' => 240,
				'lineHeight' => 1.4,
			]);
			$section = $phpWord->addSection();

			// datum
			$createdAt = new \DateTime($program['time']);

			// odkaz
			$articleUrl = $urlGenerator->generate(
				'program_show_video_' . $show['id'],
				['url' => $show['url'], 'program_url' => $program['url']],
				UrlGeneratorInterface::ABSOLUTE_URL
			);

			// hlavička (logo vlevo, datum + odkaz vpravo)
			$table = $section->addTable([
				'width' => 5000,
				'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT,
				'borderSize' => 0,
				'borderColor' => 'FFFFFF',
				'cellMarginTop' => 0,
				'cellMarginLeft' => 0,
				'cellMarginRight' => 200,
				'cellMarginBottom' => 0,
			]);

			$table->addRow();

			// logo
			$cellLeft = $table->addCell(6500, ['valign' => 'top']);
			$cellLeft->addImage($PUBLIC_PATH . '/img/web/logo_polar.png', [
				'height' => 40,
			]);

			// datum + odkaz
			$cellRight = $table->addCell(2500, ['valign' => 'top']);

			$cellRight->addText(
				'Datum vydání:',
				[
					'size' => 9,
					'bold' => true,
				],
				[
					'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END,
					'spaceAfter' => 0,
				]
			);

			$cellRight->addText(
				$createdAt->format('j.n.Y, H:i'),
				[
					'size' => 9,
					'bold' => true,
				],
				[
					'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END,
					'spaceAfter' => 80,
				]
			);

			$cellRight->addLink(
				$articleUrl,
				'Otevřít video na polar.cz',
				[
					'color' => '0563C1',
					'underline' => 'single',
					'size' => 10,
				],
				[
					'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END,
					'spaceAfter' => 0,
				]
			);

			// mezera
			$section->addTextBreak(1);

			// nadpis - název pořadu
			$section->addText(
				$show['title'],
				[
					'bold' => true,
					'size' => 18,
				],
				[
					'spaceAfter' => 160,
				]
			);

			// try/catch z toho důvodu, aby při složitém HTML nezůstala zobrazená chybová stránka 500
			try {
				Html::addHtml($section, $html, false, false);
			} catch (\Throwable $e) {

				// Případné uložení problematického HTML
				/*file_put_contents(
					PUBLIC_PATH . '/docx-debug-' . time() . '.txt',
					print_r([
						'error' => $e->getMessage(),
						'html' => $html,
						'json' => json_encode($html, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
					], true)
				);*/

				$section->addText('Přepis se nepodařilo převést do DOCX.');
			}

			$baseName = pathinfo($program['file'], PATHINFO_FILENAME);
			$filename = 'polar-prepis-' . $baseName . '.docx';

			$tmp = tempnam(sys_get_temp_dir(), 'docx_');
			$writer = IOFactory::createWriter($phpWord, 'Word2007');
			$writer->save($tmp);

			$response = new Response(file_get_contents($tmp));
			$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
			$response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
			$response->headers->set('Content-Length', (string) filesize($tmp));
			@unlink($tmp);

			return $response;
		} catch (Exception $e) {
			return new RedirectResponse('/');
		}
	}
}
