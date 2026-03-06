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
            $commande->setStatut(CommandeStatutEnum::VALIDEE);
            $this->entityManager->flush();
        }

        $contact = $commande->getCommandeContactTmp();
        $email = $contact?->getEmail();
        if ($email === null || $email === '') {
            return false;
        }

        if ($status === CommandeStatutEnum::VALIDEE) {
            $this->mailerNotifier->notifyCommandeValidee($commande);
        } elseif ($status !== CommandeStatutEnum::ANNULEE) {
            $this->mailerNotifier->notifyCommandeRefusee($commande);
        }

        $contact?->setEmail(null);
        $this->entityManager->flush();

        return true;
    }
}
