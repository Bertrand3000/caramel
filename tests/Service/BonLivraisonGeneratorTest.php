<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Service\BonLivraisonGenerator;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class BonLivraisonGeneratorTest extends TestCase
{
    public function testGeneratePrintHtmlUsesTwigTemplateAndCommandeContext(): void
    {
        $commande = new Commande();

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('logistique/bon_livraison_print.html.twig', ['commande' => $commande])
            ->willReturn('<html>ok</html>');

        $service = new BonLivraisonGenerator($twig);

        self::assertSame('<html>ok</html>', $service->generatePrintHtml($commande));
    }
}
