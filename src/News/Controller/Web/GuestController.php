<?php

namespace App\News\Controller\Web;

use App\News\Repository\NewsRepository;
use App\News\Repository\PlaykitRepository;
use App\News\Repository\ShowRepository;
use App\News\Repository\VideoRepository;
use App\Application\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GuestController
{
	public function __construct(
		private PlaykitRepository $playkitRepository,
		private ShowRepository $showRepository,
		private VideoRepository $videoRepository,
		private NewsRepository $newsRepository,
		private UrlGeneratorInterface $urlGenerator,
	) {}

	public function guests(Request $request, PhtmlRenderer $renderer): Response
	{
		$page = max(1, $request->query->getInt('strana', 1));
		$limit = 30;

		$guests = $this->playkitRepository->getGuests();

		$shows = $this->showRepository->fetchAllForGuests();
		$shows_arr = [];
		if ($shows) {
			foreach ($shows as $show) {
				$shows_arr[$show['id']] = $show['title'];
			}
		}

		// Paginator
		$total = $guests ? count($guests) : 0;
		$offset = ($page - 1) * $limit;
		$guestsPage = $guests ? array_slice($guests, $offset, $limit) : null;

		return new Response($renderer->renderWithLayout('news/web/guest/guests', [
			'guests' => $guestsPage,
			'shows' => $shows_arr,
			'page' => $page,
			'total' => $total,
			'limit' => $limit,
			'currentUrl' => $request->getUri(),
		]));
	}

	public function guest(Request $request, PhtmlRenderer $renderer, int $guest_id): Response
	{
		if (!$guest_id) {
			return new RedirectResponse($this->urlGenerator->generate('news_guests'));
		}

		$guest = $this->playkitRepository->getGuest($guest_id);
		if (!$guest || !$guest['file_id']) {
			return new RedirectResponse($this->urlGenerator->generate('news_guests'));
		}

		$show = null;
		if ($guest['show_id'] && $guest['show_id'] != 999 && $guest['show_id'] != 1000 && $guest['show_id'] != 1001) {
			$show = $this->showRepository->findPostBy('id', $guest['show_id']);
		}

		$newVideos = $this->videoRepository->getNewVideosForWeb(3);
		//$todayPremieres = null;

		// nejsledovanejsi hoste
		$ids = $this->newsRepository->getMostWatchedGuestsForWeb(3);
		$mostWatchedGuests = $this->playkitRepository->getGuestsByIDs($ids ?? [], 3);

		// Počítání zobrazení hostů
		$this->newsRepository->setImpressionsGuestsCount($guest_id);

		// Zakázání zobrazení PR článku při prvním příchodu ze stránek seznam.cz
		$seznam = $request->query->get('utm_source');
		if ($seznam !== 'www.seznam.cz') {
			// PR články
			$pr = $this->newsRepository->getPrArticles(2);
		} else {
			$pr = null;
		}

		return new Response($renderer->renderWithLayout('news/web/guest/guest', [
			'guest' => $guest,
			'show' => $show,
			'newVideos' => $newVideos,
			//'todayPremieres' => $todayPremieres,
			'mostWatchedGuests' => $mostWatchedGuests,
			'pr' => $pr,
			'currentUrl' => $request->getUri(),
		]));
	}
}
