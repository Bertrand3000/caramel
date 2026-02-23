# PROMPTS Phase 3 — Front-Office Boutique & Moteur de Réservation

Date: 2026-02-23 | Projet: Caramel | Type: Prompts IA Codage

> **Usage :** Chaque prompt est indépendant. Donner UN prompt à la fois à l'IA de codage.
> Toujours attendre la livraison complète avant de passer au suivant.

---

## PROMPT BLOC A — Complétion contrats & entités manquantes

```
CONTEXTE:
Projet Caramel (récupération anti-gaspi mobilier), Symfony 6.4 LTS, PHP 8.2,
Doctrine ORM, PSR-12. Repo : https://github.com/Bertrand3000/caramel
Règles absolues : declare(strict_types=1) en tête, attributs PHP 8 natifs
(#[ORM\...], #[Assert\...]), pas d'annotations YAML/XML, classes max 150 lignes.

ÉTAT:
Lire dans l'ordre chronologique :
  1. /doc/phases.md
  2. /doc/20260220_1444_DIRECTEUR_PHASE1_SOCLE_TECHNIQUE.md
  3. /doc/20260223_1345_DIRECTEUR_PHASE3_FRONTOFFICE_BOUTIQUE.md

ANALYSE (obligatoire avant tout code) :
  - Lire src/Entity/Panier.php, src/Entity/Commande.php, src/Entity/Creneau.php
  - Lire src/Entity/Produit.php (vérifier si isTeletravailleur existe)
  - Lire src/Entity/Utilisateur.php (identifier comment le profil est géré)
  - Lire src/Interface/CartManagerInterface.php
  - Lire src/Interface/SlotManagerInterface.php
  - Lire src/Interface/StockReservationInterface.php
  - Lire src/Enum/ (lister tous les fichiers existants)
  - Lire src/Entity/ (lister tous les fichiers existants)

OBJECTIF:
Créer UNIQUEMENT les éléments manquants après analyse :

1. src/Entity/ReservationTemporaire.php (si absent)
   - Champs : id (int auto), produit (ManyToOne Produit, nullable false),
     quantite (int, min 1), sessionId (string 255), expireAt (DateTimeImmutable)
   - Index Doctrine sur (sessionId) et sur (expireAt) pour les purges
   - Pas de relation inverse sur Produit

2. src/Enum/ProfilUtilisateur.php (si absent)
   - Backed enum string : DMAX = 'dmax', TELETRAVAILLEUR = 'teletravailleur',
     PARTENAIRE = 'partenaire', PUBLIC = 'public'

3. Compléter src/Interface/CartManagerInterface.php (si méthodes manquantes)
   Méthodes attendues :
   - addItem(string $sessionId, Produit $produit, int $quantite): void
   - removeItem(string $sessionId, int $produitId): void
   - getContents(string $sessionId): array
   - validateCart(string $sessionId): Commande
   - releaseExpired(): int   // retourne le nb de réservations libérées
   - clear(string $sessionId): void

4. Compléter src/Interface/SlotManagerInterface.php (si méthodes manquantes)
   Méthodes attendues :
   - getDisponibles(\DateTimeInterface $date): array
   - reserverCreneau(Creneau $creneau, Commande $commande): void
   - getJaugeDisponible(Creneau $creneau): int
   - libererCreneau(Creneau $creneau, Commande $commande): void

5. Ajouter le champ isTeletravailleur sur Produit si absent :
   - bool $isTeletravailleur = false
   - Attribut #[ORM\Column(type: 'boolean', options: ['default' => false])]

NE PAS recréer ce qui existe déjà. Ne modifier une interface que si elle
est incomplète par rapport aux méthodes listées ci-dessus.

Critères de succès :
- Chaque fichier commence par declare(strict_types=1);
- php bin/console doctrine:schema:validate passe sans erreur
- php bin/console doctrine:migrations:diff génère uniquement les tables manquantes

TESTS:
Aucun test PHPUnit pour ce bloc. Validation via commandes Doctrine uniquement.
```

---

## PROMPT BLOC B-1 — Service CartManager

