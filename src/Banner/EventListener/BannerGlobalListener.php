<?php

namespace App\Banner\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use App\Application\View\PhtmlRenderer;
use App\Banner\Repository\BannerRepository;

class BannerGlobalListener
{
    public function __construct(
        private BannerRepository $bannerRepository,
        private PhtmlRenderer $phtmlRenderer
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $this->phtmlRenderer->addGlobal('bannerLeaderboard', $this->bannerRepository->getLeaderboard());
        $this->phtmlRenderer->addGlobal('bannerMobilesticky', $this->bannerRepository->getMobilesticky());
        $this->phtmlRenderer->addGlobal('bannerRectangle', $this->bannerRepository->getRectangle());
        $this->phtmlRenderer->addGlobal('bannerSquare', $this->bannerRepository->getSquare());
        $this->phtmlRenderer->addGlobal('bannerMobilesquare1', $this->bannerRepository->getMobilesquare1());
        $this->phtmlRenderer->addGlobal('bannerMobilesquare2', $this->bannerRepository->getMobilesquare2());
    }
}
