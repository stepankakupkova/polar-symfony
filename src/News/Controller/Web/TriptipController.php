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

	public function article(Request $request, PhtmlRenderer $renderer, int $article_id): Response
	{
		if (!$article_id) {
			return new RedirectResponse($this->urlGenerator->generate('news_triptip'));
		}

		$article = $this->playkitRepository->getTriptip($article_id);   // Dříve "$this->getCoverageTable()->getArticleSection();"

		if (!$article) {
			return new RedirectResponse($this->urlGenerator->generate('news_triptip'));
		}

		// Nepovolit zobrazeni detailu pres URL, pokud je udalost mimo interval, nebo vice, nez 30 dni stara
		$today = new \DateTime();
		$today_minus_30_days = new \DateTime();
		$today = $today->format('Y-m-d H:i:s');
		$today_minus_30_days = $today_minus_30_days->modify('- 30 days')->format('Y-m-d H:i:s');
		if ($article['public_from'] >= $today) {
			return new RedirectResponse($this->urlGenerator->generate('news_triptip'));
		}
		if ($article['public_to'] <= $today) {
			return new RedirectResponse($this->urlGenerator->generate('news_triptip'));
		}
		if (isset($article['term_to']) && $article['term_to'] <= $today) {
			return new RedirectResponse($this->urlGenerator->generate('news_triptip'));
		}
		if ($article['term_from'] <= $today && !isset($article['term_to']) && $article['created_date'] < $today_minus_30_days) {
			return new RedirectResponse($this->urlGenerator->generate('news_triptip'));
		}

		// Souvísející články
		if ($article['text']) {
			$article['text'] = $this->insertRelativeArticle($article['text']);
		}

		// Souvísející PR články
		if ($article['text']) {
			$article['text'] = $this->insertRelativePrArticle($article['text']);
		}

		// Souvísející KAM VYRAZIT články
		if ($article['text']) {
			$article['text'] = $this->insertRelativeTriptipArticle($article['text']);
		}

		// Twitter feed
		if ($article['text']) {
			$article['text'] = $this->insertTwitter($article['text']);
		}

		// Facebook feed
		if ($article['text']) {
			$article['text'] = $this->insertFacebook($article['text']);
		}

		// Youtube video
		if ($article['text']) {
			$article['text'] = $this->insertYoutube($article['text'], $request->getSchemeAndHttpHost());
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

		$articles = $this->playkitRepository->getTriptips(30, 0, $date->format('Y-m-d'));

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

	/**
	 * @param string $text
	 * @return string
	 */
	private function insertRelativeArticle(string $text): string
	{
		RELATIVEARTICLE:
		if (mb_strpos($text, '{{souvisejici-clanek', 0, 'UTF-8')) {
			$posStart = mb_strpos($text, '{{souvisejici-clanek',0, 'UTF-8');
			$posEnd = mb_strpos($text, '}}', $posStart, 'UTF-8');
			$id = mb_substr($text, $posStart, $posEnd - $posStart + 2);
			$relative_article_id = mb_substr($id, 22, -3);
			if (is_numeric($relative_article_id)) {
				$relatedArticle = $this->playkitRepository->getArticle($relative_article_id);
				if ($relatedArticle) {

					$html  = '<div class="header">Sledujte také</div>';
					$html .= '<div class="relative-article">';
					$html .= '<h3>';
					$html .= '<a href="' . $this->urlGenerator->generate('news_region_city_article', ['url' => $relatedArticle['region_url'], 'city_url' => $relatedArticle['city_url'], 'article_id' => $relatedArticle['id'], 'article_url' => $relatedArticle['url']]) . '" title="">' . $relatedArticle['title'] . '</a>';
					$html .= '</h3>';
					$html .= '</div>';

					$text = str_replace($id, $html, $text);
				} else {
					$text = str_replace($id, '', $text);
				}
			} else {
				$text = str_replace($id, '', $text);
			}
			GOTO RELATIVEARTICLE;
		}
		return $text;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function insertRelativePrArticle(string $text): string
	{
		RELATIVEARTICLE:
		if (mb_strpos($text, '{{souvisejici-pr-clanek', 0, 'UTF-8')) {
			$posStart = mb_strpos($text, '{{souvisejici-pr-clanek',0, 'UTF-8');
			$posEnd = mb_strpos($text, '}}', $posStart, 'UTF-8');
			$id = mb_substr($text, $posStart, $posEnd - $posStart + 2);
			$relative_article_id = mb_substr($id, 25, -3);
			if (is_numeric($relative_article_id)) {
				$relatedArticle = $this->playkitRepository->getArticlePr($relative_article_id);
				if ($relatedArticle) {

					$html  = '<div class="header">Sledujte také</div>';
					$html .= '<div class="relative-pr-article">';
					$html .= '<h3>';
					$html .= '<a href="' . $this->urlGenerator->generate('news_pr_article', ['article_id' => $relatedArticle['id'], 'article_url' => $relatedArticle['url']]) . '" title="">' . $relatedArticle['title'] . '</a>';
					$html .= '</h3>';
					$html .= '</div>';

					$text = str_replace($id, $html, $text);
				} else {
					$text = str_replace($id, '', $text);
				}
			} else {
				$text = str_replace($id, '', $text);
			}
			GOTO RELATIVEARTICLE;
		}
		return $text;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function insertRelativeTriptipArticle(string $text): string
	{
		RELATIVEARTICLE:
		if (mb_strpos($text, '{{souvisejici-kam-vyrazit-clanek', 0, 'UTF-8')) {
			$posStart = mb_strpos($text, '{{souvisejici-kam-vyrazit-clanek',0, 'UTF-8');
			$posEnd = mb_strpos($text, '}}', $posStart, 'UTF-8');
			$id = mb_substr($text, $posStart, $posEnd - $posStart + 2);
			$relative_article_id = mb_substr($id, 34, -3);
			if (is_numeric($relative_article_id)) {
				$relatedArticle = $this->playkitRepository->getTriptip($relative_article_id); // Dříve "$this->getCoverageTable()->getArticleSection($relative_article_id, 2);"
				if ($relatedArticle) {

					$html  = '<div class="header">Sledujte také</div>';
					$html .= '<div class="relative-triptip-article">';
					$html .= '<h3>';
					$html .= '<a href="' . $this->urlGenerator->generate('news_triptip_article', ['url' => $relatedArticle['region_url'], 'city_url' => $relatedArticle['city_url'], 'article_id' => $relatedArticle['id'], 'article_url' => $relatedArticle['url']]) . '" title="">' . $relatedArticle['title'] . '</a>';
					$html .= '</h3>';
					$html .= '</div>';

					$text = str_replace($id, $html, $text);
				} else {
					$text = str_replace($id, '', $text);
				}
			} else {
				$text = str_replace($id, '', $text);
			}
			GOTO RELATIVEARTICLE;
		}
		return $text;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function insertTwitter(string $text): string
	{
		$i = 1;
		TWITTERFEED:
		if (mb_strpos($text, '{{twitter-feed-', 0, 'UTF-8')) {
			$posStart = mb_strpos($text, '{{twitter-feed-',0, 'UTF-8');
			$posEnd = mb_strpos($text, '}}', $posStart, 'UTF-8');
			$id = mb_substr($text, $posStart, $posEnd - $posStart + 2);
			$twitter_id = mb_substr($id, 16, -3);
			if (is_numeric($twitter_id)) {
				$html = '<div class="twitter-feed" id="twitter-feed-' . $i . '">';
				$html .= '</div>';
				$html .= '<script>
								twttr.ready(function (twttr) {
									twttr.widgets.createTweet(
										"' . $twitter_id . '",
										document.getElementById("twitter-feed-' . $i . '"),
										{
											theme: "light",
											align: "center",
											lang: "cs"
										}
									);
								});
							</script>';

				$text = str_replace($id, $html, $text);
			} else {
				$text = str_replace($id, '', $text);
			}
			$i++;
			GOTO TWITTERFEED;
		}
		return $text;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function insertFacebook(string $text): string
	{
		$i = 1;
		FACEBOOKFEED:
		if (mb_strpos($text, '{{facebook-feed-', 0, 'UTF-8')) {
			$posStart = mb_strpos($text, '{{facebook-feed-',0, 'UTF-8');
			$posEnd = mb_strpos($text, '}}', $posStart, 'UTF-8');
			$code = mb_substr($text, $posStart, $posEnd - $posStart + 2);
			$both_ids = mb_substr($code, 16, -2);
			$both_ids = str_replace('"', '', $both_ids);
			if (mb_strpos($both_ids, '-', 0, 'UTF-8')) {
				$two_ids = explode("-", $both_ids);
				if (isset($two_ids[0]) && isset($two_ids[1])) {
					$id_page = $two_ids[0];
					$id_post = $two_ids[1];
				}
			}
			if (isset($id_page) && isset($id_post)) {
				$html = '<div class="fb-post" data-href="https://www.facebook.com/'.$id_page.'/posts/'.$id_post.'/" id="facebook-feed-' . $i . '"></div>';
				$text = str_replace($code, $html, $text);
			} else {
				$text = str_replace($code, '', $text);
			}
			$i++;
			GOTO FACEBOOKFEED;
		}
		return $text;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function insertYoutube(string $text, string $schemeHost): string
	{
		$i = 1;
		YOUTUBEVIDEO:
		if (mb_strpos($text, '{{youtube-video-', 0, 'UTF-8')) {
			$posStart = mb_strpos($text, '{{youtube-video-',0, 'UTF-8');
			$posEnd = mb_strpos($text, '}}', $posStart, 'UTF-8');
			$id = mb_substr($text, $posStart, $posEnd - $posStart + 2);
			$youtube_id = mb_substr($id, 17, -3);
			if ($youtube_id) {
				$html = '<div class="responsive_player"><iframe id="youtube-player-'.$i.'" type="text/html" width="640" height="360" src="https://www.youtube.com/embed/'.$youtube_id.'?enablejsapi=1&origin='.$schemeHost.'" frameborder="0" allowfullscreen></iframe></div>';
				$text = str_replace($id, $html, $text);
			} else {
				$text = str_replace($id, '', $text);
			}
			$i++;
			GOTO YOUTUBEVIDEO;
		}
		return $text;
	}
}
