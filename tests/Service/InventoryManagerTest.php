<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\CreateProduitDTO;
use App\Entity\Produit;
use App\Enum\ProduitEtatEnum;
use App\Interface\TaggingRuleServiceInterface;
use App\Repository\ProduitRepository;
use App\Service\ImageProcessorService;
use App\Service\InventoryManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class InventoryManagerTest extends TestCase
{
    public function testCreateProduitPersistsEntity(): void
    {
        $repo = $this->createMock(ProduitRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $img = $this->createMock(ImageProcessorService::class);
        $taggingRuleService = $this->createMock(TaggingRuleServiceInterface::class);
        $img->method('processProductPhoto')->willReturn('photo.jpg');
        $img->method('processInventoryPhoto')->willReturn('inv.png');
        $taggingRuleService->method('resolveTagForLibelle')->willReturn(null);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Produit::class));
        $em->expects(self::once())->method('flush');

        $service = new InventoryManager($repo, $em, $img, $taggingRuleService);
        $dto = new CreateProduitDTO('12345-67.89', 'Chaise', ProduitEtatEnum::BON, '1', '12', false, 45.0, 80.0, 40.0);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'x');
        $file = new UploadedFile($tmp, 'a.png', null, null, true);

        $service->createProduit($dto, $file, $file);
    }

    public function testDeleteProduitChangesStatut(): void
    {
        $produit = (new Produit())
            ->setLibelle('Table')
            ->setEtat(ProduitEtatEnum::BON)
            ->setEtage('2')
            ->setPorte('A')
            ->setLargeur(120.0)
            ->setHauteur(75.0)
            ->setProfondeur(60.0)
            ->setPhotoProduit('a.jpg');
        $repo = $this->createMock(ProduitRepository::class);
        $repo->method('find')->willReturn($produit);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $img = $this->createMock(ImageProcessorService::class);
        $taggingRuleService = $this->createMock(TaggingRuleServiceInterface::class);

        $service = new InventoryManager($repo, $em, $img, $taggingRuleService);
        $service->deleteProduit(1);

        self::assertSame('remis', $produit->getStatut()->value);
    }

    public function testCreateProduitAppliqueRegleTagQuandCheckboxNonCochee(): void
    {
        $repo = $this->createMock(ProduitRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $img = $this->createMock(ImageProcessorService::class);
        $taggingRuleService = $this->createMock(TaggingRuleServiceInterface::class);
        $img->method('processProductPhoto')->willReturn('photo.jpg');
        $img->method('processInventoryPhoto')->willReturn('inv.png');
        $taggingRuleService->method('resolveTagForLibelle')->with('Caisson mobile')->willReturn(true);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static fn (Produit $produit): bool => $produit->isTagTeletravailleur() === true,
        ));
        $em->expects(self::once())->method('flush');

        $service = new InventoryManager($repo, $em, $img, $taggingRuleService);
        $dto = new CreateProduitDTO(null, 'Caisson mobile', ProduitEtatEnum::BON, '1', '12', false, 45.0, 80.0, 40.0);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'x');
        $file = new UploadedFile($tmp, 'a.png', null, null, true);

        $service->createProduit($dto, $file, $file);
    }
}
