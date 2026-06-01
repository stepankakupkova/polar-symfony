<?php

namespace App\News\Controller\Web;

use App\Banner\Repository\BannerRepository;
use App\Camera\Repository\CameraRepository;
use App\Job\Repository\JobRepository;
use App\News\Repository\CrawlRepository;
use App\News\Repository\NewsRepository;
use App\News\Repository\PlaykitRepository;
use App\News\Repository\TickerRepository;
use App\Application\View\PhtmlRenderer;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NewsController
{
	public function __construct(
		private NewsRepository $newsRepository,
		private PlaykitRepository $playkitRepository,
		private TickerRepository $tickerRepository,
		private CrawlRepository $crawlRepository,
		private JobRepository $jobRepository,
		private CameraRepository $cameraRepository,
		private BannerRepository $bannerRepository,
		private UrlGeneratorInterface $urlGenerator,
		private string $PUBLIC_PATH,
		private string $LIGHT_URL,
	) {}

	public function index(Request $request, PhtmlRenderer $renderer): Response
	{
		$articles = null;
		try {
			$page = (int) $request->query->get('strana', 1);
			$articles = $this->newsRepository->getPaginator($page, 25);
		} catch (\Exception $e) {
			//var_dump ($e->getMessage());
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		// Celkový počet článků
		$newsCount = $this->newsRepository->getCountFromSettings();

		// PR články
		$pr = $this->newsRepository->getPrArticles(11);

		// Blok Kam vyrazit
		$blockTriptip = $this->newsRepository->getTriptipArticles(4, false, true);

		// Blok Nabídky práce
		$blockJob = $this->jobRepository->getRandForWeb(132, 4);

		// Blok kamery
		$blockCamera = $this->cameraRepository->fetchAllLimit(4);

		// Počasí
		$weather = $this->playkitRepository->getWeatherForNews('Ostrava');

		// Banner rectangle
		$bannerRectangle = $this->bannerRepository->getRectangle();

		// Banner square
		$bannerSquare = $this->bannerRepository->getSquare();

		// Banner mobile square 1
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();

		// Banner mobile square 2
		$bannerMobilesquare2 = $this->bannerRepository->getMobilesquare2();

		$response = new Response($renderer->renderWithLayout('news/web/news/index', [
			'newsCount' => $newsCount,
			'pr' => $pr,
			'blockTriptip' => $blockTriptip,
			'blockJob' => $blockJob,
			'blockCamera' => $blockCamera,
			'articles' => $articles,
			'weather' => $weather,
			'weather_region' => 'Ostrava',
			'bannerRectangle' => $bannerRectangle,
			'bannerSquare' => $bannerSquare,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			'bannerMobilesquare2' => $bannerMobilesquare2,
			// Extra pro šablonu (HeadMeta, paginator)
			'region' => null,
			'page' => $page,
			'total' => $this->newsRepository->getCount(),
			'limit' => 25,
			'currentUrl' => $request->getUri(),
			'schemeHost' => $request->getSchemeAndHttpHost(),
		]));
		$response->setPublic();
		$response->setMaxAge(120);
		return $response;
	}

	public function region(Request $request, PhtmlRenderer $renderer): Response
	{
		$url = (string) $request->attributes->get('url', 0);
		if (!$url) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		try {
			$region = $this->playkitRepository->getRegionByUrl($url);
		} catch (\Exception $ex) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		if (!$region) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		$articles = null;
		try {
			$page = (int) $request->query->get('strana', 1);
			if ($region['url'] === 'moravskoslezsky-kraj') {
				$articles = $this->newsRepository->getPaginator($page, 25);
			} else {
				$articles = $this->newsRepository->getPaginatorByRegion((int) $region['id'], $page, 25);
			}
		} catch (\Exception $e) {
			//var_dump ($e->getMessage());
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		// Celkový počet článků
		$newsCount = $this->newsRepository->getCount($region['id']);

		// PR články
		$pr = $this->newsRepository->getPrArticles(11);

		// Blok Kam vyrazit
		$blockTriptip = $this->newsRepository->getTriptipArticles(4, $region['id'], true);

		// Blok Nabídky práce
		$blockJob = $this->jobRepository->getRandForWebByCityCode($this->getJobOkresCode($url), 4);

		// Blok kamery
		$blockCamera = $this->cameraRepository->fetchAllLimit(4);

		// Počasí
		$weather_region = $this->getRegionForWeather($region['url']);
		$weather = $this->playkitRepository->getWeatherForNews($weather_region);

		// Banner rectangle
		$bannerRectangle = $this->bannerRepository->getRectangle();

		// Banner square
		$bannerSquare = $this->bannerRepository->getSquare();

		// Banner mobile square 1
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();

		// Banner mobile square 2
		$bannerMobilesquare2 = $this->bannerRepository->getMobilesquare2();

		$response = new Response($renderer->renderWithLayout('news/web/news/region', [
			'newsCount' => $newsCount,
			'region' => $region,
			'pr' => $pr,
			'blockTriptip' => $blockTriptip,
			'blockJob' => $blockJob,
			'blockCamera' => $blockCamera,
			'articles' => $articles,
			'weather' => $weather,
			'weather_region' => $weather_region,
			'bannerRectangle' => $bannerRectangle,
			'bannerSquare' => $bannerSquare,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			'bannerMobilesquare2' => $bannerMobilesquare2,
			// Extra pro šablonu (HeadMeta, paginator)
			'page' => $page,
			'total' => $newsCount,
			'limit' => 25,
			'currentUrl' => $request->getUri(),
			'schemeHost' => $request->getSchemeAndHttpHost(),
		]));
		$response->setPublic();
		$response->setMaxAge(120);
		return $response;
	}

	public function city(Request $request, PhtmlRenderer $renderer): Response
	{
		$url = (string) $request->attributes->get('url', 0);
		$city_url = (string) $request->attributes->get('city_url', 0);
		if (!$url || !$city_url) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		try {
			$region = $this->playkitRepository->getRegionByUrl($url);
			$city = $this->playkitRepository->getCityByUrl($city_url);
		} catch (\Exception $ex) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		if (!$city || !$region) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		$articles = null;
		try {
			$page = (int) $request->query->get('strana', 1);
			$articles = $this->newsRepository->getPaginatorByCity((int) $city['id'], $page, 25);
		} catch (\Exception $e) {
			//var_dump ($e->getMessage());
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		// Celkový počet článků
		$newsCount = $this->newsRepository->getCount(null, $city['id']);

		// PR články
		$pr = $this->newsRepository->getPrArticles(11);

		// Blok Kam vyrazit
		$blockTriptip = $this->newsRepository->getTriptipArticles(4, $region['id'], true);

		// Blok Nabídky práce
		$blockJob = $this->jobRepository->getRandForWebByCityCode($this->getJobOkresCode($url), 4);

		// Blok kamery
		$blockCamera = $this->cameraRepository->fetchAllLimit(4);

		// Počasí
		$weather_region = $this->getRegionForWeather($region['url']);
		$weather = $this->playkitRepository->getWeatherForNews($weather_region);

		// Banner rectangle
		$bannerRectangle = $this->bannerRepository->getRectangle();

		// Banner square
		$bannerSquare = $this->bannerRepository->getSquare();

		// Banner mobile square 1
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();

		// Banner mobile square 2
		$bannerMobilesquare2 = $this->bannerRepository->getMobilesquare2();

		$response = new Response($renderer->renderWithLayout('news/web/news/city', [
			'newsCount' => $newsCount,
			'pr' => $pr,
			'blockTriptip' => $blockTriptip,
			'blockJob' => $blockJob,
			'blockCamera' => $blockCamera,
			'articles' => $articles,
			'region' => $region,
			'city' => $city,
			'weather' => $weather,
			'weather_region' => $weather_region,
			'bannerRectangle' => $bannerRectangle,
			'bannerSquare' => $bannerSquare,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			'bannerMobilesquare2' => $bannerMobilesquare2,
			// Extra pro šablonu (HeadMeta, paginator)
			'page' => $page,
			'total' => $newsCount,
			'limit' => 25,
			'currentUrl' => $request->getUri(),
			'schemeHost' => $request->getSchemeAndHttpHost(),
		]));
		$response->setPublic();
		$response->setMaxAge(120);
		return $response;
	}

	public function redactor(Request $request, PhtmlRenderer $renderer): Response
	{
		$redactor_url = (string) $request->attributes->get('redactor_url', null);
		if (!$redactor_url) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		try {
			$redactor = $this->playkitRepository->getRedactorByUrl($redactor_url);
		} catch (\Exception $ex) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		if (!$redactor) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		$articles = null;
		try {
			$page = (int) $request->query->get('strana', 1);
			$articles = $this->newsRepository->getPaginatorByRedactor($redactor['url'], $page, 10);
		} catch (\Exception $e) {
			//var_dump($e->getMessage());
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		// Celkový počet článků
		$newsCount = $this->newsRepository->getCount(null, null, $redactor['url']);

		// PR články
		$pr = $this->newsRepository->getPrArticles(9);

		// Banner rectangle
		$bannerRectangle = $this->bannerRepository->getRectangle();

		// Banner mobile square 1
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();

		return new Response($renderer->renderWithLayout('news/web/news/redactor', [
			'newsCount' => $newsCount,
			'redactor' => $redactor,
			'pr' => $pr,
			'articles' => $articles,
			'bannerRectangle' => $bannerRectangle,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			// Extra pro šablonu (paginator)
			'page' => $page,
			'total' => $newsCount,
			'limit' => 10,
			'currentUrl' => $request->getUri(),
			'schemeHost' => $request->getSchemeAndHttpHost(),
		]));
	}

	public function article(Request $request, PhtmlRenderer $renderer): Response
	{
		$url = $request->attributes->get('url', 0);
		$city_url = $request->attributes->get('city_url', 0);
		$article_id = (int) $request->attributes->get('article_id', 0);
		$printableText = '';

		if (!$url || !$city_url || !$article_id) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		try {
			$region = $this->playkitRepository->getRegionByUrl($url);
			$city = $this->playkitRepository->getCityByUrl($city_url);
			$article = $this->playkitRepository->getArticle($article_id, $city['id']);
			$city_rank_number_1 = $this->playkitRepository->getCityRank1ByArticleId($article_id);

			// V proměnné $article jsou data z playkitu. Kvůli autosave dochází ke zveřejnění článků, které jsou rozepsané. Vezmene základní údaje z tabulky "article" v db webu Polaru.
			$article_data = $this->newsRepository->getArticle($article_id);
			if ($article_data && $article_data['title'] && $article_data['anotation'] && $article_data['text']) {
				$article['title'] = $article_data['title'];
				$article['anotation'] = $article_data['anotation'];
				$article['text'] = $article_data['text'];
			}

			if ($article['public'] === 0) {
				return new RedirectResponse($this->urlGenerator->generate('news'));
			}

			if (!$region || !$city || !$article) {
				return new RedirectResponse($this->urlGenerator->generate('news'));
			}
			if (!$article['region'] || !$article['city']) {
				return new RedirectResponse($this->urlGenerator->generate('news'));
			}

			// Text článku k tisku, bez widgetů
			$printableText = $article['text'];

			// Počítání zobrazení článků
			$this->newsRepository->setImpressionsCount($article_id);

			// Souvísající články
			if ($article['text']) {
				$article['text'] = $this->insertRelativeArticle($article['text']);
			}

			// Souvísající PR články
			if ($article['text']) {
				$article['text'] = $this->insertRelativePrArticle($article['text']);
			}

			// Souvísající KAM VYRAZIT články
			if ($article['text']) {
				$article['text'] = $this->insertRelativeTriptipArticle($article['text']);
			}

			// Online reportáž
			if ($article['text']) {
				$article['text'] = $this->insertOnlineNews($article['text'], (int)$article['id']);
			}

			// Twitter feed
			if ($article['text']) {
				$article['text'] = $this->insertTwitter($article['text']);
			}

			// Facebook feed
			if ($article['text']) {
				$article['text'] = $this->insertFacebook($article['text']);
			}

			// Facebook LIVE feed
			if ($article['text']) {
				$article['text'] = $this->insertFacebookLive($article['text']);
			}

			// Youtube video
			if ($article['text']) {
				$article['text'] = $this->insertYoutube($article['text'], $request->getSchemeAndHttpHost());
			}
		} catch (\Exception $ex) {
			//var_dump($ex->getMessage().$ex->getFile().$ex->getLine());
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		// Zakázání zobrazení PR článku při prvním příchodu ze stránek seznam.cz
		$seznam = $request->query->get('utm_source');
		if ($seznam !== 'www.seznam.cz') {
			// PR články
			$pr = $this->newsRepository->getPrArticles(11);
		} else {
			$pr = null;
		}

		// Doporučujeme - hlavní články z HP
		$newArticles = $this->playkitRepository->getAllHomepage([$article_id]);   // Dříve název "$this->getHomepageTable()->getAll()"
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
		// Rozdělíme $newArticles na 2 poloviny. První podpole (prvních 5 položek). Druhé podpole (zbytek).
		$dividedArray = array_chunk($newArticles ?: [], 5);
		$newArticlesFirstHalf = isset($dividedArray[0]) ? $dividedArray[0] : null;
		$newArticlesSecondHalf = isset($dividedArray[1]) ? $dividedArray[1] : null;

		// Nejnovější zprávy pro MSK
		$regionArticles = $this->newsRepository->getArticlesByRegionId((int)$region['id'], 6, $article_id);
		if ($regionArticles) {
			foreach ($regionArticles as $id => $item) {
				$date = new \DateTime($item['public_from']);
				$today = new \DateTime();
				if ($today->format('Y-m-d') === $date->format('Y-m-d')) {
					$regionArticles[$id]['date'] = 'Dnes';
				} else if ($today->modify('-1 day')->format('Y-m-d') === $date->format('Y-m-d')) {
					$regionArticles[$id]['date'] = 'Včera';
				} else {
					$regionArticles[$id]['date'] =  $date->format('d.m.');
				}
				$regionArticles[$id]['time'] =  $date->format('H:i');
				$regionArticles[$id]['url'] = '/zpravy/' . $item['region_url'] . '/' . $item['city_url'] . '/' . $item['article_id'] . '/' . $this->removeAccent($item['title'], '-');
			}
		}

		// Blok Kam vyrazit
		$blockTriptip = $this->newsRepository->getTriptipArticles(4, $region['id'], true);

		// Blok Nabídky práce
		$blockJob = $this->jobRepository->getRandForWebByCityCode($this->getJobOkresCode($url), 4);

		// Blok kamery
		$blockCamera = $this->cameraRepository->fetchAllLimit(4);

		// Počasí
		$weather_region = $this->getRegionForWeather($region['url']);
		$weather = $this->playkitRepository->getWeatherForNews($weather_region);

		//
		// Články k tématu
		// Položky vybrat podle: 1) počtu stejných témat (tagů) se zobrazeným článkem, 2) pokud bude stejný počet témat, tak podle počtu shlédnutí (tab. numbers_of_impressions), 3) nezobrazíme článek, který je starší než xy měsíců
		//
		if ($article['topics']) {
			$topic_ids = $data = $final = [];
			// vezmeme IDčka tagů, ktré jsou u aktuálního článku
			foreach ($article['topics'] as $topic) {
				$topic_ids[] = $topic['tag_id'];
			}
			// prohledáme všechny články na tyto tagy. V poli vzniknou duplicity, protože některé články mají více, než 1 tag stejný
			$articles_ids[] = $this->playkitRepository->getArticlesByTopicsAndDate($topic_ids, $article['id']);
			// sečteme tyto duplicity - potřebujeme články, které mají nejvíce stejných tagů, jako aktuální článek
			$articles_ids_sort = array_count_values($articles_ids[0] ?? []);
			// seřadíme pole podle počtu stejných tagů DESC
			arsort($articles_ids_sort, SORT_NUMERIC);
			// ořežeme na prvních x položek (podle toho, kolik článků bude ve výpise "K tématu")
			$slice = array_slice($articles_ids_sort, 0, 3, true);
			// finální IDčka článků projedeme postupně a vyhledáme počet kliknutí (numbers_of_impressions) a vytvoříme pole $data se všemi potřebnými údajji
			$i = 0;
			foreach ($slice as $article_id => $count) {
				$data[$i]['article_id'] = $article_id;
				$data[$i]['count'] = $count;
				$data[$i]['impressions'] = $this->newsRepository->getCountForByArticleId($article_id);
				$i++;
			}
			// nakonec toto pole seřadíme podle počtu stejných tagů DESC, numbers_of_impressions DESC
			if ($data) {
				foreach ($data as $key => $row) {
					$count_tags[$key]  = $row['count'];
					$impressions[$key] = $row['impressions'];
				}
				array_multisort($count_tags, SORT_DESC, $impressions, SORT_DESC, $data);
				// finální pole už bude obsahovat jen IDčka článků seřazené podle všech kritérií
				foreach ($data as $val) {
					$final[] = $val['article_id'];
				}
				// pro všechny IDčka článků vyhledáme dané články ke zobrazení
				$newArticlesTopic = $this->newsRepository->getNewArticlesTopic($final);
				//Debug::dump($newArticlesTopic);
			} else {
				$newArticlesTopic = null;
			}

		} else {
			$newArticlesTopic = null;
		}
		// END Články k tématu

		// Banner rectangle
		$bannerRectangle = $this->bannerRepository->getRectangle();

		// Banner square
		$bannerSquare = $this->bannerRepository->getSquare();

		// Banner mobile square 1
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();

		// Banner mobile square 2
		$bannerMobilesquare2 = $this->bannerRepository->getMobilesquare2();

		$response = new Response($renderer->renderWithLayout('news/web/news/article', [
			'article' => $article,
			'printableText' => $printableText,
			'region' => $region,
			'city' => $city,
			'newArticlesFirstHalf' => $newArticlesFirstHalf,
			'newArticlesSecondHalf' => $newArticlesSecondHalf,
			'newArticlesTopic' => $newArticlesTopic,
			'regionArticles' => $regionArticles,
			'pr' => $pr,
			'blockTriptip' => $blockTriptip,
			'blockJob' => $blockJob,
			'blockCamera' => $blockCamera,
			'weather' => $weather,
			'weather_region' => $weather_region,
			'bannerRectangle' => $bannerRectangle,
			'bannerSquare' => $bannerSquare,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			'bannerMobilesquare2' => $bannerMobilesquare2,
			// Extra pro šablonu (widgety, meta)
			'cityRank1' => $city_rank_number_1,
			'hasTwitterWidgets' => !empty($printableText) && str_contains($printableText, '{{twitter-feed-'),
			'hasFacebookWidgets' => !empty($printableText) && str_contains($printableText, '{{facebook-feed-'),
			'hasFacebookLiveWidgets' => !empty($printableText) && str_contains($printableText, '{{facebook-live-feed-'),
			'hasYoutubeWidgets' => !empty($printableText) && str_contains($printableText, '{{youtube-video-'),
			'hasOnlineNewsWidget' => !empty($printableText) && str_contains($printableText, '{{online-reportaz}}'),
			'recaptchaSiteKey' => getenv('GOOGLE_RECAPTCHA_SITE_KEY') ?: null,
			'currentUrl' => $request->getUri(),
			'schemeHost' => $request->getSchemeAndHttpHost(),
		]));
		$response->setPublic();
		$response->setMaxAge(300);
		return $response;
	}

	public function articlePr(Request $request, PhtmlRenderer $renderer): Response
	{
		$article_id = (int) $request->attributes->get('article_id', 0);
		if (! $article_id) {
			return new RedirectResponse($this->urlGenerator->generate('news_pr'));
		}
		$printableText = '';

		try {
			$article = $this->playkitRepository->getArticlePr($article_id);
			if (! $article) {
				return new RedirectResponse($this->urlGenerator->generate('news_pr'));
			}

			// Text článku k tisku, bez widgetů
			$printableText = $article['text'];

			// Počítání zobrazení článků
			$this->newsRepository->setImpressionsCountPr($article_id);

			// Související články
			if ($article['text']) {
				$article['text'] = $this->insertRelativeArticle($article['text']);
			}

			// Související PR články
			if ($article['text']) {
				$article['text'] = $this->insertRelativePrArticle($article['text']);
			}

			// Související KAM VYRAZIT články
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

			// Facebook LIVE feed
			if ($article['text']) {
				$article['text'] = $this->insertFacebookLive($article['text']);
			}

			// Youtube video
			if ($article['text']) {
				$article['text'] = $this->insertYoutube($article['text'], $request->getSchemeAndHttpHost());
			}
		} catch (\Exception $ex) {
			return new RedirectResponse($this->urlGenerator->generate('news_pr'));
		}

		// Celkový počet článků
		$newsCount = $this->playkitRepository->getCountPR();

		// PR články
		$pr = $this->newsRepository->getPrArticles(11);

		// Doporučujeme - hlavní články z HP
		$newArticles = $this->playkitRepository->getAllHomepage([$article_id]);   // Dříve název "$this->getHomepageTable()->getAll()"
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
		// Rozdělíme $newArticles na 2 poloviny. První podpole (prvních 5 položek). Druhé podpole (zbytek).
		$dividedArray = array_chunk($newArticles ?: [], 5);
		$newArticlesFirstHalf = isset($dividedArray[0]) ? $dividedArray[0] : null;
		$newArticlesSecondHalf = isset($dividedArray[1]) ? $dividedArray[1] : null;

		// Nejnovější zprávy pro MSK
		$regionArticles = $this->newsRepository->getArticlesByRegionId(7, 6, $article_id);
		if ($regionArticles) {
			foreach ($regionArticles as $id => $item) {
				$date = new \DateTime($item['public_from']);
				$today = new \DateTime();
				if ($today->format('Y-m-d') === $date->format('Y-m-d')) {
					$regionArticles[$id]['date'] = 'Dnes';
				} else if ($today->modify('-1 day')->format('Y-m-d') === $date->format('Y-m-d')) {
					$regionArticles[$id]['date'] = 'Včera';
				} else {
					$regionArticles[$id]['date'] =  $date->format('d.m.');
				}
				$regionArticles[$id]['time'] =  $date->format('H:i');
				$regionArticles[$id]['url'] = '/zpravy/' . $item['region_url'] . '/' . $item['city_url'] . '/' . $item['article_id'] . '/' . $this->removeAccent($item['title'], '-');
			}
		}

		// Blok Kam vyrazit
		$blockTriptip = $this->newsRepository->getTriptipArticles(4, false, true);

		// Blok Nabídky práce
		$blockJob = $this->jobRepository->getRandForWeb(132, 4);

		// Blok kamery
		$blockCamera = $this->cameraRepository->fetchAllLimit(4);

		// Počasí
		$weather = $this->playkitRepository->getWeatherForNews('Ostrava');

		// Banner rectangle
		$bannerRectangle = $this->bannerRepository->getRectangle();

		// Banner square
		$bannerSquare = $this->bannerRepository->getSquare();

		// Banner mobile square 1
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();

		// Banner mobile square 2
		$bannerMobilesquare2 = $this->bannerRepository->getMobilesquare2();

		return new Response($renderer->renderWithLayout('news/web/news/article-pr', [
			'newsCount' => $newsCount,
			'pr' => $pr,
			'article' => $article,
			'printableText' => $printableText,
			'newArticlesFirstHalf' => $newArticlesFirstHalf,
			'newArticlesSecondHalf' => $newArticlesSecondHalf,
			'regionArticles' => $regionArticles,
			'blockTriptip' => $blockTriptip,
			'blockJob' => $blockJob,
			'blockCamera' => $blockCamera,
			'weather' => $weather,
			'weather_region' => 'Ostrava',
			'bannerRectangle' => $bannerRectangle,
			'bannerSquare' => $bannerSquare,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			'bannerMobilesquare2' => $bannerMobilesquare2,
			// Extra pro šablonu (widgety, meta)
			'hasTwitterWidgets' => !empty($printableText) && str_contains($printableText, '{{twitter-feed-'),
			'hasFacebookWidgets' => !empty($printableText) && str_contains($printableText, '{{facebook-feed-'),
			'hasFacebookLiveWidgets' => !empty($printableText) && str_contains($printableText, '{{facebook-live-feed-'),
			'hasYoutubeWidgets' => !empty($printableText) && str_contains($printableText, '{{youtube-video-'),
			'currentUrl' => $request->getUri(),
			'schemeHost' => $request->getSchemeAndHttpHost(),
		]));
	}

	public function prnews(Request $request, PhtmlRenderer $renderer): Response
	{
		$articles = null;
		try {
			$page = (int) $request->query->get('strana', 1);
			$articles = $this->playkitRepository->getPaginatorByPR($page, 10);
		} catch (\Exception $e) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		// Celkový počet článků
		$newsCount = $this->playkitRepository->getCountPR();

		// PR články
		$pr = $this->newsRepository->getPrArticles(7);

		// Banner rectangle
		$bannerRectangle = $this->bannerRepository->getRectangle();

		// Banner mobile square 1
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();

		return new Response($renderer->renderWithLayout('news/web/news/prnews', [
			'articles' => $articles,
			'page' => $page,
			'newsCount' =>  $newsCount,
			'pr' => $pr,
			'bannerRectangle' => $bannerRectangle,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			// Extra pro šablonu
			'page' => $page,
			'total' => $newsCount,
			'limit' => 25,
			'currentUrl' => $request->getUri(),
		]));
	}

	public function shortlink(Request $request): RedirectResponse
	{
		try {
			$shortlink = $request->attributes->get('shortlink');
			//var_dump($shortlink);
			if (!$shortlink) {
				return new RedirectResponse($this->urlGenerator->generate('news'));
			}

			$article_url = $this->playkitRepository->getArticleUrlByShortlink($shortlink);
			//var_dump($article_url);

			if (!$article_url) {
				return new RedirectResponse($this->urlGenerator->generate('news'));
			}

			return new RedirectResponse($article_url);

		} catch (\Exception $e) {
			//var_dump ($e->getMessage());
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}
	}

	public function download(Request $request): Response
	{
		$video_id = $request->attributes->get('video_id', 0);
		$quality = $request->attributes->get('quality', 'hq');

		$video = $this->playkitRepository->getVideoById($video_id);

		$fileUrl = $this->LIGHT_URL . 'zpravy/publikovano/' . $video[0]['folder_light'] . '/' . $video[0]['file'] . '_' . $quality . '.mp4';

		$response = new StreamedResponse(static function () use ($fileUrl): void {
			readfile($fileUrl);
		});
		$response->headers->set('Content-Description', 'File Transfer');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $video[0]['file'] . '_' . $quality . '.mp4"');
		$response->headers->set('Content-Type', 'application/force-download');

		return $response;
	}

	public function getTicker(): JsonResponse
	{
		$success = true;
		$message = null;
		$content = null;

		try {
			$ticker = $this->tickerRepository->getItems();

			if ($ticker) {
				$content = '<ul>';
				foreach ($ticker as $item) {
					$content .= '<div><li><span>' . $item . '</span></li></div>';
				}
				$content .= '</ul>';
			}
		} catch (\Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'content' => $content,
			'success' => $success,
			'message' => $message,
		]);
	}

	public function getCrawl(): JsonResponse
	{
		$success = true;
		$message = null;
		$content = null;
		$start = null;
		$stop = null;

		try {
			$crawl = $this->crawlRepository->getCrawl(1);

			if ($crawl['auto_delete_start']) {
				$start = $crawl['auto_delete_start'];
			}
			if ($crawl['auto_delete_stop']) {
				$stop = $crawl['auto_delete_stop'];
			}

			$content .= '<div>';

			$items = $this->crawlRepository->getItems(1);

			$separator = str_replace(' ', '&nbsp;', $crawl['separator']);

			if ($crawl['text_before']) {
				$content .= $crawl['text_before'];
				$content .= ' ';
				if ($crawl['separator']) {
					$content .= $separator . ' ';
				}
			}

			if ($items) {
				foreach ($items as $i => $iValue) {
					if ($iValue !== '') {
						$content .= str_replace(' ', '&nbsp;', $iValue);
						if ($crawl['separator'] && (($i + 1) < count($items))) {
							$content .= ' ' . $separator . ' ';
						}
					}
				}
			}

			if ($crawl['text_after']) {
				$content .= ' ';
				if ($crawl['separator']) {
					$content .= $separator . ' ';
				}
				$content .= $crawl['text_after'];
			}

			$content .= '</div>';
		} catch (\Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		return new JsonResponse([
			'content' => $content,
			'success' => $success,
			'message' => $message,
			'start' => $start,
			'stop' => $stop,
		]);
	}

	/**
	 * stáhne přepis videa jako DOCX soubor
	 * @return Response
	 */
	public function overwriteDocx(Request $request): Response
	{
		$article_id = (int) $request->attributes->get('article_id', 0);

		$article = $this->newsRepository->getArticle($article_id);

		if ($article) {

			$html = (string) $article['text'];
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
			$phpWord->getSettings()->setThemeFontLang(new Language('cs-CZ'));
			$phpWord->setDefaultParagraphStyle([
				'spaceAfter' => 240,
				'lineHeight' => 1.4,
			]);
			$section = $phpWord->addSection();

			// datum
			$publicFrom = new \DateTime($article['public_from']);

			// odkaz
			$articleUrl = $this->urlGenerator->generate('news_region_city_article', [
				'url' => $article['region_url'],
				'city_url' => $article['city_url'],
				'article_id' => $article['article_id'],
				'article_url' => $article['article_url'],
			], UrlGeneratorInterface::ABSOLUTE_URL);

			// hlavička (logo vlevo, datum + odkaz vpravo)
			$table = $section->addTable([
				'width' => 5000,
				'unit' => TblWidth::PERCENT,
				'borderSize' => 0,
				'borderColor' => 'FFFFFF',
				'cellMarginTop' => 0,
				'cellMarginLeft' => 0,
				'cellMarginRight' => 200,
				'cellMarginBottom' => 0,
			]);

			$table->addRow();

			// logo
			$logoPath = $this->PUBLIC_PATH . '/img/web/logo_polar.png';
			$cellLeft = $table->addCell(6500, ['valign' => 'top']);
			if (is_file($logoPath)) {
				$cellLeft->addImage($logoPath, ['height' => 40]);
			}

			// datum + odkaz
			$cellRight = $table->addCell(2500, ['valign' => 'top']);

			$cellRight->addText(
				'Datum vydání:',
				[
					'size' => 9,
					'bold' => true,
				],
				[
					'alignment' => Jc::END,
					'spaceAfter' => 0,
				]
			);

			$cellRight->addText(
				$publicFrom->format('j.n.Y, H:i'),
				[
					'size' => 9,
					'bold' => true,
				],
				[
					'alignment' => Jc::END,
					'spaceAfter' => 80,
				]
			);

			$cellRight->addLink(
				$articleUrl,
				'Otevřít článek na polar.cz',
				[
					'color' => '0563C1',
					'underline' => 'single',
					'size' => 10,
				],
				[
					'alignment' => Jc::END,
					'spaceAfter' => 0,
				]
			);

			// mezera
			$section->addTextBreak(1);

			// nadpis - název pořadu
			$section->addText(
				(string) $article['title'],
				[
					'bold' => true,
					'size' => 14,
				],
				[
					'spaceAfter' => 160,
				]
			);

			// try/catch z toho důvodu, aby při složitém HTML nezůstala zobrazená chybová stránka 500
			try {
				Html::addHtml($section, $html, false, false);
			} catch (\Throwable $e) {

				/* Případné uložení problematického HTML
				 * file_put_contents(
					PUBLIC_PATH . '/docx-debug-' . time() . '.txt',
					print_r([
						'error' => $e->getMessage(),
						'html' => $html,
						'json' => json_encode($html, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
					], true)
				);*/

				$section->addText('Přepis se nepodařilo převést do DOCX.');
			}

			$baseName = pathinfo($article['article_url'], PATHINFO_FILENAME);
			$baseName = mb_substr($baseName, 0, 40);
			$filename = 'polar-prepis-' . $baseName . '-' . $publicFrom->format('Y-m-d') . '.docx';

			$tmp = tempnam(sys_get_temp_dir(), 'docx_');
			$writer = IOFactory::createWriter($phpWord, 'Word2007');
			$writer->save($tmp);

			$response = new Response((string) file_get_contents($tmp));
			$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
			$response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
			$response->headers->set('Content-Length', (string) filesize($tmp));

			@unlink($tmp);

			return $response;
		}

		return new Response();
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
	 * @param int $article_id
	 * @return string
	 */
	private function insertOnlineNews(string $text, int $article_id): string
	{
		if (mb_strpos($text, '{{online-reportaz}}', 0, 'UTF-8')) {
			$posStart = mb_strpos($text, '{{online-reportaz}}',0, 'UTF-8');
			$posEnd = $posStart + 19;
			$id = mb_substr($text, $posStart, $posEnd - $posStart);

			$html  = '<div class="onlineNews">Online reportáž</div>';

			$text = str_replace($id, $html, $text);

			// Inline JS pro AJAX načítání online reportáže se řeší v šabloně
		}
		return $text;
	}

	/**
	 * @return JsonResponse
	 */
	public function getContentOnlineNews(Request $request): JsonResponse
	{
		$article_id = (int) $request->attributes->get('article_id', 0);
		$page = (int) $request->request->get('page', 1);
		$limit = 10;
		$count = 0;
		$html = '';
		$refreshDate = '1970/01/01 00:00:00';
		$success = true;

		try {
			$items = $this->playkitRepository->getOnlineNewsByArticleId($article_id, $page, $limit);
			$count = $this->playkitRepository->getCountOnlineNewsByArticleId($article_id);

			// Poslední příspěvěk
			try {
				$item = $this->playkitRepository->getOnlineNewsByArticleId($article_id, 1, 1);
				if (isset($item[0])) {
					$datetime = new \DateTime($item[0]['datetime']);
					$refreshDate = $datetime->format('Y/m/d H:i');
				}
			} catch (\Exception $e) {
				$refreshDate = '1970/01/01 00:00:00';
			}

			if ($items) {
				$html .=
					'<h3 class="mb-3">Online reportáž <span class="btnRefreshOnlineNews cur-pointer pull-right text-0">aktualizovat&nbsp;<i class="fa fa-fw fa-refresh text-primary"></i></span></h3>' .
					'<div class="container-fluid ps-3">';
				foreach ($items as $item) {

					try {
						$datetime = new \DateTime($item['datetime']);
						$today = new \DateTime();

						if ($today->format('Y-m-d') === $datetime->format('Y-m-d')) {
							$day = 'dnes';
						} else if ($today->modify('-1 day')->format('Y-m-d') === $datetime->format('Y-m-d')) {
							$day = 'včera';
						} else {
							$day = $datetime->format('j.n.');
						}
					} catch (\Exception $e) {
						$day = 'NaN';
					}

					try {
						$datetime = new \DateTime($item['datetime']);
						$datetime = $datetime->format('H:i');
					} catch (\Exception $e) {
						$datetime = 'NaN';
					}
					$html .=
						'<div class="row mt-2">' .
							'<div class="col-md-2 font-weight-bold text-primary text-4-5 text-start line-height-2 ps-0">' .
								$datetime .
								'<br /><span class="text-1 font-weight-normal">' . $day . '</span>' .
							'</div>' .
							'<div class="col-md-10 content">' .
								$item['content'] .
							'</div>' .
						'</div>';
				}
				$html .=
					'</div>';

				// Paginator
				if ($count > $limit) {
					$pages = (int)($count / $limit);
					if (($count % $limit) !== 0) {
						$pages++;
					}
					$html .=
						'<ul class="pagination">';
					for ($i = 1; $i <= $pages; $i++) {
						$class = 'page-item';
						if ($page === $i) {
							$class .= ' active';
						} else {
							$class .= ' btnPaginator';
						}
						$html .=
							'<li class="' . $class . '" data-page="' . $i . '">' .
								'<a class="page-link text-decoration-none" href="#" title="">' .
									$i .
								'</a>' .
							'</li>';
					}
					$html .=
						'</ul>';
				}

				// Doplnění widgetů
				$html = $this->insertRelativeArticle($html);
				$html = $this->insertFacebook($html);
				$html = $this->insertTwitter($html);
				$html = $this->insertYoutube($html, $request->getSchemeAndHttpHost());
				$html = $this->insertFacebookLive($html);
			}
		} catch (\Exception $e) {
			$success = $e->getMessage();
		}

		return new JsonResponse([
			'success' => $success,
			'article_id' => $article_id,
			'page' => $page,
			'limit' => $limit,
			'count' => $count,
			'refreshDate' => $refreshDate,
			'html' => $html,
		]);
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
	private function insertFacebookLive(string $text): string
	{
		$i = 1;
		FACEBOOKLIVEFEED:
		if (mb_strpos($text, '{{facebook-live-feed-', 0, 'UTF-8')) {
			$posStart = mb_strpos($text, '{{facebook-live-feed-', 0, 'UTF-8');
			$posEnd = mb_strpos($text, '}}', $posStart, 'UTF-8');
			$code = mb_substr($text, $posStart, $posEnd - $posStart + 2);
			$both_ids = mb_substr($code, 21, -2);
			$both_ids = str_replace('"', '', $both_ids);
			if (mb_strpos($both_ids, '-', 0, 'UTF-8')) {
				$two_ids = explode("-", $both_ids);
				if (isset($two_ids[0]) && isset($two_ids[1])) {
					$id_page = $two_ids[0];
					$id_post = $two_ids[1];
				}
			}
			if (isset($id_page) && isset($id_post)) {
				$html = '<div class="fb-video" data-href="https://www.facebook.com/'.$id_page.'/videos/'.$id_post.'/" id="facebook-live-feed-' . $i . '"></div>';
				$text = str_replace($code, $html, $text);
			} else {
				$text = str_replace($code, '', $text);
			}
			$i++;
			GOTO FACEBOOKLIVEFEED;
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

	/**
	 * @param string|null $url
	 * @return int|null
	 */
	private function getJobOkresCode(?string $url): ?int
	{
		switch ($url) {
			case 'ostrava':
				$okres_code = 3807;
				break;
			case 'karvinsko':
				$okres_code = 3803;
				break;
			case 'frydeckomistecko':
				$okres_code = 3802;
				break;
			case 'opavsko':
				$okres_code = 3806;
				break;
			case 'novojicinsko':
				$okres_code = 3804;
				break;
			case 'bruntalsko':
				$okres_code = 3801;
				break;
			default:
				$okres_code = null;
				break;
		}
		return $okres_code;
	}

	/**
	 * @param string|null $region_url
	 * @return string|null
	 */
	private function getRegionForWeather(?string $region_url): ?string
	{
		$weather_region = match ($region_url) {
			'ostrava' => 'Ostrava',
			'karvinsko' => 'Karviná',
			'frydeckomistecko' => 'Frýdek-Místek',
			'opavsko' => 'Opava',
			'novojicinsko' => 'Nový Jičín',
			'bruntalsko' => 'Bruntál',
			default => 'Ostrava',
		};
		return $weather_region;
	}

	/**
	 * Funkce pro úpravu odkazu
	 * @param string $url
	 * @param string $pageParam
	 * @param int $newPageValue
	 * @return string
	 */
	private function updatePageParam($url, $pageParam, $newPageValue) {
		// Parsing URL
		$parsedUrl = parse_url($url);
		if (isset($parsedUrl['query'])) {
			parse_str($parsedUrl['query'], $queryParams);
		}

		// Update the page parameter
		$queryParams[$pageParam] = $newPageValue;

		// Rebuild the query and URL
		$newQuery = http_build_query($queryParams);
		return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'] . '?' . $newQuery;
	}

	/**
	 * @param $text
	 * @param null $replace
	 * @return string
	 */
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
