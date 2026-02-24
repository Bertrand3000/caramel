<?php

declare(strict_types=1);

namespace App\Tests\Controller\Dmax;

use App\Entity\Produit;
use App\Entity\Utilisateur;
use App\Enum\ProduitEtatEnum;
use App\Enum\ProduitStatutEnum;
use App\Interface\InventoryManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardControllerTest extends WebTestCase
{
    public function testIndexRequiresDmaxRole(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dmax/');

        self::assertResponseRedirects('/login');
    }

    public function testNewProduitFormSubmit(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->loginUser($this->createDmaxUser());

        $inventoryManager = $this->createMock(InventoryManagerInterface::class);
        $inventoryManager->expects(self::once())
            ->method('createProduit')
            ->willReturn(new Produit());

        static::getContainer()->set(InventoryManagerInterface::class, $inventoryManager);

        $crawler = $client->request('GET', '/dmax/produit/nouveau');
        self::assertResponseIsSuccessful();

        $imagePath = tempnam(sys_get_temp_dir(), 'img');
        self::assertNotFalse($imagePath);
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Yx2sAAAAASUVORK5CYII='));

        $form = $crawler->selectButton('Enregistrer')->form([
            'produit[libelle]' => 'Chaise test',
            'produit[etat]' => '1',
            'produit[etage]' => '1',
            'produit[porte]' => 'A12',
            'produit[tagTeletravailleur]' => '1',
            'produit[largeur]' => '45',
            'produit[hauteur]' => '80',
            'produit[profondeur]' => '40',
        ]);
        $form['produit[photoProduit]']->upload($imagePath);

        $client->submit($form);
        self::assertResponseRedirects('/dmax/');
    }

    public function testIndexLoadsInventoryForDmax(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->loginUser($this->createDmaxUser());

        $inventoryManager = $this->createMock(InventoryManagerInterface::class);
        $inventoryManager->expects(self::once())
            ->method('findAllAvailable')
            ->with(null)
            ->willReturn([]);
        static::getContainer()->set(InventoryManagerInterface::class, $inventoryManager);

        $client->request('GET', '/dmax/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Inventaire DMAX');
    }

    public function testDeleteProduitRequiresValidCsrfToken(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->loginUser($this->createDmaxUser());

        $inventoryManager = $this->createMock(InventoryManagerInterface::class);
        $inventoryManager->expects(self::never())->method('deleteProduit');
        static::getContainer()->set(InventoryManagerInterface::class, $inventoryManager);

        $client->request('POST', '/dmax/produit/42/supprimer', ['_token' => 'invalid-token']);

        self::assertResponseRedirects('/dmax/');
    }

    public function testDeleteProduitWithValidCsrfTokenCallsService(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createDmaxUser());
        $produit = $this->createProduit();

        $crawler = $client->request('GET', '/dmax/');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler
            ->filter(sprintf('form[action="/dmax/produit/%d/supprimer"] input[name="_token"]', $produit->getId()))
            ->attr('value');
        $client->request('POST', sprintf('/dmax/produit/%d/supprimer', $produit->getId()), ['_token' => $token]);

        self::assertResponseRedirects('/dmax/');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(Produit::class, $produit->getId());
        self::assertInstanceOf(Produit::class, $reloaded);
        self::assertSame(ProduitStatutEnum::REMIS, $reloaded->getStatut());
    }

    private function createDmaxUser(): Utilisateur
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('dmax-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_DMAX']);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createProduit(): Produit
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $produit = (new Produit())
            ->setLibelle(sprintf('Produit-%s', bin2hex(random_bytes(4))))
            ->setPhotoProduit('photo.jpg')
            ->setEtat(ProduitEtatEnum::BON)
            ->setEtage('1')
            ->setPorte('A01')
            ->setLargeur(120.0)
            ->setHauteur(80.0)
            ->setProfondeur(60.0)
            ->setQuantite(1)
            ->setStatut(ProduitStatutEnum::DISPONIBLE);

        $entityManager->persist($produit);
        $entityManager->flush();

        return $produit;
    }
}
