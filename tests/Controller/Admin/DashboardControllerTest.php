<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Utilisateur;
use App\Enum\ProfilUtilisateur;
use App\Interface\GrhImportServiceInterface;
use App\Repository\ParametreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class DashboardControllerTest extends WebTestCase
{
    public function testDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/');

        self::assertResponseRedirects('/login');
    }

    public function testAdminCanAccessDashboard(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Administration');
        self::assertStringContainsString('Paramètres boutique', (string) $client->getResponse()->getContent());
        self::assertSelectorExists('a[href="/admin/exports/ventes.csv"]');
    }

    public function testAdminCanSaveParameters(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());
        $crawler = $client->request('GET', '/admin/');

        $client->submit($crawler->selectButton('Sauvegarder')->form([
            'parametre[boutique_ouverte_agents]' => '1',
            'parametre[boutique_ouverte_teletravailleurs]' => '1',
            'parametre[boutique_ouverte_partenaires]' => '1',
            'parametre[max_produits_par_commande]' => '7',
        ]));

        self::assertResponseRedirects('/admin/');

        $parametreRepository = static::getContainer()->get(ParametreRepository::class);
        self::assertSame('1', $parametreRepository->findOneByKey('boutique_ouverte_agents')?->getValeur());
        self::assertSame('1', $parametreRepository->findOneByKey('boutique_ouverte_teletravailleurs')?->getValeur());
        self::assertSame('1', $parametreRepository->findOneByKey('boutique_ouverte_partenaires')?->getValeur());
        self::assertSame('7', $parametreRepository->findOneByKey('max_produits_par_commande')?->getValeur());
    }

    public function testAdminCanImportTeletravailleursCsv(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->loginUser($this->createAdminUser());

        $grhImportService = $this->createMock(GrhImportServiceInterface::class);
        $grhImportService->expects(self::once())
            ->method('replaceAll')
            ->with(self::callback(static fn (string $pathname): bool => is_file($pathname)))
            ->willReturn(2);
        static::getContainer()->set(GrhImportServiceInterface::class, $grhImportService);

        $crawler = $client->request('GET', '/admin/');
        self::assertResponseIsSuccessful();

        $tmpPath = tempnam(sys_get_temp_dir(), 'teletravailleurs_');
        self::assertNotFalse($tmpPath);
        $csvPath = $tmpPath.'.csv';
        rename($tmpPath, $csvPath);
        file_put_contents($csvPath, "numero_agent\n12345\n");

        $client->submit($crawler->selectButton('Importer le CSV')->form([
            'import_teletravailleurs[csvFile]' => new UploadedFile(
                $csvPath,
                'teletravailleurs.csv',
                'text/csv',
                null,
                true
            ),
        ]));

        self::assertResponseRedirects('/admin/');
    }

    private function createAdminUser(): Utilisateur
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('admin-dash-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_ADMIN'])
            ->setProfil(ProfilUtilisateur::DMAX);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
