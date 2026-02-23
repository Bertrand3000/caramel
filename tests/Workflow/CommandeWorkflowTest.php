<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Entity\Commande;
use App\Enum\CommandeStatutEnum;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;

final class CommandeWorkflowTest extends KernelTestCase
{
    private WorkflowInterface $workflow;

    protected function setUp(): void
    {
        self::bootKernel();

        $registry = static::getContainer()->get(Registry::class);
        $this->workflow = $registry->get(new Commande(), 'commande_lifecycle');
    }

    public function testActerRetraitTransitionIsConfigured(): void
    {
        $commandePrete = (new Commande())->setStatut(CommandeStatutEnum::PRETE);
        $commandeValidee = (new Commande())->setStatut(CommandeStatutEnum::VALIDEE);

        self::assertTrue($this->workflow->can($commandePrete, 'acter_retrait'));
        self::assertFalse($this->workflow->can($commandeValidee, 'acter_retrait'));
    }

    public function testAnnulerCommandeTransitionIsConfigured(): void
    {
        $commandePrete = (new Commande())->setStatut(CommandeStatutEnum::PRETE);
        $commandeRetiree = (new Commande())->setStatut(CommandeStatutEnum::RETIREE);
        $commandeAnnulee = (new Commande())->setStatut(CommandeStatutEnum::ANNULEE);

        self::assertTrue($this->workflow->can($commandePrete, 'annuler_commande'));
        self::assertFalse($this->workflow->can($commandeRetiree, 'annuler_commande'));
        self::assertFalse($this->workflow->can($commandeAnnulee, 'annuler_commande'));
    }
}
