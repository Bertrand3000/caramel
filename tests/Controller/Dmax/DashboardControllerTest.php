<?php

declare(strict_types=1);

namespace App\Tests\Controller\Dmax;

use App\Entity\Produit;
use App\Entity\Utilisateur;
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
}
