<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class TwigGlobalsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Environment $twig,
        #[Autowire('%env(VAPID_PUBLIC_KEY)%')]
        private string $vapidPublicKey,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->twig->addGlobal('vapid_public_key', $this->vapidPublicKey);
    }
}
