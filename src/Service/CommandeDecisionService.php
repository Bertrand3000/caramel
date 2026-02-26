<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Enum\CommandeStatutEnum;
use App\Interface\CheckoutServiceInterface;
use App\Interface\CommandeDecisionServiceInterface;
use App\Interface\MailerNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;

final class CommandeDecisionService implements CommandeDecisionServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerNotifierInterface $mailerNotifier,
        private readonly CheckoutServiceInterface $checkoutService,
    ) {
    }

    public function apply(Commande $commande, CommandeStatutEnum $status): bool
    {
        if ($status === CommandeStatutEnum::ANNULEE) {
            $this->checkoutService->annulerCommande($commande);
        } else {
            $commande->setStatut($status);
            $this->entityManager->flush();
        }

        $email = $commande->getCommandeContactTmp()?->getEmail();
        if ($email === null || $email === '') {
            return false;
        }

        if ($status === CommandeStatutEnum::VALIDEE) {
            $this->mailerNotifier->notifyCommandeValidee($commande);
        } elseif ($status !== CommandeStatutEnum::ANNULEE) {
            $this->mailerNotifier->notifyCommandeRefusee($commande);
        }

        return true;
    }
}
