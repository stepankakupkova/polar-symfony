<?php

declare(strict_types=1);

namespace App\Election\Controller\Web;

use App\Application\View\PhtmlRenderer;
use App\Election\Repository\Election2020PlaykitRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Web2020Controller
{
    private array $colors = [
        45 => '#E63812',
        63 => '#8c0000',
        12 => '#f3c308',
        82 => '#C80000',
        67 => '#37583C',
        70 => '#11457e',
        50 => '#261060',
        29 => '#004494',
        19 => '#343434',
        22 => '#feca0a',
        16 => '#2175bb',
        79 => '#27205f',
        37 => '#cccccc',
        28 => '#0083CB',
        5  => '#cccccc',
        57 => '#cccccc',
        38 => '#EC461E',
        54 => '#84c4f0',
        81 => '#cccccc',
        14 => '#343434'
    ];

    public function __construct(
        private Election2020PlaykitRepository $electionPlaykitRepository,
        private PhtmlRenderer $renderer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function index(): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('election_2020_kresla'));
    }

    public function kresla(): Response
    {
        try {
            $elections = $this->electionPlaykitRepository->fetchAllKreslaForWeb();
            $elections_results = (array) $this->electionPlaykitRepository->getResultsTotal();
            for ($i = 0, $iMax = count($elections); $i < $iMax; $i++) {
                $elections[$i]['barva'] = $this->colors[$elections[$i]['KSTRANA']] ?? '#ccc';
            }
        } catch (\Exception $ex) {
            return new RedirectResponse($this->urlGenerator->generate('election_2024'));
        }

        return new Response($this->renderer->renderWithLayout('election/web2020/kresla', [
            'elections'         => $elections,
            'elections_results' => $elections_results,
        ]));
    }
}
