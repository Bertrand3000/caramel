<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityControllerTest extends WebTestCase
{
    public function testLoginFailureKeepsUsernameAndShowsError(): void
    {
        $client = static::createClient();
        $login = sprintf('agent-fail-%s@test.local', bin2hex(random_bytes(4)));
        $this->createUser($login, 'Secret123!', ['ROLE_AGENT']);

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => $login,
            '_password' => 'mauvais-mot-de-passe',
        ]));

        self::assertResponseRedirects('/login');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame($login, (string) $crawler->filter('#username')->attr('value'));
        self::assertMatchesRegularExpression(
            '/Invalid credentials|Identifiants invalides/i',
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testLoginSuccessRedirectsToHomeThenShopCatalogue(): void
    {
        $client = static::createClient();
        $login = sprintf('agent-ok-%s@test.local', bin2hex(random_bytes(4)));
        $this->createUser($login, 'Secret123!', ['ROLE_AGENT']);

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => $login,
            '_password' => 'Secret123!',
        ]));

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertResponseRedirects('/boutique');
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $login, string $plainPassword, array $roles): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = (new Utilisateur())
            ->setLogin($login)
            ->setPassword('placeholder')
            ->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        $entityManager->persist($user);
        $entityManager->flush();
    }
}