```
CONTEXTE:
Projet Caramel, Symfony 6.4, PHP 8.2. Repo : https://github.com/Bertrand3000/caramel
Rules : strict_types=1, attributs PHP 8, classes max 150 lignes, zéro logique
métier dans les Controller.

ÉTAT:
Lire /doc/20260223_1345_DIRECTEUR_PHASE3_FRONTOFFICE_BOUTIQUE.md (Décisions D1 et D2).

ANALYSE (obligatoire avant tout code) :
  - Lire src/Interface/CartManagerInterface.php (contrat à implémenter)
  - Lire src/Interface/StockReservationInterface.php
  - Lire src/Entity/Panier.php, src/Entity/LignePanier.php
  - Lire src/Entity/ReservationTemporaire.php
  - Lire src/Entity/Produit.php (champ quantite)
  - Lire src/Repository/ (identifier si PanierRepository et ReservationTemporaireRepository existent)

OBJECTIF:
Créer src/Service/CartManager.php qui implémente CartManagerInterface.

Règles métier strictes :

1. addItem() :
   - Vérifier la disponibilité calculée = Produit.quantite - SUM(ReservationTemporaire.quantite actives pour ce produit)
   - Si dispo insuffisante : lancer \RuntimeException('Stock insuffisant')
   - Créer ou mettre à jour une ReservationTemporaire (sessionId, produit, quantite, expireAt = now()+30min)
   - Ajouter/mettre à jour un LignePanier lié au Panier de la session

2. validateCart() — CONTRAINTE CRITIQUE RACE CONDITION :
   - Ouvrir une transaction : $em->wrapInTransaction(function() { ... })
   - Verrouiller chaque Produit concerné avec LockMode::PESSIMISTIC_WRITE
     (Doctrine\DBAL\LockMode)
   - Vérifier à nouveau la dispo après lock (double-check)
   - Si OK : décrémenter Produit.quantite, créer Commande avec
     statut = 'en_attente_validation', supprimer les ReservationTemporaire
   - Si KO : lancer \RuntimeException('Stock épuisé lors de la validation')
   - Retourner l'entité Commande créée

3. releaseExpired() :
   - Supprimer toutes les ReservationTemporaire où expireAt < now()
   - Retourner le nombre de lignes supprimées
   - Utiliser une requête DQL DELETE directe (pas de fetch + loop)

4. getContents() :
   - Retourner un array de ['produit' => Produit, 'quantite' => int, 'expireAt' => DateTimeImmutable]
   - Calculer le expireAt minimum des ReservationTemporaire de la session

Critères de succès :
  - Pas de logique SQL brute (utiliser QueryBuilder ou DQL)
  - Injection via constructeur uniquement (EntityManagerInterface, ParametreRepository)
  - Aucune logique dans le Controller

TESTS:
  Créer tests/Service/CartManagerTest.php :
  - testAddItemDecrementsAvailableStock
  - testAddItemThrowsWhenStockInsuffisant
  - testValidateCartUsesLockPessimisticWrite (mock EntityManager, vérifier wrapInTransaction appelé)
  - testReleaseExpiredDeletesOldReservations
  Utiliser PHPUnit MockObject pour EntityManager et Repository.
```

---

## PROMPT BLOC B-2 — Service QuotaCheckerService

