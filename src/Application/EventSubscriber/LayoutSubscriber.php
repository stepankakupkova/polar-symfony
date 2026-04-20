<?php

declare(strict_types=1);

namespace App\Application\EventSubscriber;

use App\Application\Repository\SettingRepository;
use App\Application\View\PhtmlRenderer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LayoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SettingRepository $settingRepository,
        private PhtmlRenderer $renderer,
        private string $GOOGLE_ANALYTICS_ID,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $this->renderer->addGlobal('routeName', $request->attributes->get('_route', ''));
        $this->renderer->addGlobal('footerNumbers', $this->settingRepository->fetchFooterNumbers());
        $this->renderer->addGlobal('GOOGLE_ANALYTICS_ID', $this->GOOGLE_ANALYTICS_ID);
        $this->renderer->addGlobal('locale', 'cs_CZ');
        $this->renderer->addGlobal('localeShort', 'cs');
    }
}
