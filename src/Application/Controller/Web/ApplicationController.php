<?php

namespace App\Application\Controller\Web;

use App\Banner\Repository\BannerRepository;
use App\News\Repository\NewsRepository;
use App\News\Repository\PlaykitRepository;
use App\News\Repository\VideoRepository;
use App\Program\Repository\ProgramRepository;
use App\Application\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ApplicationController
{
	public function index(
		PhtmlRenderer $renderer,
		PlaykitRepository $playkitRepository,
		NewsRepository $newsRepository,
		VideoRepository $videoRepository,
		BannerRepository $bannerRepository,
	): Response {
		
		// Celkový počet článků
		$newsCount = $newsRepository->getCountFromSettings();

		// Hlavní články
		$newArticles = $playkitRepository->getAllHomepage();
		if ($newArticles) {
			foreach ($newArticles as $i => $iValue) {
				if ($iValue['section'] === 1 || $iValue['section'] === 2) {
					$newArticles[$i]['url'] = '/zpravy/' . $iValue['region_url'] . '/' . $iValue['city_url'] . '/' . $iValue['article_id'] . '/' . $this->removeAccent($iValue['title'], '-');
				}
				if ($iValue['section'] === 3) {
					$newArticles[$i]['url'] = '/kam-vyrazit/' . $iValue['region_url'] . '/' . $iValue['city_url'] . '/' . $iValue['article_id'] . '/' . $this->removeAccent($iValue['title'], '-');
				}
			}
		}

		// PR články
		$pr = $newsRepository->getPrArticles(19);

		// Nejnovější pořady
		$newVideos = $videoRepository->getNewVideosForWeb(6);

		// Články pro regiony
		$articles_regions = $newsRepository->getAllArticles();
		$regions = [];

		if ($articles_regions) {
			foreach ($articles_regions as $article) {
				if (!isset($regions[$article['region_order']])) {
					$regions[$article['region_order']] = [
						'id' => $article['region_id'],
						'title' => $article['region_title'],
						'url' => $article['region_url'],
						'articles' => [],
					];
				}
				$regions[$article['region_order']]['articles'][] = $article;
			}
			unset($articles_regions);
		}

		// Speciál na HP
		$specialArticlesIds = null;
		$specialOnlineArticleId = $specialOnlineArticleUrl = null;
		$special = $playkitRepository->getSpecial();
		if ($special['active'] === '1') {
			if ($special['articles_ids'] !== null) {
				$ids = $special['articles_ids'];
				$specialArticlesIds = $newsRepository->getArticlesForSpecialByIDs($ids, 5);
			}
			if ($special['online_article_id'] !== null) {
				$specialOnlineArticleId = $playkitRepository->getOnlineNewsForSpecialByArticleId((int)$special['online_article_id'], 5);
				$specialOnlineArticleUrl = $newsRepository->getArticle((int)$special['online_article_id']);
				if (isset($specialOnlineArticleUrl[0])) {
					$specialOnlineArticleUrl = $specialOnlineArticleUrl[0];
				}
			}
		}
		//var_dump($specialOnlineArticleUrl);

		$response = new Response($renderer->renderWithLayout('application/web/index', [
			'newsCount' => $newsCount,
			'newArticles' => $newArticles,
			'pr' => $pr,
			'regions' => $regions,
			'newVideos' => $newVideos,
			'special' => $special,
			'specialArticlesIds' => $specialArticlesIds,
			'specialOnlineArticleId' => $specialOnlineArticleId,
			'specialOnlineArticleUrl' => $specialOnlineArticleUrl,
			'bannerRectangle' => $bannerRepository->getRectangle(),
			'bannerSquare' => $bannerRepository->getSquare(),
			'bannerMobilesquare1' => $bannerRepository->getMobilesquare1(),
			'bannerMobilesquare2' => $bannerRepository->getMobilesquare2(),
		]));
		$response->setPublic();
		$response->setMaxAge(120);
		return $response;
	}

	public function search(
		Request $request,
		PhtmlRenderer $renderer,
		NewsRepository $newsRepository,
		ProgramRepository $programRepository,
		PlaykitRepository $playkitRepository,
		BannerRepository $bannerRepository,
	): Response {
		$params = $request->query->all();
		if (!$params) {
			return new RedirectResponse('/');
		}

		$query = $params['q2'] ?? $params['q'] ?? '';
		$page = isset($params['p']) ? (int)$params['p'] : 1;
		$page_porady = isset($params['p_porady']) ? (int)$params['p_porady'] : 1;
		$region = isset($params['r']) ? (int)$params['r'] : null;
		$city = isset($params['c']) ? (int)$params['c'] : null;
		$limit = 10;
		$paginator = null;
		$program_paginator = null;
		$count = 0;
		$program_count = 0;
		$countReprice = 0;

		if ($query !== '' && mb_strlen($query, 'UTF-8') >= 3) {     // zakázat hledání krátkých slov
			// Ošetření dotazu
			$query = preg_replace('/\s+/', ' ', $query);
			$query = trim($query);
			$query = stripslashes($query);
			$query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');

			$paginator = $newsRepository->searchArticles($query, $region, $city, $page, $limit);
			$count = $newsRepository->searchCount($query, $region, $city);

			// Vyhledávání v pořadech
			$program_paginator = $programRepository->searchPaginator($query, $page_porady, $limit);
			$program_count = $programRepository->searchPaginatorCount($query);

			// Vyhledávání v reprízách
			$countReprice = $programRepository->searchPaginator2($query, 1, 1);
		}

		$regions = $playkitRepository->getAlRegionsForSearch($query, $region, $city);

		return new Response($renderer->renderWithLayout('application/web/search', [
			'query' => $query,
			'count' => $count,
			'page' => $page,
			'page_porady' => $page_porady,
			'limit' => $limit,
			'regions' => $regions,
			'paginator' => $paginator,
			'region_id' => $region,
			'city_id' => $city,
			'program_count' => $program_count,
			'program_paginator' => $program_paginator,
			'countReprice' => $countReprice,
			'robots' => 'noindex, follow',
			'bannerRectangle'    => $bannerRepository->getRectangle(),
			'bannerSquare'       => $bannerRepository->getSquare(),
			'bannerMobilesquare1' => $bannerRepository->getMobilesquare1(),
			'bannerMobilesquare2' => $bannerRepository->getMobilesquare2(),
		]));
	}

	public function hd(
		Request $request,
		PhtmlRenderer $renderer,
		BannerRepository $bannerRepository,
	): Response {
		$actual_link = $request->getSchemeAndHttpHost() . $request->getRequestUri();

		return new Response($renderer->renderWithLayout('application/web/hd', [
			'og' => [
				'title' => 'HD vysílání | Televize POLAR',
				'description' => 'Nepřetržitý proud informací, zpráv a zábavných pořadů z vašeho okolí.',
				'url' => $actual_link,
			],
		]));
	}

	public function polar2(
		Request $request,
		PhtmlRenderer $renderer,
		BannerRepository $bannerRepository,
	): Response {
		$actual_link = $request->getSchemeAndHttpHost() . $request->getRequestUri();

		return new Response($renderer->renderWithLayout('application/web/polar2', [
			'og' => [
				'title' => 'Polar 2 | Televize POLAR',
				'description' => 'POLAR - moravskoslezská regionální televize - nepřetržitý proud informací, zpráv a zábavných pořadů z vašeho okolí.',
				'url' => $actual_link,
			],
		]));
	}

	public function privacyPolicy(
		PhtmlRenderer $renderer,
	): Response {
		return new Response($renderer->renderWithLayout('application/web/privacy-policy', []));
	}

	public function accessDenied(
		PhtmlRenderer $renderer,
	): Response {
		return new Response($renderer->renderWithLayout('application/web/access-denied', []));
	}

	public function rss(
		PhtmlRenderer $renderer,
		PlaykitRepository $playkitRepository,
	): Response {
		return new Response($renderer->renderWithLayout('application/web/rss', [
			'regions' => $playkitRepository->getAllRegionsForRss(),
			'cities' => $playkitRepository->getAllCitiesForRss(),
		]));
	}

	public function sitemap(
		PhtmlRenderer $renderer,
	): Response {
		return new Response($renderer->renderWithLayout('application/web/sitemap', []));
	}

	public function sitemapXml(): Response
	{
		$file = __DIR__ . '/../../../../public/sitemap.xml';
		if (!file_exists($file)) {
			throw new NotFoundHttpException('sitemap.xml not found');
		}

		$xml = simplexml_load_string(file_get_contents($file));
		return new Response($xml->asXML(), 200, ['Content-Type' => 'text/xml']);
	}

	public function sitemapDefaultXml(
		PhtmlRenderer $renderer,
	): Response {
		$content = $renderer->render('application/web/sitemap-default-xml', []);
		return new Response($content, 200, ['Content-Type' => 'text/xml']);
	}

	public function robotsTxt(
		PhtmlRenderer $renderer,
	): Response {
		$content = $renderer->render('application/web/robots-txt', []);
		return new Response($content, 200, ['Content-Type' => 'text/plain']);
	}

	public function embedPlayerjs(
		Request $request,
		PhtmlRenderer $renderer,
	): Response {
		$agent = $request->headers->get('User-Agent', '');

		if (strpos($agent, 'Trident') || strpos($agent, 'Edge')) {
			$autoplay = 'autoplay';
		} else {
			$autoplay = '';
		}

		if (strpos($agent, 'Safari') && !strpos($agent, 'Chrome')) {
			$dash = false;
		} else {
			$dash = true;
		}

		if (stripos($agent, 'mobile') !== false || stripos($agent, 'android') !== false) {
			$mobile = true;
		} else {
			$mobile = false;
		}

		return new Response($renderer->renderWithLayout('application/web/embed-playerjs', [
			'autoplay' => $autoplay,
			'dash' => $dash,
			'mobile' => $mobile,
		]));
	}

	private function removeAccent($text, $replace = null): string
	{
		$transliterator = \Transliterator::createFromRules(':: Any-Latin; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC; :: [:Punctuation:] Remove; :: Lower();', \Transliterator::FORWARD);
		if ($transliterator) {
			$text = $transliterator->transliterate($text);
		}
		$text = preg_replace('/\p{C}+/u', '', $text);
		if ($replace) {
			$text = str_replace(' ', $replace, $text);
		}
		return $text;
	}
}