```
CONTEXTE:
Projet Caramel, Symfony 6.4, PHP 8.2. Repo : https://github.com/Bertrand3000/caramel

ÉTAT:
Lire /doc/20260223_1345_DIRECTEUR_PHASE3_FRONTOFFICE_BOUTIQUE.md (Décision D3).

ANALYSE (obligatoire avant tout code) :
  - Lire src/Enum/ProfilUtilisateur.php
  - Lire src/Entity/Parametre.php
  - Lire src/Repository/ParametreRepository.php
  - Lire src/Entity/Commande.php (comprendre la relation avec Utilisateur/session)
  - Lire src/Entity/Utilisateur.php

OBJECTIF:
Créer src/Service/QuotaCheckerService.php (pas d'interface, service autonome).

Règles métier :
1. Méthode principale : check(string $sessionId, ProfilUtilisateur $profil, int $quantiteDemandee): bool
   - Si $profil === ProfilUtilisateur::PARTENAIRE : retourner true (exempté)
   - Lire la valeur quota_articles_max depuis Parametre (via ParametreRepository::findOneByKey('quota_articles_max'))
   - Si clé absente : utiliser la valeur par défaut 3
   - Compter les articles déjà en commande pour cette session (toutes Commandes actives non annulées)
   - Retourner true si (articles existants + quantiteDemandee) <= quota

2. Méthode : getQuotaRestant(string $sessionId, ProfilUtilisateur $profil): int
   - Retourner le nombre d'articles restants autorisés
   - Retourner PHP_INT_MAX si PARTENAIRE

Critères de succès :
  - Valeur du quota jamais hardcodée dans le service (toujours lu depuis Parametre)
  - Injection constructeur : ParametreRepository, CommandeRepository

TESTS:
  Créer tests/Service/QuotaCheckerServiceTest.php :
  - testPartenaireEstExempte
  - testTeletravailleurRespecteLimite
  - testQuotaParDefautEstTrois (ParametreRepository retourne null)
  - testQuotaLuDepuisParametre
```

---

## PROMPT BLOC B-3 — Service CreneauManager

```
CONTEXTE:
Projet Caramel, Symfony 6.4, PHP 8.2. Repo : https://github.com/Bertrand3000/caramel

ÉTAT:
Lire /doc/20260223_1345_DIRECTEUR_PHASE3_FRONTOFFICE_BOUTIQUE.md (Décision D4).

ANALYSE (obligatoire avant tout code) :
  - Lire src/Interface/SlotManagerInterface.php (contrat à implémenter)
  - Lire src/Entity/Creneau.php (champs nbMax, dateHeure)
  - Lire src/Entity/Commande.php (relation Creneau)
  - Lire src/Repository/CreneauRepository.php si existant

OBJECTIF:
Créer src/Service/CreneauManager.php qui implémente SlotManagerInterface.

Règles métier :
1. getDisponibles(\DateTimeInterface $date): array
   - Retourner les Creneau où dateHeure.date == $date ET où getJaugeDisponible() > 0
   - Tri par dateHeure ASC

2. getJaugeDisponible(Creneau $creneau): int
   - COUNT des Commandes actives liées à ce créneau (statut != annulée)
   - Résultat mis en cache Symfony Cache (PSR-6 CacheItemPoolInterface) clé = 'creneau_jauge_'.$creneau->getId()
   - TTL cache = 30 secondes
   - Retourner $creneau->getNbMax() - $count

3. reserverCreneau(Creneau $creneau, Commande $commande): void
   - Appelé DEPUIS une transaction ouverte par CheckoutService
   - Vérifier getJaugeDisponible() > 0 après le lock (ne pas se fier au cache ici)
   - Si plein : lancer \RuntimeException('Créneau complet')
   - Assigner $commande->setCreneau($creneau)
   - Invalider le cache 'creneau_jauge_'.$creneau->getId()

4. libererCreneau(Creneau $creneau, Commande $commande): void
   - Désassocier le créneau de la commande
   - Invalider le cache correspondant

Critères de succès :
  - Injection constructeur : EntityManagerInterface, CacheItemPoolInterface
  - getJaugeDisponible utilise le cache SAUF dans reserverCreneau (COUNT direct)

TESTS:
  Créer tests/Service/CreneauManagerTest.php :
  - testGetJaugeDisponibleRetourneDiff
  - testReserverCreneauPleinsLanceException
  - testGetDisponiblesFiltreDateEtJauge
```

---

## PROMPT BLOC B-4 — Service CheckoutService (transaction atomique)

