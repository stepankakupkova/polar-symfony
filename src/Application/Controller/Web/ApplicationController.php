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

final class ApplicationController
{
	public function index(
		PhtmlRenderer $renderer,
		PlaykitRepository $playkitRepository,
		NewsRepository $newsRepository,
		VideoRepository $videoRepository,
		BannerRepository $bannerRepository,
	): Response {
		$special = $playkitRepository->getSpecial();

		// Speciál na HP
		$specialArticlesIds = null;
		$specialOnlineArticleId = $specialOnlineArticleUrl = null;
		if ($special['active'] === '1') {
			if ($special['articles_ids'] !== null) {
				$ids = $special['articles_ids'];
				$specialArticlesIds = $newsRepository->getArticlesForSpecialByIDs($ids, 5);
			}
			if ($special['online_article_id'] !== null) {
				$specialOnlineArticleId = $playkitRepository->getOnlineNewsForSpecialByArticleId((int)$special['online_article_id'], 5);
				$specialOnlineArticleUrl = $newsRepository->getArticle((int)$special['online_article_id']);
			}
		}

		// Hlavní články HP (section 1 = zprávy, 2 = PR, 3 = Triptip)
		$newArticles = $playkitRepository->getAllHomepage();

		$newsCount  = $newsRepository->getCountFromSettings();
		$pr         = $newsRepository->getPrArticles(19);
		$regions    = $newsRepository->getAllArticlesForRegions();
		$newVideos  = $videoRepository->getNewVideosForWeb(6);

		return new Response($renderer->renderWithLayout('application/web/index', [
			'special'      => $special,
			'specialArticlesIds' => $specialArticlesIds,
			'specialOnlineArticleId' => $specialOnlineArticleId,
			'specialOnlineArticleUrl' => $specialOnlineArticleUrl,
			'newArticles'  => $newArticles,
			'newsCount'    => $newsCount,
			'pr'           => $pr,
			'regions'      => $regions,
			'newVideos'    => $newVideos,
			'bannerRectangle'    => $bannerRepository->getRectangle(),
			'bannerSquare'       => $bannerRepository->getSquare(),
			'bannerMobilesquare1' => $bannerRepository->getMobilesquare1(),
			'bannerMobilesquare2' => $bannerRepository->getMobilesquare2(),
		]));
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
		$paginator = [];
		$program_paginator = [];
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
}
