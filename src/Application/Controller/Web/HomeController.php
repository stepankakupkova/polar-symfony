<?php

namespace App\Application\Controller\Web;

use App\Banner\Repository\BannerRepository;
use App\News\Repository\NewsRepository;
use App\News\Repository\PlaykitRepository;
use App\News\Repository\VideoRepository;
use App\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\Response;

final class HomeController
{
	public function index(
		PhtmlRenderer $renderer,
		PlaykitRepository $playkitRepository,
		NewsRepository $newsRepository,
		VideoRepository $videoRepository,
		BannerRepository $bannerRepository,
	): Response {
		$special = $playkitRepository->getSpecial();

		// Hlavní články HP (section 1 = zprávy, 2 = PR, 3 = Triptip)
		$newArticles = $playkitRepository->getAllHomepage();

		$newsCount  = $newsRepository->getCountFromSettings();
		$pr         = $newsRepository->getPrArticles(19);
		$regions    = $newsRepository->getAllArticlesForRegions();
		$newVideos  = $videoRepository->getNewVideosForWeb(6);

		return new Response($renderer->renderWithLayout('application/web/home', [
			'special'      => $special,
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
}
