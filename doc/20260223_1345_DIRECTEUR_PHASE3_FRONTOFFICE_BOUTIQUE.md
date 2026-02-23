# PHASE 3 : Front-Office Boutique & Moteur de Réservation

Date: 2026-02-23 | Projet: Caramel | Type: Architecture

---

## OBJECTIF

Développer les tunnels de commande différenciés par profil (filtrage télétravailleurs, quotas d'articles, exemptions pour les partenaires) et sécuriser la réservation physique des produits via un mécanisme de réservation temporaire anti-surréservation.

---

## DÉCISIONS ARCHITECTURALES

### D1 — Réservation temporaire de stock (30 min)
- Entité `ReservationTemporaire` liée à la session utilisateur avec champ `expire_at` (DateTime +30min).
- Un `EventSubscriber` (ou commande console optionnelle) libère les réservations expirées à chaque requête entrante.
- Lors de l'ajout au panier : décrémentation du **stock disponible calculé** (`produit.quantite - SUM(reservations actives)`) — le champ `Produit.quantite` reste intact jusqu'à la validation finale.

### D2 — Prévention des race conditions (conflits de stock)
- Utilisation du **verrouillage pessimiste Doctrine** (`LOCK_PESSIMISTIC_WRITE` / `SELECT FOR UPDATE`) lors de la validation du panier.
- Transaction atomique obligatoire : vérification disponibilité + création Commande + suppression ReservationTemporaire dans une **seule transaction DB**.
- Méthode critique : `CartManager::validateCart()` — toujours encapsulée dans `$em->wrapInTransaction(...)`.

### D3 — Gestion des quotas et profils
- `ProfilUtilisateur` Enum PHP 8.1 backed (string) : `DMAX`, `TELETRAVAILLEUR`, `PARTENAIRE`, `PUBLIC`.
- `QuotaCheckerService` : vérifie le nombre d'articles en commande (max configurable via `Parametre.quota_articles_max`, défaut : **3**).
- Les partenaires sont exemptés des quotas — la vérification est conditionnelle sur le profil.
- Les produits "tagués télétravailleur" sont filtrés par un champ booléen `Produit.isTeletravailleur`.

### D4 — Gestion des créneaux de retrait
- Entité `Creneau` avec `nbMax` (jauge, défaut : **10 commandes/30min**).
- Comptage temps réel des commandes validées par créneau (requête SQL `COUNT` avec mise en cache Symfony Cache courte durée — 30s max).
- `CreneauManager::reserverCreneau()` vérifie et réserve dans la même transaction que la validation panier.

---

## COMPOSANTS À CRÉER

### Entités (`src/Entity`)
| Entité | Champs clés |
|---|---|
| `Panier` | id, user (nullable), sessionId, createdAt, expireAt, statut |
| `PanierItem` | id, panier (ManyToOne), produit (ManyToOne), quantite |
| `ReservationTemporaire` | id, produit (ManyToOne), quantite, sessionId, expireAt |
| `Commande` | id, panier (OneToOne), creneau (ManyToOne), statut, createdAt |
| `Creneau` | id, dateHeure (DateTime), nbMax (int), label (string) |

### Interfaces (`src/Interface`)
- `CartManagerInterface` : `addItem`, `removeItem`, `getContents`, `validateCart`, `releaseExpired`, `clear`
- `CheckoutServiceInterface` : `checkQuota`, `assignCreneau`, `confirmCommande`, `annulerCommande`
- `CreneauManagerInterface` : `getDisponibles`, `reserverCreneau`, `getJaugeDisponible`, `libererCreneau`

### Services (`src/Service`)
- `CartManager` → implémente `CartManagerInterface` (gestion panier + reservations temporaires)
- `CheckoutService` → implémente `CheckoutServiceInterface` (tunnel validation, transaction atomique)
- `CreneauManager` → implémente `CreneauManagerInterface`
- `QuotaCheckerService` → vérifie les quotas selon profil (injecte `ParametreRepository`)

### Controllers (`src/Controller`)
- `ShopController` : catalogue vitrine + filtres par profil
- `CartController` : add / remove / view panier + affichage timer
- `CheckoutController` : sélection créneau + confirmation commande

### Templates (`templates/`)
- `shop/catalogue.html.twig` : Vitrine produits avec filtres (profil, catégorie, dispo)
- `cart/index.html.twig` : Résumé panier + timer JS d'expiration (affichage seulement)
- `checkout/creneaux.html.twig` : Sélection créneau avec jauge visuelle (% remplissage)
- `checkout/confirmation.html.twig` : Récapitulatif commande validée

---

## CONTRAINTES CRITIQUES

| # | Contrainte | Impact |
|---|---|---|
| C1 | Réponse catalogue < 2s au pic d'ouverture | Mise en cache Symfony HttpCache sur `ShopController::catalogue` (TTL 30s, invalidé à chaque modif produit) |
| C2 | Race conditions interdites | `CartManager::validateCart()` dans `$em->wrapInTransaction(...)` + `LOCK_PESSIMISTIC_WRITE` sur `Produit` |
| C3 | Quota configurable | Clé `Parametre.quota_articles_max` (défaut 3), lu depuis la BDD via `ParametreRepository` |
| C4 | Timer serveur uniquement | `Panier.expireAt` calculé côté serveur ; le JS affiche seulement un décompte basé sur la valeur retournée |
| C5 | Profil PARTENAIRE exempté quota | Condition dans `QuotaCheckerService::check(ProfilUtilisateur $profil)` |
| C6 | Chaque étape du tunnel indépendante | Catalogue, Panier et Checkout = 3 controllers découplés, pas de dépendance circulaire |

---

## ORDRE D'IMPLÉMENTATION — 4 BLOCS

### Bloc A — Contrats & Entités *(Priorité 1)*
1. Créer `ProfilUtilisateur` Enum (`src/Enum/ProfilUtilisateur.php`)
2. Créer les 5 entités + annotations Doctrine
3. Générer et valider les migrations (`doctrine:migrations:diff` + `doctrine:migrations:migrate`)
4. Créer les 3 interfaces (`CartManagerInterface`, `CheckoutServiceInterface`, `CreneauManagerInterface`)

### Bloc B — Services métier *(Priorité 2)*
1. Implémenter `CartManager` (add/remove + gestion `ReservationTemporaire`)
2. Implémenter `QuotaCheckerService`
3. Implémenter `CreneauManager`
4. Implémenter `CheckoutService` (transaction atomique C2)

### Bloc C — Controllers & Templates *(Priorité 3)*
1. `ShopController` + `catalogue.html.twig` (filtres profil + dispo)
2. `CartController` + `cart/index.html.twig` (timer + liste articles)
3. `CheckoutController` + templates créneau + confirmation

### Bloc D — Tests *(Priorité 4)*
1. Tests unitaires `CartManager` (mock EntityManager, mock Repository)
2. Tests unitaires `CheckoutService` (simulation race condition : 2 appels simultanés)
3. Tests fonctionnels `ShopController`, `CartController`, `CheckoutController` (WebTestCase)

---

## POINT D'ATTENTION — DÉPENDANCE PHASE 4

La structure de l'entité `Commande` doit anticiper le cycle de vie géré par le composant Symfony Workflow (Phase 4). Le champ `statut` doit être une **string simple** dans un premier temps, mais sa valeur initiale doit être `en_attente_validation` pour être compatible sans migration supplémentaire lors de l'intégration du Workflow en Phase 4.
