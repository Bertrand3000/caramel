<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Commande;
use App\Entity\Utilisateur;
use App\Enum\CommandeStatutEnum;
use App\Interface\MailerNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SuiviCommandesControllerTest extends WebTestCase
{
    public function testIndexRetourne403SansRoleAdmin(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(['ROLE_AGENT'], 'suivi-agent'));

        $client->request('GET', '/admin/suivi-commandes');

        self::assertResponseStatusCodeSame(403);
    }

    public function testValiderSansEmailGrhNeDeclenchePasEnvoiMail(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->loginUser($this->createUser(['ROLE_ADMIN'], 'suivi-admin'));
        $commande = $this->createPendingCommandeWithoutContact();

        $mailer = $this->createMock(MailerNotifierInterface::class);
        $mailer->expects(self::never())->method('notifyCommandeValidee');
        $mailer->expects(self::never())->method('notifyCommandeRefusee');
        static::getContainer()->set(MailerNotifierInterface::class, $mailer);

        $crawler = $client->request('GET', sprintf('/admin/suivi-commandes/%d', $commande->getId()));
        $token = (string) $crawler
            ->filter(sprintf('form[action="/admin/suivi-commandes/%d/valider"] input[name="_token"]', $commande->getId()))
            ->attr('value');

        $client->request('POST', sprintf('/admin/suivi-commandes/%d/valider', $commande->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/suivi-commandes');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $saved = $entityManager->find(Commande::class, $commande->getId());
        self::assertInstanceOf(Commande::class, $saved);
        self::assertSame(CommandeStatutEnum::VALIDEE, $saved->getStatut());
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(array $roles, string $prefix): Utilisateur
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new Utilisateur())
            ->setLogin(sprintf('%s-%s@test.local', $prefix, bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles($roles);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createPendingCommandeWithoutContact(): Commande
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $beneficiary = $this->createUser(['ROLE_AGENT'], 'suivi-beneficiary');
        $commande = (new Commande())
            ->setUtilisateur($beneficiary)
            ->setSessionId(sprintf('suivi-commande-%s', bin2hex(random_bytes(4))))
            ->setNumeroAgent('12345')
            ->setNom('Durand')
            ->setPrenom('Alice')
            ->setStatut(CommandeStatutEnum::EN_ATTENTE_VALIDATION);
        $entityManager->persist($commande);
        $entityManager->flush();

        return $commande;
    }
}
