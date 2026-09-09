<?php

declare(strict_types=1);

namespace App\Election\Controller\Web;

use App\Election\Repository\Election2026PlaykitRepository;
use App\Election\Repository\ElectionRepository2026;
use App\News\Repository\NewsRepository;
use App\News\Repository\PlaykitRepository;
use App\Program\Repository\ShowRepository;
use App\Application\View\PhtmlRenderer;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Web2026Controller
{
    private array $colors = [
        1    => '#FFDD00', // KDU-ČSL
        7    => '#FF5F60', // Sociální demokracie
        47   => '#CC0808', // Komunistická strana Čech a Moravy
        53   => '#2B5797', // Občanská demokratická strana
        72   => '#00FF00', // COEXISTENTIA
        83   => '#feca0a', // Moravané
        166  => '#ce0f68', // STAROSTOVÉ A NEZÁVISLÍ
        720  => '#f9ce05', // Česká pirátská strana
        768  => '#29235c', // ANO 2011
        1114 => '#EE202C', // Svoboda a přímá demokracie (SPD)
        1178 => '#009fe3', // Motoristé sobě
        1291 => '#00caf9', // Hnutí Generace
        1298 => '#7d0004', // Stačilo!
    ];

    /**
     * @param ElectionRepository2026 $electionRepository
     * @param PlaykitRepository $playkitRepository
     * @param NewsRepository $newsRepository
     * @param Election2026PlaykitRepository $electionPlaykitRepository
     * @param ShowRepository $showRepository
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(
        private ElectionRepository2026 $electionRepository,
        private PlaykitRepository $playkitRepository,
        private NewsRepository $newsRepository,
        private Election2026PlaykitRepository $electionPlaykitRepository,
        private ShowRepository $showRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function index(Request $request, PhtmlRenderer $renderer): Response
    {

        try {
            $elections = $this->electionRepository->fetchAllForWeb() ?: [];
            foreach ($elections as $key => $sel) {
                $kvros = $this->electionPlaykitRepository->findKvrosPost(
                    (int)$sel['OSTRANA'],
                    (int)$sel['VSTRANA'],
                    (int)$sel['KODZASTUP']
                );
                if ($kvros) {
                    $elections[$key]['NAZEVZAST'] = $kvros['NAZEVZAST'];
                    $elections[$key]['NAZEVCELK'] = $kvros['NAZEVCELK'];
                } else {
                    unset($elections[$key]);
                }
            }
            //var_dump($elections);

        } catch (Exception $e) {
            return new RedirectResponse($this->urlGenerator->generate('news'));
            //var_dump($e->getMessage());
        }

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2026');   
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

        return new Response($renderer->renderWithLayout('election/web2026/index', [
            'elections' => $elections,
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

    public function kandidati(Request $request, PhtmlRenderer $renderer): Response
    {

        $okres_id = (int)$request->attributes->get('okres', 8106);
        $obec_id = $request->attributes->get('obec');
        $election = $studio = null;
        $obce = [];
        $obec = null;
        $kandidatky = [];

        try {
            $obce = $this->electionPlaykitRepository->getKvrosArrayByColumn('OKRES', $okres_id) ?: [];
            if (!$obec_id && $obce) {
                // výchozí obec je Ostrava, pokud je v okrese k dispozici
                $ostrava = array_filter($obce, fn($o) => (int)$o['KODZASTUP'] === 554821);
                $obec_id = $ostrava ? 554821 : (int)$obce[0]['KODZASTUP'];
            }
            $obec_id = (int)$obec_id;
            $obec = $this->electionPlaykitRepository->getKvrosByColumn('KODZASTUP', $obec_id);
            $kandidatky = $this->electionPlaykitRepository->getKvrkByObec($obec_id) ?: [];
            if (!$obce) {
                return new RedirectResponse($this->urlGenerator->generate('election_2026'));
            }
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2026'));
            //var_dump($ex->getMessage());
        }

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2026');  
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

        return new Response($renderer->renderWithLayout('election/web2026/kandidati', [
            'okres_id' => $okres_id,
            'obec_id' => $obec_id,
            'obce' => $obce,
            'obec' => $obec,
            'kandidatky' => $kandidatky,
            'election' => $election,
            'strany' => [],
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
            $obce = $this->electionPlaykitRepository->getKvrosArrayByColumn('OKRES', $okres_id) ?: [];
            if (!$obec_id && $obce) {
                // výchozí obec je Ostrava, pokud je v okrese k dispozici
                $ostrava = array_filter($obce, fn($o) => (int)$o['KODZASTUP'] === 554821);
                $obec_id = $ostrava ? 554821 : (int)$obce[0]['KODZASTUP'];
            }
            $elections = $obec_id ? $this->electionPlaykitRepository->getResultsOkresyObceForWeb($obec_id) : null;
            $elections_results = $obec_id ? $this->electionPlaykitRepository->getResultsOkresyObceTotal($obec_id) : null;
            if ($elections_results) $elections_results = (array)$elections_results;

            for ($i=0, $iMax = count($elections ?? []); $i< $iMax; $i++) {
                $elections[$i]['barva'] = isset($this->colors[$elections[$i]['VSTRANA']]) ? $this->colors[$i]['VSTRANA'] : "#ccc";
            }
            if ($obec_id) {
                $obec = $this->electionPlaykitRepository->getKvrosByColumn('KODZASTUP', $obec_id);
            }
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2026_obec', ['okres' => $okres_id]));
            //var_dump($ex->getMessage());
        }
        //var_dump($elections_results);

        // Články k tématu volby
        $articles = null;
        $page = 1;
        try {
            $topic = $this->playkitRepository->getTopicIDByUrl('volby-2026');  
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

        $obec_title = isset($obec['NAZEVZAST']) ? $obec['NAZEVZAST'].' | ' : '';

        return new Response($renderer->renderWithLayout('election/web2026/obec', [
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

}
