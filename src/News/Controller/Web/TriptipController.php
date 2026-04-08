<?php

namespace App\News\Controller\Web;

use App\News\Repository\NewsRepository;
use App\News\Repository\PlaykitRepository;
use App\Application\View\PhtmlRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TriptipController
{
	public function __construct(
		private NewsRepository $newsRepository,
		private PlaykitRepository $playkitRepository,
		private UrlGeneratorInterface $urlGenerator,
	) {}

	public function index(Request $request, PhtmlRenderer $renderer): Response
	{
		$articles = $this->playkitRepository->getTriptips(30);      // Dříve pojmenováno v CoverageTable "getRandArticleBySection"
		$exhibitions = $this->playkitRepository->getTriptips(20, 0, null, 1);         // Dříve pojmenováno v CoverageTable "getRandArticleBySection"

		return new Response($renderer->renderWithLayout('news/web/triptip/index', [
			'articles' => $articles,
			'exhibitions' => $exhibitions,
			'currentUrl' => $request->getUri(),
		]));
	}

	public function region(Request $request, PhtmlRenderer $renderer, string $url): Response
	{
		if (!$url) {
			return new RedirectResponse($this->urlGenerator->generate('triptip'));
		}

		$region = $this->playkitRepository->getRegionByUrl($url);
		if (!$region) {
			return new RedirectResponse($this->urlGenerator->generate('triptip'));
		}

		if ($region['url'] === 'moravskoslezsky-kraj') {
			$articles = $this->playkitRepository->getTriptips(30);      // Dříve pojmenováno v CoverageTable "getRandArticleBySection"
		} else {
			$articles = $this->playkitRepository->getRandTriptipByRegion(3, $region['id']);       // Dříve "$this->getCoverageTable()->getRandArticleBySectionAndRegion(2, 3, $region['id'])"
		}
		$exhibitions = $this->playkitRepository->getRandTriptipByRegion(20, $region['id'], 0, null, 1);       // Dříve "$this->getCoverageTable()->getRandArticleBySectionAndRegion(2, 20, $region['id'], 0, null, 1)"

		return new Response($renderer->renderWithLayout('news/web/triptip/region', [
			'region' => $region,
			'articles' => $articles,
			'exhibitions' => $exhibitions,
			'currentUrl' => $request->getUri(),
		]));
	}

	public function city(Request $request, PhtmlRenderer $renderer, string $url, string $city_url): Response
	{
		if (!$url || !$city_url) {
			return new RedirectResponse($this->urlGenerator->generate('triptip'));
		}

		$region = $this->playkitRepository->getRegionByUrl($url);
		$city = $this->playkitRepository->getCityByUrl($city_url);
		if (!$city || !$region) {
			return new RedirectResponse($this->urlGenerator->generate('triptip'));
		}

		$articles = $this->playkitRepository->getRandTriptipByCity(3, $city['id']);       // Dříve "$this->getCoverageTable()->getRandArticleBySectionAndCity(2, 3, $city['id'])"
		$exhibitions = $this->playkitRepository->getRandTriptipByCity(20, $city['id'], 0, null, 1);       // Dříve "$this->getCoverageTable()->getRandArticleBySectionAndCity(2, 20, $city['id'], 0, null, 1)"

		return new Response($renderer->renderWithLayout('news/web/triptip/city', [
			'region' => $region,
			'city' => $city,
			'articles' => $articles,
			'exhibitions' => $exhibitions,
			'currentUrl' => $request->getUri(),
		]));
	}

	public function detail(Request $request, PhtmlRenderer $renderer, string $url, string $city_url, int $article_id): Response
	{
		if (!$url || !$city_url || !$article_id) {
			return new RedirectResponse($this->urlGenerator->generate('triptip'));
		}

		$region = $this->playkitRepository->getRegionByUrl($url);
		$city = $this->playkitRepository->getCityByUrl($city_url);
		$article = $this->playkitRepository->getTriptip($article_id);   // Dříve "$this->getCoverageTable()->getArticleSection();"

		if (!$region || !$city || !$article) {
			return new RedirectResponse($this->urlGenerator->generate('triptip'));
		}

		// Nepovolit zobrazeni detailu pres URL, pokud je udalost mimo interval, nebo vice, nez 30 dni stara
		$today = new \DateTime();
		$today_minus_30_days = new \DateTime();
		$today = $today->format('Y-m-d H:i:s');
		$today_minus_30_days = $today_minus_30_days->modify('- 30 days')->format('Y-m-d H:i:s');
		if ($article['public_from'] >= $today) {
			return new RedirectResponse($this->urlGenerator->generate('triptip'));
		}
		if ($article['public_to'] <= $today) {
			return new RedirectResponse($this->urlGenerator->generate('triptip'));
		}
		if (isset($article['term_to']) && $article['term_to'] <= $today) {
			return new RedirectResponse($this->urlGenerator->generate('triptip'));
		}
		if ($article['term_from'] <= $today && !isset($article['term_to']) && $article['created_date'] < $today_minus_30_days) {
			return new RedirectResponse($this->urlGenerator->generate('triptip'));
		}

		// Pocitani zobrazeni clanku
		$this->newsRepository->setImpressionsCountTriptips($article_id);

		// Zakázání zobrazení PR článku při prvním příchodu ze stránek seznam.cz
		$seznam = $request->query->get('utm_source');
		if ($seznam !== 'www.seznam.cz') {
			// PR články
			$pr = $this->newsRepository->getPrArticles(2);
		} else {
			$pr = null;
		}

		// Počasí
		$weather = $this->playkitRepository->getWeatherForNews('Ostrava');

		return new Response($renderer->renderWithLayout('news/web/triptip/article', [
			'region' => $region,
			'city' => $city,
			'article' => $article,
			'pr' => $pr,
			'weather' => $weather,
			'weather_region' => 'Ostrava',
			'currentUrl' => $request->getUri(),
		]));
	}

	public function getTriptipList(Request $request): JsonResponse
	{
		$date = $request->request->get('date');
		$region_id = $request->request->get('region_id');
		$city_id = $request->request->get('city_id');

		if ($date) {
			$date = new \DateTime($date);
		} else {
			$date = new \DateTime();
		}

		$calendar = $content = '';

		$date->modify('-1 day');
		$prevDay = new \DateTime($date->format('Y-m-d'));
		$date->modify('+1 day');

		$date->modify('+1 day');
		$nextDay = new \DateTime($date->format('Y-m-d'));
		$date->modify('-1 day');

		$today = new \DateTime();
		$dateCal = new \DateTime($date->format('Y-m-d'));

		if ($dateCal->format('Y-m-d') > $today->format('Y-m-d')) {
			$today->modify('+12 days');
			if ($today->format('Y-m-d') > $dateCal->format('Y-m-d')) {
				$today->modify('-12 days');
				$dateCal = new \DateTime($today->format('Y-m-d'));
			} else {
				while ($today->format('Y-m-d') <= $dateCal->format('Y-m-d')) {
					$today->modify('+12 days');
				}
				$today->modify('-12 days');
				$dateCal = new \DateTime($today->format('Y-m-d'));
			}
		} else {
			while ($today->format('Y-m-d') > $dateCal->format('Y-m-d')) {
				$today->modify('-12 days');
			}
			$dateCal = new \DateTime($today->format('Y-m-d'));
		}

		$formatter = new \IntlDateFormatter(\Locale::getDefault(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, null, null, 'dd.MM.');
		$formatterMonth = new \IntlDateFormatter(\Locale::getDefault(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, null, null, 'EEE');
		$dateCal->modify('-1 day');

		$now = new \DateTime();

		$calendar =
			'<ul class="nav nav-pills">';

		if ($date->format('Y-m-d') <= $now->format('Y-m-d')) {
			$calendar .=
				'<li class="disabled"><a class="disabled" href="#' . $prevDay->format('Y-m-d') . '" title=""><i class="fas fa-chevron-left"></i></a></li>';
		} else {
			$calendar .=
				'<li><a href="#' . $prevDay->format('Y-m-d') . '" title=""><i class="fas fa-chevron-left"></i></a></li>';
		}
		$dateCal->modify('+1 day');
		for ($i = 0; $i < 12; $i++) {
			$calendar .=
				'<li class="' . (($dateCal->format('Y-m-d') == $date->format('Y-m-d')) ? 'active' : '') . (($dateCal->format('Y-m-d') == $now->format('Y-m-d')) ? ' today' : '') . (($dateCal->format('N') > 5) ? ' weekend' : '') . '">' .
				'<a href="#' . $dateCal->format('Y-m-d') . '" title="">' .
				$formatter->format($dateCal) .
				'<span>' . (($dateCal->format('Y-m-d') == $now->format('Y-m-d')) ? 'dnes' : ($formatterMonth->format($dateCal))) . '</span>' .
				'</a>' .
				'</li>';
			$dateCal->modify('+1 day');
		}

		$dateCal->modify('-10 day');
		$calendar .=
			'<li><a href="#' . $nextDay->format('Y-m-d') . '" title=""><i class="fas fa-chevron-right"></i></a></li>' .
			'</ul>';

		if ($region_id === '7') {
			$articles = $this->playkitRepository->getTriptips(30, 0, $date->format('Y-m-d'));
		} else if ($region_id) {
			$articles = $this->playkitRepository->getRandTriptipByRegion(30, (int) $region_id, 0, $date->format('Y-m-d'));
		} else if ($city_id) {
			$articles = $this->playkitRepository->getRandTriptipByCity(30, (int) $city_id, 0, $date->format('Y-m-d'));
		} else {
			$articles = $this->playkitRepository->getTriptips(30, 0, $date->format('Y-m-d'));
		}

		$dateFormatter = new \IntlDateFormatter(\Locale::getDefault(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, null, null, 'dd.MM.');
		$timeFormatter = new \IntlDateFormatter(\Locale::getDefault(), \IntlDateFormatter::NONE, \IntlDateFormatter::SHORT);

		$content =
			'<section class="section-kamvyrazit">' .
			'<div class="row">';
		if ($articles) {
			$i = 1;
			foreach ($articles as $article) {
				if ($article['image']) {
					$content .=
						'<div class="col-sm-4 col-xs-12 mb-3">';
					$content .=
						'<div class="row">' .
						'<div class="col-5 col-sm-12 pe-2 pe-md-3">' .
						'<div class="img-thumbnail mb-2">' .
						'<a class="mb-1" href="' . $article['url'] . '" title="' . $article['title'] . '">' .
						'<img src="' . $article['image'] . '" class="img-fluid img-rounded" alt="" />' .
						'</a>' .
						'</div>' .
						'</div>' .
						'<div class="col-7 col-sm-12 mb-3 ps-1 ps-sm-3 pe-3">' .
						'<h4 class="mb-2">' .
						'<a class="text-secondary font-weight-regular text-3" href="' . $article['url'] . '" title="' . $article['title'] . '">' .
						$article['title'] .
						'</a>' .
						'</h4>' .
						'<div class="">' .
						'<span>' .
						'<i class="fa fa-fw fa-calendar text-color-primary"></i> ';
					$dateFrom = new \DateTime($article['term_from']);
					$todayAjax = new \DateTime();
					if ($todayAjax->format('Y-m-d') == $dateFrom->format('Y-m-d')) {
						$content .=
							'Dnes';
					} else {
						$todayAjax->modify('+1 day');
						if ($todayAjax->format('Y-m-d') == $dateFrom->format('Y-m-d')) {
							$content .=
								'Zítra';
						} else {
							$content .=
								$dateFormatter->format($dateFrom);
						}
					}
					$content .=
						'</span>' .
						' <span>' .
						$timeFormatter->format(new \DateTime($article['term_from']));
					if ($article['term_to']) {
						$dateTo = new \DateTime($article['term_to']);
						if ($dateFrom->format('Y-m-d') == $dateTo->format('Y-m-d')) {
							$content .=
								' - ' . $timeFormatter->format(new \DateTime($article['term_to']));
						} else {
							$content .=
								' - ' . $dateFormatter->format($dateTo) . ' ' .
								$timeFormatter->format(new \DateTime($article['term_to']));
						}
					}
					$content .=
						'</span>' .
						'<div class="mt-1">' .
						'<span>' .
						'<i class="fa fa-fw fa-map-pin text-primary"></i> ' .
						$article['city'] .
						', ' . $article['place'] .
						'</span>' .
						'</div>' .
						'</div>' .
						'</div>' .
						'</div>' .
						'</div>';

					if (($i % 3) == 0) {
						$content .=
							'</div>' .
							'<div class="row' . (($i >= 15) ? ' hide' : '') . '">';
					}
					$i++;
				}
			}
		}

		$content .=
			'</div>' .
			'<button id="nextEvents" class="btn btn-secondary">Zobrazit dalších 15 pozvánek</button>' .
			'</section>';

		return new JsonResponse([
			'calendar' => $calendar,
			'content' => $content,
			'success' => true,
		]);
	}
}