```
CONTEXTE:
Projet Caramel, Symfony 6.4, PHP 8.2. Repo : https://github.com/Bertrand3000/caramel
C'est le service le plus critique : gestion des race conditions et de la
transaction finale.

ÉTAT:
Lire /doc/20260223_1345_DIRECTEUR_PHASE3_FRONTOFFICE_BOUTIQUE.md (Décisions D2, D3, D4).

ANALYSE (obligatoire avant tout code) :
  - Lire src/Interface/CheckoutServiceInterface.php (contrat à implémenter)
  - Lire src/Service/CartManager.php (déjà implémenté au Bloc B-1)
  - Lire src/Service/QuotaCheckerService.php (déjà implémenté au Bloc B-2)
  - Lire src/Service/CreneauManager.php (déjà implémenté au Bloc B-3)
  - Lire src/Entity/Commande.php (champ statut)
  - Lire src/Enum/CommandeStatutEnum.php

OBJECTIF:
Créer src/Service/CheckoutService.php qui implémente CheckoutServiceInterface.

Méthode principale : confirmCommande(string $sessionId, Creneau $creneau, ProfilUtilisateur $profil): Commande

Logique de la transaction atomique FINALE (unique point d'entrée checkout) :

  return $this->em->wrapInTransaction(function() use ($sessionId, $creneau, $profil) {

    // 1. Vérification quota
    $panier = $this->cartManager->getContents($sessionId);
    $totalQuantite = array_sum(array_column($panier, 'quantite'));
    if (!$this->quotaChecker->check($sessionId, $profil, $totalQuantite)) {
      throw new \RuntimeException('Quota d articles dépassé');
    }

    // 2. Validation du panier (lock pessimiste + décrément stock)
    $commande = $this->cartManager->validateCart($sessionId);
    // validateCart est déjà transactionnel, mais ici on l'imbrique
    // dans la même transaction pour l'atomicité avec le créneau

    // 3. Réservation du créneau (vérif jauge directe, sans cache)
    $this->creneauManager->reserverCreneau($creneau, $commande);

    // 4. Passage statut commande à en_attente_validation
    $commande->setStatut(CommandeStatutEnum::EN_ATTENTE_VALIDATION->value);

    return $commande;
  });

Méthodes secondaires :
- checkQuota(string $sessionId, ProfilUtilisateur $profil): bool  // délègue à QuotaCheckerService
- assignCreneau(Commande $commande, Creneau $creneau): void  // délègue à CreneauManager
- annulerCommande(Commande $commande): void  // statut = annulée + libérer créneau + restituer stock

Critères de succès :
  - confirmCommande : UNE seule transaction, jamais de transaction imbriquée ouverte dans cartManager
    si on est déjà dans wrapInTransaction (adapter CartManager::validateCart pour détecter transaction active)
  - annulerCommande restitue Produit.quantite et invalide cache créneau

TESTS:
  Créer tests/Service/CheckoutServiceTest.php :
  - testConfirmCommandeCompleteParcours
  - testConfirmCommandeEchoueQuotaDepasse
  - testConfirmCommandeEchoueCreneauPlein
  - testAnnulerCommandeRestitueStock
  Simuler race condition : deux appels confirmCommande simultanés sur même produit,
  le deuxième doit lever RuntimeException.
```

---

## PROMPT BLOC C — Controllers & Templates

