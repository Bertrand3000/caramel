<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateProduitDTO;
use App\DTO\ProduitFilterDTO;
use App\Entity\Produit;
use App\Enum\ProduitStatutEnum;
use App\Interface\InventoryManagerInterface;
use App\Interface\TaggingRuleServiceInterface;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class InventoryManager implements InventoryManagerInterface
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageProcessorService $imageProcessor,
        private readonly TaggingRuleServiceInterface $taggingRuleService,
    ) {
    }

    public function createProduit(CreateProduitDTO $dto, UploadedFile $photo, ?UploadedFile $photoInventaire): Produit
    {
        $produit = $this->hydrateProduit(new Produit(), $dto);
        $produit->setQuantite(1);
        $produit->setPhotoProduit($this->imageProcessor->processProductPhoto($photo));
        $produit->setPhotoNumeroInventaire($photoInventaire ? $this->imageProcessor->processInventoryPhoto($photoInventaire) : null);
        $this->entityManager->persist($produit);
        $this->entityManager->flush();

        return $produit;
    }

    public function updateProduit(int $id, CreateProduitDTO $dto, ?UploadedFile $photo, ?UploadedFile $photoInventaire): Produit
    {
        $produit = $this->getProduitOrFail($id);
        $this->hydrateProduit($produit, $dto);
        if ($photo !== null) {
            $produit->setPhotoProduit($this->imageProcessor->processProductPhoto($photo));
        }
        if ($photoInventaire !== null) {
            $produit->setPhotoNumeroInventaire($this->imageProcessor->processInventoryPhoto($photoInventaire));
        }
        $this->entityManager->flush();

        return $produit;
    }

    public function deleteProduit(int $id): void
    {
        $produit = $this->getProduitOrFail($id);
        $produit->setStatut(ProduitStatutEnum::REMIS);
        $this->entityManager->flush();
    }

    public function findAllAvailable(?ProduitFilterDTO $filter): array
    {
        return $this->produitRepository->findAvailableWithFilter($filter);
    }

    public function findDashboardPage(?string $etage, ?string $bureau, int $page, int $perPage = 8): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(24, $perPage));
        $total = $this->produitRepository->countAvailableDashboard($etage, $bureau);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return [
            'items' => $this->produitRepository->findAvailableDashboardPage($etage, $bureau, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'etage' => $etage,
            'bureau' => $bureau,
            'etageOptions' => $this->produitRepository->findDistinctAvailableEtages(),
            'bureauOptions' => $this->produitRepository->findDistinctAvailablePortes(),
        ];
    }

    private function getProduitOrFail(int $id): Produit
    {
        $produit = $this->produitRepository->find($id);
        if ($produit === null) {
            throw new \InvalidArgumentException('Produit introuvable.');
        }

        return $produit;
    }

    private function hydrateProduit(Produit $produit, CreateProduitDTO $dto): Produit
    {
        $tagTeletravailleur = $dto->tagTeletravailleur;
        if ($tagTeletravailleur === false) {
            $ruleTag = $this->taggingRuleService->resolveTagForLibelle($dto->libelle);
            if ($ruleTag !== null) {
                $tagTeletravailleur = $ruleTag;
            }
        }

        return $produit
            ->setNumeroInventaire($dto->numeroInventaire)
            ->setLibelle($dto->libelle)
            ->setEtat($dto->etat)
            ->setEtage($dto->etage)
            ->setPorte($dto->porte)
            ->setTagTeletravailleur($tagTeletravailleur)
            ->setLargeur($dto->largeur)
            ->setHauteur($dto->hauteur)
            ->setProfondeur($dto->profondeur);
    }
}
