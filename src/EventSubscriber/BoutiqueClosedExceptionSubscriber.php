<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\BoutiqueClosedException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final class BoutiqueClosedExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 20],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof BoutiqueClosedException) {
            return;
        }

        $content = $this->twig->render('errors/boutique_closed.html.twig', [
            'message' => $exception->getMessage(),
        ]);

        $event->setResponse(new Response($content, Response::HTTP_FORBIDDEN));
    }
}
