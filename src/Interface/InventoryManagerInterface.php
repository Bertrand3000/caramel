<?php

declare(strict_types=1);

namespace App\Interface;

use App\DTO\CreateProduitDTO;
use App\DTO\ProduitFilterDTO;
use App\Entity\Produit;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface InventoryManagerInterface
{
    public function createProduit(CreateProduitDTO $dto, UploadedFile $photo, ?UploadedFile $photoInventaire): Produit;

    public function updateProduit(int $id, CreateProduitDTO $dto, ?UploadedFile $photo, ?UploadedFile $photoInventaire): Produit;

    public function deleteProduit(int $id): void;

    /**
     * @return array<int, Produit>
     */
    public function findAllAvailable(?ProduitFilterDTO $filter): array;

    /**
     * @return array{
     *   items: array<int, Produit>,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   totalPages: int,
     *   etage: ?string,
     *   bureau: ?string,
     *   etageOptions: list<string>,
     *   bureauOptions: list<string>
     * }
     */
    public function findDashboardPage(?string $etage, ?string $bureau, int $page, int $perPage = 8): array;
}
