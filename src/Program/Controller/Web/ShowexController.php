<?php

namespace App\Program\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\News\Repository\NewsRepository;
use App\Program\Repository\ShowexRepository;
use App\Program\Repository\VideoexRepository;
use App\Program\Repository\VideoRepository;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ShowexController
{
	public function showex(
		string $url,
		Request $request,
		PhtmlRenderer $renderer,
		ShowexRepository $showexRepository,
		VideoexRepository $videoexRepository,
		NewsRepository $newsRepository,
		UrlGeneratorInterface $urlGenerator,
	): Response
	{
		if (!$url) {
			return new RedirectResponse($urlGenerator->generate('program_web_shows'));
		}

		try {
			$show = $showexRepository->findPostBy('url', $url);
			if (!$show) {
				return new RedirectResponse($urlGenerator->generate('program_web_shows'));
			}

			$page = (int) $request->query->get('strana', 1);
			$limit = 10;

			$videos = $videoexRepository->getPaginatorByShow((int)$show['id'], $page, $limit);
			$videosTotal = $videoexRepository->getCountByShow((int)$show['id']);
		} catch (Exception) {
			return new RedirectResponse($urlGenerator->generate('program_web_shows'));
		}

		// PR články
		$pr = $newsRepository->getPrArticles(2);

		return new Response($renderer->renderWithLayout('program/web/showex', [
			'show' => $show,
			'videos' => $videos,
			'videosTotal' => $videosTotal,
			'page' => $page,
			'limit' => $limit,
			'pr' => $pr,
		]));
	}

	public function videoex(
		string $url,
		string $video_url,
		PhtmlRenderer $renderer,
		ShowexRepository $showexRepository,
		VideoexRepository $videoexRepository,
		VideoRepository $videoRepository,
		NewsRepository $newsRepository,
		UrlGeneratorInterface $urlGenerator,
		string $LIGHT_URL,
	): Response
	{
		if (!$url) {
			return new RedirectResponse($urlGenerator->generate('program_web_shows'));
		}

		$video = null;
		$parts = null;
		$newVideos = null;
		$mostWatchedShows = null;

		try {
			$show = $showexRepository->findPostBy('url', $url);

			$video = $videoexRepository->findPostBy('url', $video_url);

			if ($video) {
				$parts = $videoexRepository->findPartsBy('video_id', (int)$video['id']);
			} else {
				return new RedirectResponse($urlGenerator->generate('program_showex_' . $show['id'], ['url' => $show['url']]));
			}

			$newVideos = $videoRepository->getNewVideosForWeb(3);
			//$todayPremieres = $this->showRepository->getTodayPremieresForWeb();
			$mostWatchedShows = $videoRepository->getMostWatchedShowsForWeb(5);
		} catch (Exception) {
			return new RedirectResponse($urlGenerator->generate('program_web_shows'));
		}

		// PR články
		$pr = $newsRepository->getPrArticles(2);

		return new Response($renderer->renderWithLayout('program/web/videoex', [
			'show' => $show,
			'video' => $video,
			'parts' => $parts,
			'newVideos' => $newVideos,
			//'todayPremieres' => $todayPremieres,
			'mostWatchedShows' => $mostWatchedShows,
			'pr' => $pr,
			'LIGHT_URL' => $LIGHT_URL,
		]));
	}

	public function downloadex(
		int $video_id,
		string $quality,
		VideoexRepository $videoexRepository,
		string $LIGHT_URL,
	): Response
	{
		$video = $videoexRepository->findPostBy('id', $video_id);

		$filePath = $LIGHT_URL . 'mimoradne/publikovano/' . $video['path'] . '/' . $video['name'] . '_' . $quality . '.mp4';

		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename="' . $video['name'] . '_' . $quality . '.mp4' . '"');
		header('Content-Type: application/force-download');
		readfile($filePath);

		return new Response('', 200);
	}
}
