<?php

namespace App\News\Controller\Web;

use App\Banner\Repository\BannerRepository;
use App\Camera\Repository\CameraRepository;
use App\Job\Repository\JobRepository;
use App\News\Repository\CrawlRepository;
use App\News\Repository\NewsRepository;
use App\News\Repository\PlaykitRepository;
use App\News\Repository\TickerRepository;
use App\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class NewsController
{
	public function __construct(
		private NewsRepository $newsRepository,
		private JobRepository $jobRepository,
		private CameraRepository $cameraRepository,
		private PlaykitRepository $playkitRepository,
		private BannerRepository $bannerRepository,
		private TickerRepository $tickerRepository,
		private CrawlRepository $crawlRepository,
	) {}

	public function index(Request $request, PhtmlRenderer $renderer): Response
	{
		$page = max(1, (int) $request->query->get('page', 1));
		$limit = 25;

		$articles = $this->newsRepository->getPage($page, $limit);
		$newsCount = $this->newsRepository->getCountFromSettings();
		$total = $this->newsRepository->getTotal();
		$pr = $this->newsRepository->getPrArticles(11);
		$blockTriptip = $this->newsRepository->getTriptipArticles(4, null, true);
		$blockJob = $this->jobRepository->getRandForWeb(132, 4);
		$blockCamera = $this->cameraRepository->fetchAllLimit(4);
		$weather = $this->playkitRepository->getWeatherForNews('Ostrava');
		$weatherRegion = 'Ostrava';

		$bannerLeaderboard = $this->bannerRepository->getLeaderboard();
		$bannerMobilesticky = $this->bannerRepository->getMobilesticky();
		$bannerRectangle = $this->bannerRepository->getRectangle();
		$bannerSquare = $this->bannerRepository->getSquare();
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();
		$bannerMobilesquare2 = $this->bannerRepository->getMobilesquare2();

		return new Response($renderer->renderWithLayout('news/web/index', [
			'articles'          => $articles,
			'newsCount'         => $newsCount,
			'page'              => $page,
			'total'             => $total,
			'limit'             => $limit,
			'pr'                => $pr,
			'blockTriptip'      => $blockTriptip,
			'blockJob'          => $blockJob,
			'blockCamera'       => $blockCamera,
			'weather'           => $weather,
			'weatherRegion'     => $weatherRegion,
			'currentUrl'        => $request->getUri(),
			'schemeHost'        => $request->getSchemeAndHttpHost(),
			'region'            => null,
			'bannerLeaderboard' => $bannerLeaderboard,
			'bannerMobilesticky'=> $bannerMobilesticky,
			'bannerRectangle'    => $bannerRectangle,
			'bannerSquare'       => $bannerSquare,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			'bannerMobilesquare2' => $bannerMobilesquare2,
		]));
	}

	public function getTicker(): JsonResponse
	{
		$content = null;
		try {
			$items = $this->tickerRepository->getItems();
			if ($items) {
				$content = '<ul>';
				foreach ($items as $item) {
					$content .= '<div><li><span>' . $item . '</span></li></div>';
				}
				$content .= '</ul>';
			}
			return new JsonResponse(['content' => $content, 'success' => true, 'message' => null]);
		} catch (\Exception $e) {
			return new JsonResponse(['content' => null, 'success' => false, 'message' => $e->getMessage()]);
		}
	}

	public function getCrawl(): JsonResponse
	{
		try {
			$crawl = $this->crawlRepository->getCrawl(1);
			$items = $this->crawlRepository->getItems(1);

			$start = $crawl['auto_delete_start'] ?? null;
			$stop  = $crawl['auto_delete_stop'] ?? null;
			$separator = str_replace(' ', '&nbsp;', $crawl['separator'] ?? '');

			$content = '<div>';
			if ($crawl['text_before']) {
				$content .= $crawl['text_before'] . ' ';
				if ($separator) { $content .= $separator . ' '; }
			}
			foreach ($items as $i => $val) {
				if ($val !== '') {
					$content .= str_replace(' ', '&nbsp;', $val);
					if ($separator && ($i + 1) < count($items)) {
						$content .= ' ' . $separator . ' ';
					}
				}
			}
			if ($crawl['text_after']) {
				$content .= ' ';
				if ($separator) { $content .= $separator . ' '; }
				$content .= $crawl['text_after'];
			}
			$content .= '</div>';

			return new JsonResponse(['content' => $content, 'success' => true, 'message' => null, 'start' => $start, 'stop' => $stop]);
		} catch (\Exception $e) {
			return new JsonResponse(['content' => null, 'success' => false, 'message' => $e->getMessage(), 'start' => null, 'stop' => null]);
		}
	}
}