```
CONTEXTE:
Projet Caramel, Symfony 6.4, PHP 8.2. Repo : https://github.com/Bertrand3000/caramel
UI : Bootstrap 5 Mobile-First (cohérence avec Phase 2 DMAX). Twig.

ÉTAT:
Lire /doc/20260223_1345_DIRECTEUR_PHASE3_FRONTOFFICE_BOUTIQUE.md (Composants Controllers).

ANALYSE (obligatoire avant tout code) :
  - Lire src/Controller/ (lister tous les controllers existants pour cohérence routes)
  - Lire templates/ (structure base.html.twig, layout existant)
  - Lire src/Service/CartManager.php
  - Lire src/Service/CheckoutService.php
  - Lire src/Service/CreneauManager.php
  - Lire src/Service/QuotaCheckerService.php

OBJECTIF:
Créer les 3 controllers et leurs templates associés.

### 1. src/Controller/ShopController.php
Route préfixe : /boutique
- GET /boutique                  → catalogue (filtres: profil, catégorie, dispo)
- Injecter CartManager pour afficher les quantités disponibles calculées
- Appeler releaseExpired() en début de chaque requête sur ce controller
- Cache HTTP : #[Cache(maxage: 0, public: false)] (PAS de cache public sur ce controller)
- Template : templates/shop/catalogue.html.twig
  - Grille produits Bootstrap (cards)
  - Badge "Réservé Télétravailleur" sur les produits isTeletravailleur
  - Bouton "Ajouter au panier" POST vers CartController
  - Affichage stock disponible calculé (pas le stock brut)

### 2. src/Controller/CartController.php
Route préfixe : /panier
- GET  /panier                   → affichage panier + timer
- POST /panier/ajouter           → addItem (redirect vers /panier)
- POST /panier/retirer/{id}      → removeItem (redirect vers /panier)
- POST /panier/vider             → clear (redirect vers /boutique)
- Template : templates/cart/index.html.twig
  - Liste des articles avec quantités
  - Timer JavaScript affichant le décompte avant expiration
    (expireAt retourné par getContents(), calculé côté serveur uniquement)
  - Bouton "Valider ma commande" → GET /commande/creneaux

### 3. src/Controller/CheckoutController.php
Route préfixe : /commande
- GET  /commande/creneaux        → sélection créneau (passer la date du jour)
- POST /commande/confirmer       → confirmCommande (sessionId, creneauId)
- GET  /commande/confirmation    → page récapitulatif (commande en session)
- GET  /commande/annuler/{id}    → annulerCommande
- Template : templates/checkout/creneaux.html.twig
  - Liste des créneaux disponibles avec jauge visuelle (Bootstrap progress bar)
  - Format : "14h00 — 8/10 places" avec barre de progression
- Template : templates/checkout/confirmation.html.twig
  - Récapitulatif commande : articles, créneau, statut

Critères de succès :
  - Zéro logique métier dans les controllers (tout délégué aux services)
  - Gestion des exceptions RuntimeException avec flash message d'erreur
  - sessionId = $request->getSession()->getId()
  - Routes nommées : shop_catalogue, cart_index, cart_add, cart_remove, cart_clear,
    checkout_creneaux, checkout_confirmer, checkout_confirmation, checkout_annuler

TESTS:
  Créer tests/Controller/ShopControllerTest.php, CartControllerTest.php,
  CheckoutControllerTest.php (WebTestCase) :
  - testCatalogueRetourne200
  - testAjoutPanierRedirigeVersPanier
  - testCheckoutSansCreneauRetourneErreur
```

---

## PROMPT BLOC D — Mise à jour progression.md

```
CONTEXTE:
Projet Caramel. Repo : https://github.com/Bertrand3000/caramel

OBJECTIF:
Après validation de chaque bloc (A, B, C), mettre à jour doc/progression.md :
- Remplacer le contenu par une nouvelle section ## Bloc [X] Phase 3
- Cocher les tâches terminées avec [x]
- Conserver la progression Phase 2 existante en haut du fichier

Format attendu à la fin de Phase 3 :

# Progression

## Phase 2 — (conservé tel quel)
...

## Phase 3 — Front-Office Boutique & Moteur de Réservation

### Bloc A — Contrats & Entités
- [x] ReservationTemporaire entity
- [x] ProfilUtilisateur enum
- [x] CartManagerInterface complétée
- [x] SlotManagerInterface complétée
- [x] Produit.isTeletravailleur ajouté
- [x] Migrations générées et validées

### Bloc B — Services métier
- [x] CartManager (addItem, validateCart LOCK, releaseExpired)
- [x] QuotaCheckerService
- [x] CreneauManager (cache 30s + jauge directe)
- [x] CheckoutService (transaction atomique)

### Bloc C — Controllers & Templates
- [x] ShopController + catalogue.html.twig
- [x] CartController + cart/index.html.twig
- [x] CheckoutController + creneaux.html.twig + confirmation.html.twig

### Bloc D — Tests
- [x] CartManagerTest
- [x] QuotaCheckerServiceTest
- [x] CreneauManagerTest
- [x] CheckoutServiceTest
- [x] Tests fonctionnels Controllers
```
