<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;

final class RgpdPurgeService implements PurgeServiceInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function anonymizeCommande(Commande $commande): void
    {
        $commandeContactTmp = $commande->getCommandeContactTmp();

        if ($commandeContactTmp === null) {
            return;
        }

        $commande->setCommandeContactTmp(null);
        $this->entityManager->remove($commandeContactTmp);
        $this->entityManager->flush();
    }
}
