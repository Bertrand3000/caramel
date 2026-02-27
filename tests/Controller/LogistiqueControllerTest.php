<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Commande;
use App\Entity\Creneau;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Entity\Utilisateur;
use App\Enum\CommandeStatutEnum;
use App\Enum\ProduitEtatEnum;
use App\Interface\DocumentPdfGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Twig\Environment;

/**
 * Tests fonctionnels du module logistique.
 *
 * Spec testée :
 * 1) /logistique/dashboard et /logistique/preparation => 404 (routes supprimées).
 * 2) Routes PDF => 302 (redirect login) pour un anonyme.
 * 3) Routes PDF => 200 + Content-Type application/pdf + corps non vide pour ROLE_DMAX.
 * 4) Bon de commande accessible pour au moins 2 statuts (validee, prete).
 * 5) Bon de préparation : présence Produit 1/2/3, champs inventaire/porte/étage.
 * 6) Bon de livraison : présence texte juridique complet.
 */
final class LogistiqueControllerTest extends WebTestCase
{
    // ── 1) Routes supprimées ─────────────────────────────────────────────────

    public function testDashboardRetourne404(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildDmaxUser());

        $client->request('GET', '/logistique/dashboard');

        self::assertResponseStatusCodeSame(404);
    }

    public function testPreparationRetourne404(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildDmaxUser());

        $client->request('GET', '/logistique/preparation');

        self::assertResponseStatusCodeSame(404);
    }

    // ── 2) Sécurité — anonyme redirigé vers /login ───────────────────────────

    public function testBonCommandePdfRefuseAnonymeParRedirection(): void
    {
        $client = static::createClient();
        $commande = $this->createCommandeWithProduit(CommandeStatutEnum::VALIDEE);

        $client->request('GET', sprintf('/logistique/commande/%d/bon-commande.pdf', $commande->getId()));

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testBonPreparationPdfRefuseAnonymeParRedirection(): void
    {
        $client = static::createClient();
        $commande = $this->createCommandeWithProduit(CommandeStatutEnum::VALIDEE);

        $client->request('GET', sprintf('/logistique/commande/%d/bon-preparation.pdf', $commande->getId()));

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testBonLivraisonPdfRefuseAnonymeParRedirection(): void
    {
        $client = static::createClient();
        $commande = $this->createCommandeWithProduit(CommandeStatutEnum::VALIDEE);

        $client->request('GET', sprintf('/logistique/commande/%d/bon-livraison.pdf', $commande->getId()));

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    // ── 3) Content-Type application/pdf + réponse non vide (ROLE_DMAX) ───────

    public function testBonCommandePdfRetourneUnPdf(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildDmaxUser());
        $commande = $this->createCommandeWithProduit(CommandeStatutEnum::VALIDEE);

        $client->request('GET', sprintf('/logistique/commande/%d/bon-commande.pdf', $commande->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertGreaterThan(0, strlen((string) $client->getResponse()->getContent()));
    }

    public function testBonPreparationPdfRetourneUnPdf(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildDmaxUser());
        $commande = $this->createCommandeWithProduit(CommandeStatutEnum::VALIDEE);

        $client->request('GET', sprintf('/logistique/commande/%d/bon-preparation.pdf', $commande->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertGreaterThan(0, strlen((string) $client->getResponse()->getContent()));
    }

    public function testBonLivraisonPdfRetourneUnPdf(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildDmaxUser());
        $commande = $this->createCommandeWithProduit(CommandeStatutEnum::VALIDEE);

        $client->request('GET', sprintf('/logistique/commande/%d/bon-livraison.pdf', $commande->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertGreaterThan(0, strlen((string) $client->getResponse()->getContent()));
    }

    // ── 4) Bon de commande accessible pour 2 statuts différents ─────────────

    public function testBonCommandePdfAccessibleStatutValidee(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildDmaxUser());
        $commande = $this->createCommandeWithProduit(CommandeStatutEnum::VALIDEE);

        $client->request('GET', sprintf('/logistique/commande/%d/bon-commande.pdf', $commande->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
    }

    public function testBonCommandePdfAccessibleStatutPrete(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildDmaxUser());
        $commande = $this->createCommandeWithProduit(CommandeStatutEnum::PRETE);

        $client->request('GET', sprintf('/logistique/commande/%d/bon-commande.pdf', $commande->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
    }

    // ── 5) Bon de préparation : libellés Produit 1/2/3 + champs détail ───────

    public function testBonPreparationHtmlContientSectionsProduit(): void
    {
        $commande = $this->createCommandeWithNProduits(3);
        $twig = static::getContainer()->get(Environment::class);

        $lignes = $commande->getLignesCommande()->toArray();
        $pages = array_chunk($lignes, 3);
        if ($pages === []) {
            $pages = [[]];
        }

        $html = $twig->render('logistique/pdf/bon_preparation.html.twig', [
            'commande' => $commande,
            'pages'    => $pages,
        ]);

        self::assertStringContainsString('Produit 1', $html);
        self::assertStringContainsString('Produit 2', $html);
        self::assertStringContainsString('Produit 3', $html);
        self::assertStringContainsString('N° inventaire', $html);
        self::assertStringContainsString('Porte / Bureau', $html);
        self::assertStringContainsString('Étage', $html);
    }

    public function testBonPreparationHtmlAvecMoinsDe3ProduitsAfficheSectionsVides(): void
    {
        $commande = $this->createCommandeWithProduit(CommandeStatutEnum::VALIDEE);
        $twig = static::getContainer()->get(Environment::class);

        $lignes = $commande->getLignesCommande()->toArray();
        $pages = array_chunk($lignes, 3);
        if ($pages === []) {
            $pages = [[]];
        }

        $html = $twig->render('logistique/pdf/bon_preparation.html.twig', [
            'commande' => $commande,
            'pages'    => $pages,
        ]);

        // 1 produit réel → 2 sections "À compléter" générées
        self::assertStringContainsString('Produit 1', $html);
        self::assertStringContainsString('Produit 2', $html);
        self::assertStringContainsString('Produit 3', $html);
        self::assertStringContainsString('À compléter', $html);
    }

    public function testBonPreparationAvecPlusDe3ProduitsGenereDeuxPages(): void
    {
        $client = static::createClient();
        $client->loginUser($this->buildDmaxUser());
        $commande = $this->createCommandeWithNProduits(5);

        $client->request('GET', sprintf('/logistique/commande/%d/bon-preparation.pdf', $commande->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertGreaterThan(1024, strlen((string) $client->getResponse()->getContent()));
    }

    // ── 6) Bon de livraison : texte juridique intégral ───────────────────────

    public function testBonLivraisonHtmlContientTexteAttestation(): void
    {
        $commande = $this->createCommandeWithProduit(CommandeStatutEnum::VALIDEE);
        $twig = static::getContainer()->get(Environment::class);

        $html = $twig->render('logistique/pdf/bon_livraison.html.twig', [
            'commande' => $commande,
        ]);

        self::assertStringContainsString('Attestation de cession à titre gratuit de mobilier', $html);
        self::assertStringContainsString('Nom / Prénom :', $html);
        self::assertStringContainsString('Service :', $html);
        self::assertStringContainsString('Fait à', $html);
        self::assertStringContainsString('Signature du bénéficiaire', $html);
        self::assertStringContainsString("Signature de l'administration", $html);
        self::assertStringContainsString("cédé en l'état", $html);
        self::assertStringContainsString('La CPAM est dégagée de toute responsabilité', $html);
        self::assertStringContainsString("Le transfert de propriété et de responsabilité", $html);
    }

    public function testBonLivraisonHtmlContientListeDesBiens(): void
    {
        $commande = $this->createCommandeWithProduit(CommandeStatutEnum::VALIDEE);
        $twig = static::getContainer()->get(Environment::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Recharger la commande depuis la BDD pour s'assurer que la collection
        // lignesCommande est bien hydratée (les lignes ont été persistées dans flush).
        $em->clear();
        /** @var Commande $commande */
        $commande = $em->find(Commande::class, $commande->getId());
        self::assertNotNull($commande);

        $html = $twig->render('logistique/pdf/bon_livraison.html.twig', [
            'commande' => $commande,
        ]);

        self::assertStringContainsString('Chaise de test', $html);
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function createCommandeWithProduit(CommandeStatutEnum $statut): Commande
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $produit = (new Produit())
            ->setLibelle('Chaise de test')
            ->setPhotoProduit('test.jpg')
            ->setEtat(ProduitEtatEnum::BON)
            ->setEtage('2')
            ->setPorte('B12')
            ->setLargeur(45.0)
            ->setHauteur(80.0)
            ->setProfondeur(40.0)
            ->setQuantite(1);

        $creneau = (new Creneau())
            ->setDateHeure(new \DateTimeImmutable('next saturday 09:00'))
            ->setHeureDebut(new \DateTime('09:00'))
            ->setHeureFin(new \DateTime('09:30'));

        $beneficiaire = (new Utilisateur())
            ->setLogin(sprintf('agent-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);

        $commande = (new Commande())
            ->setStatut($statut)
            ->setNom('Dupont')
            ->setPrenom('Jean')
            ->setNumeroAgent('12345')
            ->setDateValidation(new \DateTime())
            ->setUtilisateur($beneficiaire)
            ->setCreneau($creneau);

        $ligne = (new LigneCommande())
            ->setCommande($commande)
            ->setProduit($produit)
            ->setQuantite(1);

        $em->persist($beneficiaire);
        $em->persist($produit);
        $em->persist($creneau);
        $em->persist($commande);
        $em->persist($ligne);
        $em->flush();

        return $commande;
    }

    private function createCommandeWithNProduits(int $n): Commande
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $beneficiaire = (new Utilisateur())
            ->setLogin(sprintf('agent-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_AGENT']);

        $creneau = (new Creneau())
            ->setDateHeure(new \DateTimeImmutable('next saturday 10:00'))
            ->setHeureDebut(new \DateTime('10:00'))
            ->setHeureFin(new \DateTime('10:30'));

        $commande = (new Commande())
            ->setStatut(CommandeStatutEnum::VALIDEE)
            ->setNom('Martin')
            ->setPrenom('Sophie')
            ->setNumeroAgent('54321')
            ->setDateValidation(new \DateTime())
            ->setUtilisateur($beneficiaire)
            ->setCreneau($creneau);

        $em->persist($beneficiaire);
        $em->persist($creneau);
        $em->persist($commande);

        for ($i = 1; $i <= $n; $i++) {
            $produit = (new Produit())
                ->setLibelle(sprintf('Produit test %d', $i))
                ->setPhotoProduit('test.jpg')
                ->setEtat(ProduitEtatEnum::BON)
                ->setEtage((string) $i)
                ->setPorte(sprintf('P%02d', $i))
                ->setLargeur(50.0)
                ->setHauteur(90.0)
                ->setProfondeur(45.0)
                ->setQuantite(1)
                ->setNumeroInventaire(sprintf('INV-%04d', $i));

            $ligne = (new LigneCommande())
                ->setCommande($commande)
                ->setProduit($produit)
                ->setQuantite(1);

            $em->persist($produit);
            $em->persist($ligne);
        }

        $em->flush();

        return $commande;
    }

    private function buildDmaxUser(): Utilisateur
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new Utilisateur())
            ->setLogin(sprintf('dmax-%s@test.local', bin2hex(random_bytes(4))))
            ->setPassword('dummy')
            ->setRoles(['ROLE_DMAX']);

        $em->persist($user);
        $em->flush();

        return $user;
    }
}
