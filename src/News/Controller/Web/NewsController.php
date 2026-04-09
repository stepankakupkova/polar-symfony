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
		private JobRepository $jobRepository,
		private CameraRepository $cameraRepository,
		private PlaykitRepository $playkitRepository,
		private BannerRepository $bannerRepository,
		private TickerRepository $tickerRepository,
		private CrawlRepository $crawlRepository,
		private UrlGeneratorInterface $urlGenerator,
	) {}

	public function index(Request $request, PhtmlRenderer $renderer): Response
	{
		$page = max(1, (int) $request->query->get('page', 1));
		$limit = 25;

		$articles = $this->newsRepository->getPaginator($page, $limit);
		$newsCount = $this->newsRepository->getCountFromSettings();
		$total = $this->newsRepository->getCount();
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

		return new Response($renderer->renderWithLayout('news/web/news/index', [
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

	public function region(Request $request, PhtmlRenderer $renderer): Response
	{
		$url = (string) $request->attributes->get('url', '');
		if ($url === '') {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		$region = $this->playkitRepository->getRegionByUrl($url);
		if (!$region) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		$page = max(1, (int) $request->query->get('page', 1));
		$limit = 25;

		$articles = $this->newsRepository->getPaginatorByRegion((int) $region['id'], $page, $limit);
		$total = $this->newsRepository->getCount((int) $region['id']);
		$newsCount = $total;
		$pr = $this->newsRepository->getPrArticles(11);
		$blockTriptip = $this->newsRepository->getTriptipArticles(4, (int) $region['id'], true);
		$blockJob = $this->jobRepository->getRandForWeb((int) ($region['city_code'] ?? 132), 4);
		$blockCamera = $this->cameraRepository->fetchAllLimit(4);
		$weather = $this->playkitRepository->getWeatherForNews($region['region'] ?? 'Ostrava');
		$weatherRegion = $region['region'] ?? 'Ostrava';

		$bannerLeaderboard = $this->bannerRepository->getLeaderboard();
		$bannerMobilesticky = $this->bannerRepository->getMobilesticky();
		$bannerRectangle = $this->bannerRepository->getRectangle();
		$bannerSquare = $this->bannerRepository->getSquare();
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();
		$bannerMobilesquare2 = $this->bannerRepository->getMobilesquare2();

		return new Response($renderer->renderWithLayout('news/web/news/region', [
			'articles'          => $articles,
			'newsCount'         => $newsCount,
			'page'              => $page,
			'total'             => $total,
			'limit'             => $limit,
			'pr'                => $pr,
			'region'            => $region,
			'blockTriptip'      => $blockTriptip,
			'blockJob'          => $blockJob,
			'blockCamera'       => $blockCamera,
			'weather'           => $weather,
			'weatherRegion'     => $weatherRegion,
			'currentUrl'        => $request->getUri(),
			'schemeHost'        => $request->getSchemeAndHttpHost(),
			'bannerLeaderboard' => $bannerLeaderboard,
			'bannerMobilesticky'=> $bannerMobilesticky,
			'bannerRectangle'   => $bannerRectangle,
			'bannerSquare'      => $bannerSquare,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			'bannerMobilesquare2' => $bannerMobilesquare2,
		]));
	}

	public function city(Request $request, PhtmlRenderer $renderer): Response
	{
		$url = (string) $request->attributes->get('url', '');
		$cityUrl = (string) $request->attributes->get('city_url', '');
		if ($url === '' || $cityUrl === '') {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		$region = $this->playkitRepository->getRegionByUrl($url);
		if (!$region) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		$city = $this->playkitRepository->getCityByUrl($cityUrl);
		if (!$city) {
			return new RedirectResponse($this->urlGenerator->generate('news_region', ['url' => $url]));
		}

		$page = max(1, (int) $request->query->get('page', 1));
		$limit = 25;

		$articles = $this->newsRepository->getPaginatorByCity((int) $city['id'], $page, $limit);
		$total = $this->newsRepository->getCount(null, (int) $city['id']);
		$newsCount = $total;
		$pr = $this->newsRepository->getPrArticles(11);
		$blockTriptip = $this->newsRepository->getTriptipArticles(4, (int) $region['id'], true);
		$blockJob = $this->jobRepository->getRandForWeb((int) ($region['city_code'] ?? 132), 4);
		$blockCamera = $this->cameraRepository->fetchAllLimit(4);
		$weather = $this->playkitRepository->getWeatherForNews($region['region'] ?? 'Ostrava');
		$weatherRegion = $region['region'] ?? 'Ostrava';

		$bannerLeaderboard = $this->bannerRepository->getLeaderboard();
		$bannerMobilesticky = $this->bannerRepository->getMobilesticky();
		$bannerRectangle = $this->bannerRepository->getRectangle();
		$bannerSquare = $this->bannerRepository->getSquare();
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();
		$bannerMobilesquare2 = $this->bannerRepository->getMobilesquare2();

		return new Response($renderer->renderWithLayout('news/web/news/city', [
			'articles'          => $articles,
			'newsCount'         => $newsCount,
			'page'              => $page,
			'total'             => $total,
			'limit'             => $limit,
			'pr'                => $pr,
			'region'            => $region,
			'city'              => $city,
			'blockTriptip'      => $blockTriptip,
			'blockJob'          => $blockJob,
			'blockCamera'       => $blockCamera,
			'weather'           => $weather,
			'weatherRegion'     => $weatherRegion,
			'currentUrl'        => $request->getUri(),
			'schemeHost'        => $request->getSchemeAndHttpHost(),
			'bannerLeaderboard' => $bannerLeaderboard,
			'bannerMobilesticky'=> $bannerMobilesticky,
			'bannerRectangle'   => $bannerRectangle,
			'bannerSquare'      => $bannerSquare,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			'bannerMobilesquare2' => $bannerMobilesquare2,
		]));
	}

	public function redactor(Request $request, PhtmlRenderer $renderer): Response
	{
		$redactorUrl = (string) $request->attributes->get('redactor_url', '');
		if ($redactorUrl === '') {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		$redactor = $this->playkitRepository->getRedactorByUrl($redactorUrl);
		if (!$redactor) {
			return new RedirectResponse($this->urlGenerator->generate('news'));
		}

		$page = max(1, (int) $request->query->get('page', 1));
		$limit = 10;

		$articles = $this->newsRepository->getPaginatorByRedactor($redactorUrl, $page, $limit);
		$total = $this->newsRepository->getCount(null, null, $redactorUrl);
		$newsCount = $total;
		$pr = $this->newsRepository->getPrArticles(9);

		$bannerRectangle = $this->bannerRepository->getRectangle();
		$bannerSquare = $this->bannerRepository->getSquare();
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();

		return new Response($renderer->renderWithLayout('news/web/news/redactor', [
			'articles'           => $articles,
			'redactor'           => $redactor,
			'newsCount'          => $newsCount,
			'page'               => $page,
			'total'              => $total,
			'limit'              => $limit,
			'pr'                 => $pr,
			'currentUrl'         => $request->getUri(),
			'schemeHost'         => $request->getSchemeAndHttpHost(),
			'bannerRectangle'    => $bannerRectangle,
			'bannerSquare'       => $bannerSquare,
			'bannerMobilesquare1' => $bannerMobilesquare1,
		]));
	}

	public function prnews(Request $request, PhtmlRenderer $renderer): Response
	{
		$page = max(1, $request->query->getInt('strana', 1));
		$limit = 10;

		$articles = $this->playkitRepository->getPaginatorByPR($page, $limit);
		$total = $this->playkitRepository->getCountPR();

		// PR články
		$pr = $this->newsRepository->getPrArticles(7);

		// Bannery
		$banners = $this->bannerRepository->getBannersForLayout();

		return new Response($renderer->renderWithLayout('news/web/news/prnews', [
			'articles' => $articles,
			'page' => $page,
			'total' => $total,
			'limit' => $limit,
			'newsCount' => $total,
			'pr' => $pr,
			'bannerRectangle' => $banners['rectangle'] ?? null,
			'bannerMobilesquare1' => $banners['mobilesquare1'] ?? null,
			'bannerLeaderboard' => $banners['leaderboard'] ?? null,
			'bannerMobilesticky' => $banners['mobilesticky'] ?? null,
		]));
	}

	public function articlePr(Request $request, PhtmlRenderer $renderer): Response
	{
		$articleId = (int) $request->attributes->get('article_id', 0);
		if ($articleId <= 0) {
			return new RedirectResponse($this->urlGenerator->generate('news_pr'));
		}

		$article = $this->playkitRepository->getArticlePr($articleId);
		if (!$article) {
			return new RedirectResponse($this->urlGenerator->generate('news_pr'));
		}

		// Počítání zobrazení článků
		$this->newsRepository->setImpressionsCountPr($articleId);

		// Text článku k tisku, bez widgetů
		$printableText = (string) ($article['text'] ?? '');

		$hasTwitterWidgets = !empty($article['text']) && str_contains($article['text'], '{{twitter-feed-');
		$hasFacebookWidgets = !empty($article['text']) && str_contains($article['text'], '{{facebook-feed-');
		$hasFacebookLiveWidgets = !empty($article['text']) && str_contains($article['text'], '{{facebook-live-feed-');
		$hasYoutubeWidgets = !empty($article['text']) && str_contains($article['text'], '{{youtube-video-');

		// Widgety v textu
		if (!empty($article['text'])) {
			$article['text'] = $this->insertRelativeArticle($article['text']);
			$article['text'] = $this->insertRelativePrArticle($article['text']);
			$article['text'] = $this->insertRelativeTriptipArticle($article['text']);
			$article['text'] = $this->insertTwitter($article['text']);
			$article['text'] = $this->insertFacebook($article['text']);
			$article['text'] = $this->insertFacebookLive($article['text']);
			$article['text'] = $this->insertYoutube($article['text'], $request->getSchemeAndHttpHost());
		}

		// PR články
		$pr = $this->newsRepository->getPrArticles(11);

		// Doporučujeme - hlavní články z HP
		$recommendedArticles = $this->playkitRepository->getAllHomepage([$articleId]);
		$recommendedArticles = $this->prepareRecommendedArticles($recommendedArticles);
		$newArticlesFirstHalf = $recommendedArticles ? array_slice($recommendedArticles, 0, 5) : null;
		$newArticlesSecondHalf = $recommendedArticles ? array_slice($recommendedArticles, 5) : null;

		// Nejnovější zprávy pro MSK
		$regionArticles = $this->prepareRegionArticles($this->newsRepository->getArticlesByRegionId(7, 6, $articleId));

		// Blok Kam vyrazit
		$blockTriptip = $this->newsRepository->getTriptipArticles(4, null, true);

		// Blok Nabídky práce
		$blockJob = $this->jobRepository->getRandForWebByCityCode(132, 4);

		// Blok kamery
		$blockCamera = $this->cameraRepository->fetchAllLimit(4);

		// Počasí
		$weather = $this->playkitRepository->getWeatherForNews('Ostrava');

		// Bannery
		$bannerRectangle = $this->bannerRepository->getRectangle();
		$bannerSquare = $this->bannerRepository->getSquare();
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();
		$bannerMobilesquare2 = $this->bannerRepository->getMobilesquare2();

		return new Response($renderer->renderWithLayout('news/web/news/article-pr', [
			'article' => $article,
			'printableText' => $printableText,
			'pr' => $pr,
			'newArticlesFirstHalf' => $newArticlesFirstHalf,
			'newArticlesSecondHalf' => $newArticlesSecondHalf,
			'regionArticles' => $regionArticles,
			'blockTriptip' => $blockTriptip,
			'blockJob' => $blockJob,
			'blockCamera' => $blockCamera,
			'weather' => $weather,
			'weatherRegion' => 'Ostrava',
			'bannerRectangle' => $bannerRectangle,
			'bannerSquare' => $bannerSquare,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			'bannerMobilesquare2' => $bannerMobilesquare2,
			'hasTwitterWidgets' => $hasTwitterWidgets,
			'hasFacebookWidgets' => $hasFacebookWidgets,
			'hasFacebookLiveWidgets' => $hasFacebookLiveWidgets,
			'hasYoutubeWidgets' => $hasYoutubeWidgets,
			'currentUrl' => $request->getUri(),
			'schemeHost' => $request->getSchemeAndHttpHost(),
		]));
	}

	public function article(Request $request, PhtmlRenderer $renderer): Response
	{
		$url = (string) $request->attributes->get('url', '');
		$cityUrl = (string) $request->attributes->get('city_url', '');
		$articleId = (int) $request->attributes->get('article_id', 0);
		$articleUrl = (string) $request->attributes->get('article_url', '');

		if ($url === '' || $cityUrl === '' || $articleId <= 0) {
			return $this->redirectToNewsList();
		}

		$region = $this->playkitRepository->getRegionByUrl($url);
		$city = $this->playkitRepository->getCityByUrl($cityUrl);
		$playkitArticle = $city ? $this->playkitRepository->getArticle($articleId, (int) $city['id']) : null;
		$webArticle = $this->newsRepository->getArticle($articleId);

		if (!$region || !$city || !$playkitArticle || !$webArticle) {
			return $this->redirectToNewsList();
		}

		$article = $playkitArticle;
		if (($webArticle['title'] ?? null) && ($webArticle['anotation'] ?? null) && ($webArticle['text'] ?? null)) {
			$article['title'] = $webArticle['title'];
			$article['anotation'] = $webArticle['anotation'];
			$article['text'] = $webArticle['text'];
		}
		$article['article_id'] = $webArticle['article_id'];
		$article['article_url'] = $webArticle['article_url'];
		$article['city_title'] = $webArticle['city_title'] ?? $city['city'];
		$article['city_url'] = $webArticle['city_url'] ?? $city['url'];
		$article['region_url'] = $webArticle['region_url'] ?? $region['url'];
		$article['author'] = trim((string) (($playkitArticle['name'] ?? '') . ' ' . ($playkitArticle['surname'] ?? '')));
		$article['author_url'] = $playkitArticle['redactor_url'] ?? null;

		if ((int) ($playkitArticle['public'] ?? 0) === 0 || empty($playkitArticle['region']) || empty($playkitArticle['city'])) {
			return $this->redirectToNewsList();
		}

		$hasTwitterWidgets = !empty($article['text']) && str_contains($article['text'], '{{twitter-feed-');
		$hasFacebookWidgets = !empty($article['text']) && str_contains($article['text'], '{{facebook-feed-');
		$hasFacebookLiveWidgets = !empty($article['text']) && str_contains($article['text'], '{{facebook-live-feed-');
		$hasYoutubeWidgets = !empty($article['text']) && str_contains($article['text'], '{{youtube-video-');
		$hasOnlineNewsWidget = !empty($article['text']) && str_contains($article['text'], '{{online-reportaz}}');

		if ($hasOnlineNewsWidget) {
			$hasTwitterWidgets = true;
			$hasFacebookWidgets = true;
			$hasFacebookLiveWidgets = true;
			$hasYoutubeWidgets = true;
		}

		if (!empty($article['text'])) {
			$article['text'] = $this->insertRelativeArticle($article['text']);
			$article['text'] = $this->insertRelativePrArticle($article['text']);
			$article['text'] = $this->insertRelativeTriptipArticle($article['text']);
			$article['text'] = $this->insertOnlineNews($article['text'], $articleId);
			$article['text'] = $this->insertTwitter($article['text']);
			$article['text'] = $this->insertFacebook($article['text']);
			$article['text'] = $this->insertFacebookLive($article['text']);
			$article['text'] = $this->insertYoutube($article['text'], $request->getSchemeAndHttpHost());
		}

		if (
			($article['region_url'] ?? '') !== $url
			|| ($article['city_url'] ?? '') !== $cityUrl
			|| ($article['article_url'] ?? '') !== $articleUrl
		) {
			return new RedirectResponse($this->urlGenerator->generate('news_region_city_article', [
				'url' => $article['region_url'],
				'city_url' => $article['city_url'],
				'article_id' => $article['article_id'],
				'article_url' => $article['article_url'],
			]));
		}

		$this->newsRepository->setImpressionsCount($articleId);
		$cityRank1 = $this->playkitRepository->getCityRank1ByArticleId($articleId);
		$pr = $this->newsRepository->getPrArticles(11);
		$recommendedArticles = $this->playkitRepository->getAllHomepage([$articleId]);
		$recommendedArticles = $this->prepareRecommendedArticles($recommendedArticles);
		$newArticlesFirstHalf = $recommendedArticles ? array_slice($recommendedArticles, 0, 5) : null;
		$newArticlesSecondHalf = $recommendedArticles ? array_slice($recommendedArticles, 5) : null;
		$regionArticles = isset($region['id']) ? $this->prepareRegionArticles($this->newsRepository->getArticlesByRegionId((int) $region['id'], 6, $articleId)) : null;
		$blockTriptip = $this->newsRepository->getTriptipArticles(4, (int) ($region['id'] ?? 0), true);
		$blockJob = $this->jobRepository->getRandForWebByCityCode($this->getJobOkresCode($region['url'] ?? null), 4);
		$blockCamera = $this->cameraRepository->fetchAllLimit(4);
		$weatherRegion = $this->getRegionForWeather($region['url'] ?? null);
		$weather = $this->playkitRepository->getWeatherForNews($weatherRegion);
		$topicArticles = $this->getTopicArticles($article);
		$printableText = $this->preparePrintableText((string) ($article['text'] ?? ''));
		$bannerRectangle = $this->bannerRepository->getRectangle();
		$bannerSquare = $this->bannerRepository->getSquare();
		$bannerMobilesquare1 = $this->bannerRepository->getMobilesquare1();
		$bannerMobilesquare2 = $this->bannerRepository->getMobilesquare2();

		// TODO: doladit zbyvajici jemne rozdily proti produkci po vizualnim porovnani na konkretnich URL.
		return new Response($renderer->renderWithLayout('news/web/news/article', [
			'article' => $article,
			'region' => $region,
			'city' => $city,
			'cityRank1' => $cityRank1,
			'pr' => $pr,
			'newArticlesFirstHalf' => $newArticlesFirstHalf,
			'newArticlesSecondHalf' => $newArticlesSecondHalf,
			'topicArticles' => $topicArticles,
			'regionArticles' => $regionArticles,
			'blockTriptip' => $blockTriptip,
			'blockJob' => $blockJob,
			'blockCamera' => $blockCamera,
			'weather' => $weather,
			'weatherRegion' => $weatherRegion,
			'printableText' => $printableText,
			'bannerRectangle' => $bannerRectangle,
			'bannerSquare' => $bannerSquare,
			'bannerMobilesquare1' => $bannerMobilesquare1,
			'bannerMobilesquare2' => $bannerMobilesquare2,
			'hasTwitterWidgets' => $hasTwitterWidgets,
			'hasFacebookWidgets' => $hasFacebookWidgets,
			'hasFacebookLiveWidgets' => $hasFacebookLiveWidgets,
			'hasYoutubeWidgets' => $hasYoutubeWidgets,
			'hasOnlineNewsWidget' => $hasOnlineNewsWidget,
			'recaptchaSiteKey' => getenv('GOOGLE_RECAPTCHA_SITE_KEY') ?: null,
			'currentUrl' => $request->getUri(),
			'schemeHost' => $request->getSchemeAndHttpHost(),
		]));
	}

	public function downloadVideo(Request $request): Response
	{
		$videoId = (int) $request->attributes->get('video_id', 0);
		$quality = (string) $request->attributes->get('quality', 'hq');

		if ($videoId <= 0 || !in_array($quality, ['hq', 'lq'], true)) {
			return $this->redirectToNewsList();
		}

		$video = $this->playkitRepository->getVideoById($videoId);
		if (!$video || empty($video['folder_light']) || empty($video['file'])) {
			return $this->redirectToNewsList();
		}

		$fileUrl = 'https://light.polar.cz/videa/polar/zpravy/publikovano/' . $video['folder_light'] . '/' . $video['file'] . '_' . $quality . '.mp4';
		$stream = @fopen($fileUrl, 'rb');
		if ($stream === false) {
			return new RedirectResponse($fileUrl);
		}

		$response = new StreamedResponse(static function () use ($stream): void {
			fpassthru($stream);
			fclose($stream);
		});
		$response->headers->set('Content-Type', 'application/octet-stream');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $video['file'] . '_' . $quality . '.mp4"');

		return $response;
	}

	public function overwriteDocx(Request $request): Response
	{
		$articleId = (int) $request->attributes->get('article_id', 0);
		$article = $this->newsRepository->getArticle($articleId);

		if (!$article) {
			return $this->redirectToNewsList();
		}

		$html = $this->prepareArticleHtmlForDocx((string) ($article['text'] ?? ''));
		$phpWord = new PhpWord();
		$phpWord->getSettings()->setThemeFontLang(new Language('cs-CZ'));
		$phpWord->setDefaultParagraphStyle([
			'spaceAfter' => 240,
			'lineHeight' => 1.4,
		]);
		$section = $phpWord->addSection();

		$publicFrom = new \DateTime((string) $article['public_from']);
		$articleUrl = $this->urlGenerator->generate('news_region_city_article', [
			'url' => $article['region_url'],
			'city_url' => $article['city_url'],
			'article_id' => $article['article_id'],
			'article_url' => $article['article_url'],
		], UrlGeneratorInterface::ABSOLUTE_URL);

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

		$logoPath = $this->getProjectDir() . '/public/img/web/logo-email.png';
		$cellLeft = $table->addCell(6500, ['valign' => 'top']);
		if (is_file($logoPath)) {
			$cellLeft->addImage($logoPath, ['height' => 40]);
		}

		$cellRight = $table->addCell(2500, ['valign' => 'top']);
		$cellRight->addText('Datum vydání:', ['size' => 9, 'bold' => true], ['alignment' => Jc::END, 'spaceAfter' => 0]);
		$cellRight->addText($publicFrom->format('j.n.Y, H:i'), ['size' => 9, 'bold' => true], ['alignment' => Jc::END, 'spaceAfter' => 80]);
		$cellRight->addLink($articleUrl, 'Otevřít článek na polar.cz', ['color' => '0563C1', 'underline' => 'single', 'size' => 10], ['alignment' => Jc::END, 'spaceAfter' => 0]);

		$section->addTextBreak(1);
		$section->addText((string) $article['title'], ['bold' => true, 'size' => 14], ['spaceAfter' => 160]);

		try {
			Html::addHtml($section, $html, false, false);
		} catch (\Throwable) {
			$section->addText('Přepis se nepodařilo převést do DOCX.');
		}

		$baseName = pathinfo((string) $article['article_url'], PATHINFO_FILENAME);
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

	public function getContentOnlineNews(Request $request): JsonResponse
	{
		$articleId = (int) $request->attributes->get('article_id', 0);
		$page = max(1, (int) $request->request->get('page', 1));
		$limit = 10;
		$count = 0;
		$html = '';
		$refreshDate = '1970/01/01 00:00:00';

		try {
			$items = $this->playkitRepository->getOnlineNewsByArticleId($articleId, $page, $limit);
			$count = $this->playkitRepository->getCountOnlineNewsByArticleId($articleId);

			$lastItem = $this->playkitRepository->getOnlineNewsByArticleId($articleId, 1, 1);
			if (isset($lastItem[0]['datetime'])) {
				$refreshDate = (new \DateTime((string) $lastItem[0]['datetime']))->format('Y/m/d H:i');
			}

			if ($items) {
				$html .= '<h3 class="mb-3">Online reportáž <span class="btnRefreshOnlineNews cur-pointer float-end text-0">aktualizovat&nbsp;<i class="fa fa-fw fa-refresh text-primary"></i></span></h3>';
				$html .= '<div class="container-fluid ps-3">';

				foreach ($items as $item) {
					$day = 'NaN';
					$time = 'NaN';

					try {
						$datetime = new \DateTime((string) $item['datetime']);
						$today = new \DateTime();
						$time = $datetime->format('H:i');

						if ($today->format('Y-m-d') === $datetime->format('Y-m-d')) {
							$day = 'dnes';
						} elseif ($today->modify('-1 day')->format('Y-m-d') === $datetime->format('Y-m-d')) {
							$day = 'včera';
						} else {
							$day = $datetime->format('j.n.');
						}
					} catch (\Exception) {
					}

					$content = (string) ($item['content'] ?? '');
					$content = $this->insertRelativeArticle($content);
					$content = $this->insertRelativePrArticle($content);
					$content = $this->insertRelativeTriptipArticle($content);
					$content = $this->insertTwitter($content);
					$content = $this->insertFacebook($content);
					$content = $this->insertYoutube($content, $request->getSchemeAndHttpHost());
					$content = $this->insertFacebookLive($content);

					$html .= '<div class="row mt-2">';
					$html .= '<div class="col-md-2 font-weight-bold text-primary text-4-5 text-start line-height-2 ps-0">';
					$html .= $time . '<br><span class="text-1 font-weight-normal">' . $day . '</span>';
					$html .= '</div>';
					$html .= '<div class="col-md-10 content">' . $content . '</div>';
					$html .= '</div>';
				}

				$html .= '</div>';

				if ($count > $limit) {
					$pages = (int) ceil($count / $limit);
					$html .= '<ul class="pagination">';
					for ($i = 1; $i <= $pages; $i++) {
						$class = 'page-item';
						if ($page === $i) {
							$class .= ' active';
						} else {
							$class .= ' btnPaginator';
						}
						$html .= '<li class="' . $class . '" data-page="' . $i . '"><a class="page-link text-decoration-none" href="#">' . $i . '</a></li>';
					}
					$html .= '</ul>';
				}
			}

			return new JsonResponse([
				'success' => true,
				'article_id' => $articleId,
				'page' => $page,
				'limit' => $limit,
				'count' => $count,
				'refreshDate' => $refreshDate,
				'html' => $html,
			]);
		} catch (\Exception $e) {
			return new JsonResponse([
				'success' => false,
				'article_id' => $articleId,
				'page' => $page,
				'limit' => $limit,
				'count' => $count,
				'refreshDate' => $refreshDate,
				'html' => $html,
				'message' => $e->getMessage(),
			]);
		}
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

	private function redirectToNewsList(): RedirectResponse
	{
		return new RedirectResponse($this->urlGenerator->generate('news'));
	}

	private function insertRelativeArticle(string $text): string
	{
		while (($posStart = mb_strpos($text, '{{souvisejici-clanek', 0, 'UTF-8')) !== false) {
			$posEnd = mb_strpos($text, '}}', $posStart, 'UTF-8');
			if ($posEnd === false) {
				break;
			}

			$placeholder = mb_substr($text, $posStart, $posEnd - $posStart + 2);
			$relatedArticleId = mb_substr($placeholder, 22, -3);
			if (!is_numeric($relatedArticleId)) {
				$text = str_replace($placeholder, '', $text);
				continue;
			}

			$relatedArticle = $this->playkitRepository->getArticle((int) $relatedArticleId);
			if (!$relatedArticle) {
				$text = str_replace($placeholder, '', $text);
				continue;
			}

			$html  = '<div class="widget">';
			$html .= '<div class="header">Sledujte také</div>';
			$html .= '<div class="relative-article">';
			$html .= '<h3>';
			$html .= '<a href="' . $this->urlGenerator->generate('news_region_city_article', [
				'url' => $relatedArticle['region_url'],
				'city_url' => $relatedArticle['city_url'],
				'article_id' => $relatedArticle['id'],
				'article_url' => $relatedArticle['url'],
			]) . '">' . htmlspecialchars((string) $relatedArticle['title'], ENT_QUOTES, 'UTF-8') . '</a>';
			$html .= '</h3>';
			$html .= '</div>';
			$html .= '</div>';

			$text = str_replace($placeholder, $html, $text);
		}

		return $text;
	}

	private function insertRelativePrArticle(string $text): string
	{
		while (($posStart = mb_strpos($text, '{{souvisejici-pr-clanek', 0, 'UTF-8')) !== false) {
			$posEnd = mb_strpos($text, '}}', $posStart, 'UTF-8');
			if ($posEnd === false) {
				break;
			}

			$placeholder = mb_substr($text, $posStart, $posEnd - $posStart + 2);
			$relatedArticleId = mb_substr($placeholder, 25, -3);
			if (!is_numeric($relatedArticleId)) {
				$text = str_replace($placeholder, '', $text);
				continue;
			}

			$relatedArticle = $this->playkitRepository->getArticlePr((int) $relatedArticleId);
			if (!$relatedArticle) {
				$text = str_replace($placeholder, '', $text);
				continue;
			}

			$html  = '<div class="widget">';
			$html .= '<div class="header">Sledujte také</div>';
			$html .= '<div class="relative-pr-article">';
			$html .= '<h3>';
			$html .= '<a href="' . $this->urlGenerator->generate('news_pr_article', [
				'article_id' => $relatedArticle['id'],
				'article_url' => $relatedArticle['url'],
			]) . '">' . htmlspecialchars((string) $relatedArticle['title'], ENT_QUOTES, 'UTF-8') . '</a>';
			$html .= '</h3>';
			$html .= '</div>';
			$html .= '</div>';

			$text = str_replace($placeholder, $html, $text);
		}

		return $text;
	}

	private function insertRelativeTriptipArticle(string $text): string
	{
		while (($posStart = mb_strpos($text, '{{souvisejici-kam-vyrazit-clanek', 0, 'UTF-8')) !== false) {
			$posEnd = mb_strpos($text, '}}', $posStart, 'UTF-8');
			if ($posEnd === false) {
				break;
			}

			$placeholder = mb_substr($text, $posStart, $posEnd - $posStart + 2);
			$relatedArticleId = mb_substr($placeholder, 34, -3);
			if (!is_numeric($relatedArticleId)) {
				$text = str_replace($placeholder, '', $text);
				continue;
			}

			$relatedArticle = $this->playkitRepository->getTriptip((int) $relatedArticleId);
			if (!$relatedArticle || empty($relatedArticle['region_url']) || empty($relatedArticle['city_url'])) {
				$text = str_replace($placeholder, '', $text);
				continue;
			}

			$html  = '<div class="widget">';
			$html .= '<div class="header">Sledujte také</div>';
			$html .= '<div class="relative-triptip-article">';
			$html .= '<h3>';
			$html .= '<a href="' . $this->urlGenerator->generate('triptip_article', [
				'url' => $relatedArticle['region_url'],
				'city_url' => $relatedArticle['city_url'],
				'article_id' => $relatedArticle['id'],
				'article_url' => $relatedArticle['url'],
			]) . '">' . htmlspecialchars((string) $relatedArticle['title'], ENT_QUOTES, 'UTF-8') . '</a>';
			$html .= '</h3>';
			$html .= '</div>';
			$html .= '</div>';

			$text = str_replace($placeholder, $html, $text);
		}

		return $text;
	}

	private function insertOnlineNews(string $text, int $articleId): string
	{
		return str_replace(
			'{{online-reportaz}}',
			'<div class="onlineNews" data-article-id="' . $articleId . '">Online reportáž</div>',
			$text
		);
	}

	private function insertTwitter(string $text): string
	{
		$index = 0;

		return (string) preg_replace_callback('/\{\{twitter-feed-([0-9]+)\}\}/u', static function (array $matches) use (&$index): string {
			$index++;

			return '<div class="twitter-feed" id="twitter-feed-' . $index . '"></div>'
				. '<script>'
				. 'twttr.ready(function (twttr) {'
				. 'twttr.widgets.createTweet(' . json_encode($matches[1]) . ', document.getElementById(' . json_encode('twitter-feed-' . $index) . '), {theme: "light", align: "center", lang: "cs"});'
				. '});'
				. '</script>';
		}, $text);
	}

	private function insertFacebook(string $text): string
	{
		$index = 0;

		return (string) preg_replace_callback('/\{\{facebook-feed-([^}]+)\}\}/u', static function (array $matches) use (&$index): string {
			$ids = str_replace('"', '', trim($matches[1]));
			if (!str_contains($ids, '-')) {
				return '';
			}

			[$pageId, $postId] = explode('-', $ids, 2);
			if ($pageId === '' || $postId === '') {
				return '';
			}

			$index++;

			return '<div class="fb-post" data-href="https://www.facebook.com/' . htmlspecialchars($pageId, ENT_QUOTES, 'UTF-8') . '/posts/' . htmlspecialchars($postId, ENT_QUOTES, 'UTF-8') . '/" id="facebook-feed-' . $index . '"></div>';
		}, $text);
	}

	private function insertFacebookLive(string $text): string
	{
		$index = 0;

		return (string) preg_replace_callback('/\{\{facebook-live-feed-([^}]+)\}\}/u', static function (array $matches) use (&$index): string {
			$ids = str_replace('"', '', trim($matches[1]));
			if (!str_contains($ids, '-')) {
				return '';
			}

			[$pageId, $videoId] = explode('-', $ids, 2);
			if ($pageId === '' || $videoId === '') {
				return '';
			}

			$index++;

			return '<div class="fb-video" data-href="https://www.facebook.com/' . htmlspecialchars($pageId, ENT_QUOTES, 'UTF-8') . '/videos/' . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8') . '/" id="facebook-live-feed-' . $index . '"></div>';
		}, $text);
	}

	private function insertYoutube(string $text, string $schemeHost): string
	{
		$index = 0;

		return (string) preg_replace_callback('/\{\{youtube-video-([^}]+)\}\}/u', static function (array $matches) use (&$index, $schemeHost): string {
			$youtubeId = trim(str_replace('"', '', $matches[1]));
			if ($youtubeId === '') {
				return '';
			}

			$index++;

			return '<div class="responsive_player"><iframe id="youtube-player-' . $index . '" type="text/html" width="640" height="360" src="https://www.youtube.com/embed/' . htmlspecialchars($youtubeId, ENT_QUOTES, 'UTF-8') . '?enablejsapi=1&origin=' . rawurlencode($schemeHost) . '" frameborder="0" allowfullscreen></iframe></div>';
		}, $text);
	}

	private function prepareArticleHtmlForDocx(string $html): string
	{
		$html = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html) ?? $html;
		// 1) &nbsp; rozbiji XML uvnitr DOCX
		$html = str_replace('&nbsp;', ' ', $html);
		// 2) PhpWord (loadXML) potrebuje XML-friendly <br/>
		$html = preg_replace('~</br\s*>~i', '<br/>', $html) ?? $html;
		$html = preg_replace('~<br\s*>~i', '<br/>', $html) ?? $html;
		// 3) odstranit prazdne odstavce typu <p><br></p>
		$html = preg_replace('~<p\b[^>]*>\s*(<br\s*/?>|&nbsp;|\s)*\s*</p>~i', '', $html) ?? $html;

		// 4) prevod <div class="synchron"...> na <p>
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
			$node->parentNode?->replaceChild($p, $node);
		}

		$html = '';
		foreach ($dom->documentElement->childNodes as $child) {
			$html .= $dom->saveHTML($child);
		}
		// END 4)

		// 5) odstranit prazdne seznamy
		$html = preg_replace('~<ul\b[^>]*>\s*</ul>~i', '', $html) ?? $html;
		$html = preg_replace('~<ol\b[^>]*>\s*</ol>~i', '', $html) ?? $html;
		// 6) odstranit prazdne em odstavce
		$html = preg_replace('~<p\b[^>]*>\s*<em>\s*(<br\s*/?>|&nbsp;|\s|-)*\s*</em>\s*</p>~i', '', $html) ?? $html;
		// 7) odstranit <br>
		$html = preg_replace('~<br\s*/?>~i', ' ', $html) ?? $html;

		return $html;
	}

	private function getProjectDir(): string
	{
		return dirname(__DIR__, 4);
	}

	private function prepareRecommendedArticles(?array $articles): ?array
	{
		if (!$articles) {
			return null;
		}

		foreach ($articles as &$item) {
			$section = (string) ($item['section'] ?? '');
			if ($section === '1' || $section === '2') {
				$item['url'] = $this->urlGenerator->generate('news_region_city_article', [
					'url' => $item['region_url'],
					'city_url' => $item['city_url'],
					'article_id' => $item['article_id'],
					'article_url' => $this->removeAccent((string) $item['title'], '-'),
				]);
			}
			if ($section === '3') {
				$item['url'] = $this->urlGenerator->generate('triptip_article', [
					'url' => $item['region_url'],
					'city_url' => $item['city_url'],
					'article_id' => $item['article_id'],
					'article_url' => $this->removeAccent((string) $item['title'], '-'),
				]);
			}
		}
		unset($item);

		return $articles;
	}

	private function prepareRegionArticles(?array $articles): ?array
	{
		if (!$articles) {
			return null;
		}

		foreach ($articles as &$item) {
			try {
				$date = new \DateTime((string) $item['public_from']);
				$today = new \DateTime();
				if ($today->format('Y-m-d') === $date->format('Y-m-d')) {
					$item['date'] = 'Dnes';
				} elseif ($today->modify('-1 day')->format('Y-m-d') === $date->format('Y-m-d')) {
					$item['date'] = 'Včera';
				} else {
					$item['date'] = $date->format('d.m.');
				}
				$item['time'] = $date->format('H:i');
			} catch (\Exception) {
				$item['date'] = 'NaN';
				$item['time'] = 'NaN';
			}
		}
		unset($item);

		return $articles;
	}

	private function getTopicArticles(array $article): ?array
	{
		if (empty($article['topics']) || !is_array($article['topics'])) {
			return null;
		}

		$topicIds = [];
		foreach ($article['topics'] as $topic) {
			if (isset($topic['tag_id'])) {
				$topicIds[] = (int) $topic['tag_id'];
			}
		}

		$topicIds = array_values(array_unique(array_filter($topicIds)));
		if ($topicIds === []) {
			return null;
		}

		$articlesIds = $this->playkitRepository->getArticlesByTopicsAndDate($topicIds, (int) $article['id']);
		if (!$articlesIds) {
			return null;
		}

		$articlesIdsSort = array_count_values($articlesIds);
		arsort($articlesIdsSort, SORT_NUMERIC);
		$slice = array_slice($articlesIdsSort, 0, 3, true);
		$data = [];
		foreach ($slice as $articleId => $count) {
			$data[] = [
				'article_id' => (int) $articleId,
				'count' => $count,
				'impressions' => $this->newsRepository->getCountForByArticleId((int) $articleId),
			];
		}

		if ($data === []) {
			return null;
		}

		$countTags = array_column($data, 'count');
		$impressions = array_column($data, 'impressions');
		array_multisort($countTags, SORT_DESC, $impressions, SORT_DESC, $data);

		$final = [];
		foreach ($data as $value) {
			$final[] = $value['article_id'];
		}

		return $this->newsRepository->getNewArticlesTopic($final);
	}

	private function preparePrintableText(string $text): string
	{
		$text = preg_replace('/<div class="widget">.*?<\/div>/s', '', $text) ?? $text;
		$text = preg_replace('/<div class="relative-article">.*?<\/div>/s', '', $text) ?? $text;
		$text = preg_replace('/<div class="relative-pr-article">.*?<\/div>/s', '', $text) ?? $text;
		$text = preg_replace('/<div class="relative-triptip-article">.*?<\/div>/s', '', $text) ?? $text;
		$text = preg_replace('/<div class="onlineNews".*?<\/div>/s', '', $text) ?? $text;

		return $text;
	}

	private function getJobOkresCode(?string $url): ?int
	{
		return match ($url) {
			'ostrava' => 3807,
			'karvinsko' => 3803,
			'frydeckomistecko' => 3802,
			'opavsko' => 3806,
			'novojicinsko' => 3804,
			'bruntalsko' => 3801,
			default => null,
		};
	}

	private function getRegionForWeather(?string $regionUrl): ?string
	{
		return match ($regionUrl) {
			'ostrava' => 'Ostrava',
			'karvinsko' => 'Karviná',
			'frydeckomistecko' => 'Frýdek-Místek',
			'opavsko' => 'Opava',
			'novojicinsko' => 'Nový Jičín',
			'bruntalsko' => 'Bruntál',
			default => 'Ostrava',
		};
	}

	private function removeAccent(string $text, string $replace = ''): string
	{
		$transliterator = \Transliterator::createFromRules(':: Any-Latin; :: NFD; :: [:Nonspacing Mark:] Remove; :: NFC; :: [:Punctuation:] Remove; :: Lower();', \Transliterator::FORWARD);
		if ($transliterator) {
			$text = $transliterator->transliterate($text);
		}
		$text = preg_replace('/\p{C}+/u', '', $text) ?? $text;
		if ($replace) {
			$text = str_replace(' ', $replace, $text);
		}

		return $text;
	}

	public function shortlink(string $shortlink, PlaykitRepository $playkitRepository): RedirectResponse
	{
		try {
			if (!$shortlink) {
				return new RedirectResponse($this->urlGenerator->generate('news'));
			}

			$article_url = $playkitRepository->getArticleUrlByShortlink($shortlink);
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
}
