<?php

declare(strict_types=1);

namespace App\Election\Controller\Web;

use App\Election\Repository\Election2025PlaykitRepository;
use App\Election\Repository\ElectionRepository2025;
use App\News\Repository\NewsRepository;
use App\News\Repository\PlaykitRepository;
use App\Program\Repository\ShowRepository;
use App\Application\View\PhtmlRenderer;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Web2025Controller
{
    private array $colors = [
        /* všechny strany
        1  => '#FF4500', // Rebelové
        2  => '#cf2e2e', // Moravské zemské hnutí
        3  => '#000000', // Jasný Signál Nezávislých
        4  => '#0066CC', // VÝZVA 2025
        5  => '#008000', // SMS – Stát Má Sloužit
        6  => '#B45F06', // SPD
        7  => '#FF4500', // ČSSD
        8  => '#0033FF', // PŘÍSAHA
        9  => '#8B0000', // Levice
        10 => '#00008B', // Česká republika na 1. místě
        11 => '#00FF40', // SPOLU
        12 => '#C0C0C0', // ŠVÝCARSKÁ DEMOKRACIE
        13 => '#800080', // Urza.cz
        14 => '#2E8B57', // Hnutí občanů a podnikatelů
        15 => '#FFA500', // Hnutí Generace
        16 => '#707070', // Piráti
        17 => '#DAA520', // Koruna Česká
        18 => '#1E90FF', // Volt Česko
        19 => '#808080', // Volte Pravý Blok
        20 => '#00BFFF', // Motoristé sobě
        21 => '#4B0082', // Balbínova poetická strana
        22 => '#261060', // ANO 2011
        23 => '#E6007E', // STAROSTOVÉ A NEZÁVISLÍ STAN
        24 => '#00CED1', // Hnutí Kruh
        25 => '#FF0000', // Stačilo!
        26 => '#FF69B4', // Voluntia
        */

        22 => '#261060', // ANO 2011
        11 => '#00FF40', // SPOLU
        6  => '#B45F06', // SPD
        23 => '#E6007E', // STAROSTOVÉ A NEZÁVISLÍ STAN
        16 => '#707070', // Piráti
        25 => '#FF0000', // Stačilo!
        20 => '#00BFFF', // Motoristé sobě
        8  => '#0033FF', // PŘÍSAHA
        7  => '#FF4500', // ČSSD
        2  => '#cf2e2e', // Moravské zemské hnutí

    ];

    /**
     * @param ElectionRepository2025 $electionRepository
     * @param PlaykitRepository $playkitRepository
     * @param NewsRepository $newsRepository
     * @param Election2025PlaykitRepository $electionPlaykitRepository
     * @param ShowRepository $showRepository
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(
        private ElectionRepository2025 $electionRepository,
        private PlaykitRepository $playkitRepository,
        private NewsRepository $newsRepository,
        private Election2025PlaykitRepository $electionPlaykitRepository,
        private ShowRepository $showRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function index(Request $request, PhtmlRenderer $renderer): Response
    {

        try {
            $elections = $this->electionRepository->fetchAllForWeb();
            $studio_array = [];
            foreach ($elections as $key => $sel) {
                $psrkl = $this->electionPlaykitRepository->findPsrklPostBy('ZKRATKAK8', $sel['title']);
                if ($psrkl) {
                    $elections[$key]['KSTRANA'] = $psrkl['KSTRANA'];
                    $elections[$key]['NAZEV_STRK'] = $psrkl['NAZEV_STRK'];
                    $studio_array[$sel['id']] = $sel['title'];
                } else {
                    $elections[$key]['KSTRANA'] = null;
                    $elections[$key]['NAZEV_STRK'] = null;
                }
            }
            //$elections = null; $studio_array = []; // dočasně
            //var_dump($elections);

            $psrkl = $this->electionPlaykitRepository->fetchPsrklAll();
            foreach ($psrkl as $key => $value) {
                if (in_array($value['ZKRATKAK8'], $studio_array)) {
                    //$volby2025[] = $elections[array_search($value['ZKRATKAK8'], $studio_array)];
                    unset($psrkl[$key]);
                }
            }
            //var_dump($psrkl);
        } catch (Exception $e) {
            return new RedirectResponse($this->urlGenerator->generate('news'));
            //var_dump($e->getMessage());
        }

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2025');
            if ($topic) {
                $articles_ids = $this->playkitRepository->getArticlesIDsByTopicID((int)$topic['id']);
                //var_dump($articles_ids);
                if ($articles_ids) {
                    $page = (int)$request->query->get('strana', 1);
                    $articles = $this->newsRepository->getPaginatorByTopic($articles_ids, $page, 20);
                    $articlesCount = $this->newsRepository->getCountByTopic($articles_ids);
                    //var_dump($articles);
                }
            }
        } catch (Exception $e) {
            //var_dump ($e->getMessage());
            return new RedirectResponse($this->urlGenerator->generate('news'));
        }
        // Pořady
        $shows = $this->showRepository->fetchAllForNews();

        // Zakázání zobrazení PR článku při prvním příchodu ze stránek seznam.cz
        $seznam = $request->query->get('utm_source');
        if ($seznam !== 'www.seznam.cz') {
            // PR články
            $pr = $this->newsRepository->getPrArticles(11);
        } else {
            $pr = null;
        }

        // Počasí
        $weather = $this->playkitRepository->getWeatherForNews('Ostrava');

        $actual_link = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        return new Response($renderer->renderWithLayout('election/web2025/index', [
            'elections' => $elections,
            'psrkl' => $psrkl,
            'articles' => $articles,
            'shows' => $shows,
            'pr' => $pr,
            'weather' => $weather,
            'weather_region' => 'Ostrava',
            // Extra pro šablonu (paginator)
            'page'  => $page,
            'total' => $articlesCount ?? 0,
            'limit' => 20,
            'currentUrl' => $actual_link,
        ]));
    }

    public function detail(Request $request, PhtmlRenderer $renderer): Response
    {
        
        $kstrana = $request->attributes->get('kstrana', null);
        if (!$kstrana) {
            $kstrana = 22;  // strana ANO
        }
        $election = $studio = null;

        try {
            if ($kstrana) {
                $election = $this->electionPlaykitRepository->getPsrkByKstrana((int)$kstrana);
                if (!$election) {
                    return new RedirectResponse($this->urlGenerator->generate('election_2025'));
                }
                try {
                    $studio = $this->electionRepository->findPostBy('title', $election['ZKRATKAK8']);
                } catch (\Exception $ex) {
                    $studio = null;
                }
                if ($studio) {
                    $studio = $this->electionRepository->fetchStudioForWeb($studio['video_id']);
                }
            }
            $strany = $this->electionPlaykitRepository->fetchPsrklAll();
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2025'));
            //var_dump($ex->getMessage());
        }

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2025');
            if ($topic) {
                $articles_ids = $this->playkitRepository->getArticlesIDsByTopicID((int)$topic['id']);
                //var_dump($articles_ids);
                if ($articles_ids) {
                    $page = (int)$request->query->get('strana', 1);
                    $articles = $this->newsRepository->getPaginatorByTopic($articles_ids, $page, 20);
                    $articlesCount = $this->newsRepository->getCountByTopic($articles_ids);
                    //var_dump($articles);
                }
            }
        } catch (Exception $e) {
            //var_dump ($e->getMessage());
            return new RedirectResponse($this->urlGenerator->generate('news'));
        }
        // Pořady
        $shows = $this->showRepository->fetchAllForNews();

        $actual_link = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        return new Response($renderer->renderWithLayout('election/web2025/detail', [
            'kstrana' => $kstrana,
            'election' => $election,
            'strany' => $strany,
            'studio' => $studio,
            'articles' => $articles,
            'shows' => $shows,
            // Extra pro šablonu (paginator)
            'page'  => $page,
            'total' => $articlesCount ?? 0,
            'limit' => 20,
            'currentUrl' => $actual_link,
        ]));
    }

    public function obec(Request $request, PhtmlRenderer $renderer): Response
    {

        $okres_id = (int)$request->attributes->get('okres', null);
        if (!$okres_id) {
            $okres_id = 8106;
        }
        $obec_id = (int)$request->attributes->get('obec', null);
        if (!$obec_id) {
            $obec_id = null;
        }
        $obec = null;

        switch ($okres_id) {
            case 8101:
                $okres_title = 'Bruntál';
                $nuts_okres = 'CZ0801';
                break;
            case 8105:
                $okres_title = 'Opava';
                $nuts_okres = 'CZ0805';
                break;
            case 8104:
                $okres_title = 'Nový Jičín';
                $nuts_okres = 'CZ0804';
                break;
            case 8106:
                $okres_title = 'Ostrava-město';
                $nuts_okres = 'CZ0806';
                break;
            case 8103:
                $okres_title = 'Karviná';
                $nuts_okres = 'CZ0803';
                break;
            case 8102:
                $okres_title = 'Frýdek-Místek';
                $nuts_okres = 'CZ0802';
                break;
            default:
                $okres_id = 8106;
                $okres_title = 'Ostrava-město';
                $nuts_okres = 'CZ0806';
                break;
        }

        try {
            $elections = $this->electionPlaykitRepository->getResultsOkresyObceForWeb($nuts_okres, $obec_id);
            $elections_results = $this->electionPlaykitRepository->getResultsOkresyObceTotal($nuts_okres, $obec_id);
            if ($elections_results) $elections_results = (array)$elections_results;

            for ($i=0, $iMax = count($elections); $i< $iMax; $i++) {
                $elections[$i]['barva'] = isset($this->colors[$elections[$i]['KSTRANA']]) ? $this->colors[$elections[$i]['KSTRANA']] : "#ccc";
            }
            $obce = $this->electionPlaykitRepository->getPscocoArrayByColumn('OKRES', $okres_id);
            if ($obec_id) {
                $obec = (array)$this->electionPlaykitRepository->getPscocoByColumn('OBEC', $obec_id);
            }
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2025_obec', ['okres' => $okres_id]));
            //var_dump($ex->getMessage());
        }
        //var_dump($elections_results);

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2025');
            if ($topic) {
                $articles_ids = $this->playkitRepository->getArticlesIDsByTopicID((int)$topic['id']);
                //var_dump($articles_ids);
                if ($articles_ids) {
                    $page = (int)$request->query->get('strana', 1);
                    $articles = $this->newsRepository->getPaginatorByTopic($articles_ids, $page, 20);
                    $articlesCount = $this->newsRepository->getCountByTopic($articles_ids);
                    //var_dump($articles);
                }
            }
        } catch (Exception $e) {
            //var_dump ($e->getMessage());
            return new RedirectResponse($this->urlGenerator->generate('news'));
        }
        // Pořady
        $shows = $this->showRepository->fetchAllForNews();

        $actual_link = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        $obec_title = isset($obec['NAZEVOBCE']) ? $obec['NAZEVOBCE'].' | ' : '';

        return new Response($renderer->renderWithLayout('election/web2025/obec', [
            'okres_id' => $okres_id,
            'obec_id' => $obec_id,
            'obce' => $obce,
            'elections' => $elections,
            'articles' => $articles,
            'shows' => $shows,
            'okres_title' => $okres_title,
            'elections_results' => $elections_results,
            'obec' => $obec,
            // Extra pro šablonu (paginator)
            'page'  => $page,
            'total' => $articlesCount ?? 0,
            'limit' => 20,
            'currentUrl' => $actual_link,
        ]));
    }

    public function poslanci(Request $request, PhtmlRenderer $renderer): Response
    {
        try {
            $elections = $this->electionPlaykitRepository->fetchAllMandatForWeb();
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2025'));
        }

        //if ($kvrzcoco['KODZASTUP'] == 554821) $kvrzcoco['NAZEVOBCE'] = 'Ostrava';
        //if ($kvrzcoco['KODZASTUP'] == 505927) $kvrzcoco['NAZEVOBCE'] = 'Opava';

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2025');
            if ($topic) {
                $articles_ids = $this->playkitRepository->getArticlesIDsByTopicID((int)$topic['id']);
                //var_dump($articles_ids);
                if ($articles_ids) {
                    $page = (int)$request->query->get('strana', 1);
                    $articles = $this->newsRepository->getPaginatorByTopic($articles_ids, $page, 20);
                    $articlesCount = $this->newsRepository->getCountByTopic($articles_ids);
                    //var_dump($articles);
                }
            }
        } catch (Exception $e) {
            //var_dump ($e->getMessage());
            return new RedirectResponse($this->urlGenerator->generate('news'));
        }
        // Pořady
        $shows = $this->showRepository->fetchAllForNews();

        $actual_link = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        return new Response($renderer->renderWithLayout('election/web2025/poslanci', [
            'elections'     =>  $elections,
            'articles' => $articles,
            'shows' => $shows,
            // Extra pro šablonu (paginator)
            'page'  => $page,
            'total' => $articlesCount ?? 0,
            'limit' => 20,
            'currentUrl' => $actual_link,
        ]));
    }

    public function kresla(Request $request, PhtmlRenderer $renderer): Response
    {

        try {
            $elections = $this->electionPlaykitRepository->fetchAllKreslaForWeb();
            $elections_results = (array)$this->electionPlaykitRepository->getResultsTotal();
            for ($i=0, $iMax = count($elections); $i< $iMax; $i++) {
                $elections[$i]['barva'] = isset($this->colors[$elections[$i]['KSTRANA']]) ? $this->colors[$elections[$i]['KSTRANA']] : "#ccc";
            }
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2025'));
            //var_dump($ex->getMessage());
        }
        //var_dump($elections);

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2025');
            if ($topic) {
                $articles_ids = $this->playkitRepository->getArticlesIDsByTopicID((int)$topic['id']);
                //var_dump($articles_ids);
                if ($articles_ids) {
                    $page = (int)$request->query->get('strana', 1);
                    $articles = $this->newsRepository->getPaginatorByTopic($articles_ids, $page, 20);
                    $articlesCount = $this->newsRepository->getCountByTopic($articles_ids);
                    //var_dump($articles);
                }
            }
        } catch (Exception $e) {
            //var_dump ($e->getMessage());
            return new RedirectResponse($this->urlGenerator->generate('news'));
        }
        // Pořady
        $shows = $this->showRepository->fetchAllForNews();

        $actual_link = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        return new Response($renderer->renderWithLayout('election/web2025/kresla', [
            'elections' => $elections,
            'elections_results' => $elections_results,
            'articles' => $articles,
            'shows' => $shows,
            // Extra pro šablonu (paginator)
            'page'  => $page,
            'total' => $articlesCount ?? 0,
            'limit' => 20,
            'currentUrl' => $actual_link,
        ]));
    }

    public function senat(Request $request, PhtmlRenderer $renderer): Response
    {

        $actual_link = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        return new Response($renderer->renderWithLayout('election/web2025/senat', [
            'currentUrl' => $actual_link,
        ]));
    }
}
