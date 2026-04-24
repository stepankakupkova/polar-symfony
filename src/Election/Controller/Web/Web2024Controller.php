<?php
/*
 * @project polar
 * @author Rostislav Greipel
 * @link https://rogr.cz
 * @copyright (c) 2011 - 2021 ROGR All Rights Reserved
 * @license https://rogr.cz/license/eula EULA License
 */

declare(strict_types=1);

namespace App\Election\Controller\Web;

use App\Election\Repository\Election2024PlaykitRepository;
use App\Election\Repository\ElectionRepository2024;
use App\News\Repository\NewsRepository;
use App\News\Repository\PlaykitRepository;
use App\Program\Repository\ShowRepository;
use App\Application\View\PhtmlRenderer;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Web2024Controller
{
    private array $colors = [
        7  => '#2175bb', // SPD, Trikolora a PRO
        15 => '#37583C', // Demokratická strana zelených - ZA PRÁVA ZVÍŘAT
        16 => '#0033FF', // PŘÍSAHA občanské hnutí
        21 => '#343434', // Česká pirátská strana
        23 => '#343434', // REFERENDUM - Hlas Lidu
        28 => '#84c4f0', // STAROSTOVÉ A OSOBNOSTI PRO KRAJ
        39 => '#261060', // ANO 2011
        60 => '#feca0a', // Moravané
        70 => '#ff5f60', // Sociální demokracie
        76 => '#ccc',    // Švýcarská demokracie
        77 => '#8c0000', // STAČILO!
        88 => '#004494', // SPOLU MSK
        95 => '#e63812', // ČSSD A NEZÁVISLÉ OSOBNOSTI
    ];

    /**
     * @param ElectionRepository2024 $electionRepository
     * @param PlaykitRepository $playkitRepository
     * @param NewsRepository $newsRepository
     * @param Election2024PlaykitRepository $electionPlaykitRepository
     * @param ShowRepository $showRepository
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(
        private ElectionRepository2024 $electionRepository,
        private PlaykitRepository $playkitRepository,
        private NewsRepository $newsRepository,
        private Election2024PlaykitRepository $electionPlaykitRepository,
        private ShowRepository $showRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function index(Request $request, PhtmlRenderer $renderer): Response
    {

        try {
            $elections = $this->electionRepository->fetchAllForWeb();
            $studio_array = [];
            foreach ($elections as $key => $sel) {
                $kzrkl = $this->electionPlaykitRepository->findKzrklPostBy('ZKRATKAK8', $sel['title']);
                if ($kzrkl) {
                    $elections[$key]['KSTRANA'] = $kzrkl['KSTRANA'];
                    $elections[$key]['NAZEV_STRK'] = $kzrkl['NAZEV_STRK'];
                    $studio_array[$sel['id']] = $sel['title'];
                } else {
                    $elections[$key]['KSTRANA'] = null;
                    $elections[$key]['NAZEV_STRK'] = null;
                }
            }
            //$elections = null; $studio_array = [];
            //var_dump($elections);

            $kzrkl = $this->electionPlaykitRepository->fetchKzrklAll();
            foreach ($kzrkl as $key => $value) {
                if (in_array($value['ZKRATKAK8'], $studio_array)) {
                    //$volby2024[] = $elections[array_search($value['ZKRATKAK8'], $studio_array)];
                    unset($kzrkl[$key]);
                }
            }
            //var_dump($kzrkl);
        } catch (Exception $e) {
            return new RedirectResponse($this->urlGenerator->generate('news'));
            //var_dump($e->getMessage());
        }

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('krajske-volby-2024');
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

        $actual_link = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        return new Response($renderer->renderWithLayout('election/web2024/index', [
            'elections' => $elections,
            'kzrkl' => $kzrkl,
            'articles' => $articles,
            'shows' => $shows,
            'pr' => $pr,
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
            $kstrana = 39;  // strana ANO
        }
        $election = $studio = null;

        try {
            if ($kstrana) {
                $election = $this->electionPlaykitRepository->getKzrkByKstrana((int)$kstrana);
                if (!$election) {
                    return new RedirectResponse($this->urlGenerator->generate('election_2024'));
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
            $strany = $this->electionPlaykitRepository->fetchKzrklAll();
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2024'));
            //var_dump($ex->getMessage());
        }

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('krajske-volby-2024');
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

        return new Response($renderer->renderWithLayout('election/web2024/detail', [
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
            $obce = $this->electionPlaykitRepository->getKzcocoArrayByColumn('OKRES', $okres_id);
            if ($obec_id) {
                $obec = (array)$this->electionPlaykitRepository->getKzcocoByColumn('OBEC', $obec_id);
            }
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2024_obec', ['okres' => $okres_id]));
            //var_dump($ex->getMessage());
        }
        //var_dump($elections_results);

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('krajske-volby-2024');
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

        return new Response($renderer->renderWithLayout('election/web2024/obec', [
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

    public function zastupitele(Request $request, PhtmlRenderer $renderer): Response
    {
        try {
            $elections = $this->electionPlaykitRepository->fetchAllMandatForWeb();
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2024'));
        }

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('krajske-volby-2024');
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

        return new Response($renderer->renderWithLayout('election/web2024/zastupitele', [
            'elections' => $elections,
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
            return new RedirectResponse($this->urlGenerator->generate('election_2024'));
            //var_dump($ex->getMessage());
        }
        //var_dump($elections);

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('krajske-volby-2024');
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

        return new Response($renderer->renderWithLayout('election/web2024/kresla', [
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

        return new Response($renderer->renderWithLayout('election/web2024/senat', [
            'currentUrl' => $actual_link,
        ]));
    }
}
