# PHASE 3 : Front-Office Boutique & Moteur de Réservation

Date: 2026-02-23 | Projet: Caramel | Type: Architecture

---

## OBJECTIF

Développer les tunnels de commande différenciés par profil et sécuriser la réservation physique des produits.

> Mise à jour 24/03/2026 :
> cette phase est implémentée et a évolué au-delà du plan initial.
> Les points ci-dessous décrivent désormais l'architecture réellement en place.

---

## DÉCISIONS ARCHITECTURALES

### D1 — Réservation temporaire de stock
- L'entité `ReservationTemporaire` est bien utilisée pour porter la réservation par session.
- `CartManager` gère l'ajout, la suppression, l'expiration et la prolongation des réservations actives.
- Le stock fonctionnel repose sur `Produit.quantite` et sur le nettoyage des réservations temporaires avant validation.

### D2 — Prévention des race conditions (conflits de stock)
- Utilisation du **verrouillage pessimiste Doctrine** (`LOCK_PESSIMISTIC_WRITE` / `SELECT FOR UPDATE`) lors de la validation du panier.
- Transaction atomique obligatoire : vérification disponibilité + création Commande + suppression ReservationTemporaire dans une **seule transaction DB**.
- Méthode critique : `CartManager::validateCart()` — toujours encapsulée dans `$em->wrapInTransaction(...)`.

### D3 — Gestion des quotas et profils
- `ProfilUtilisateur` est défini par `dmax`, `teletravailleur`, `partenaire`, `public`.
- `QuotaCheckerService` lit prioritairement le quota sur la clé `max_produits_par_commande`, avec fallback sur l'ancienne clé `quota_articles_max`, et défaut à `3`.
- `CommandeLimitCheckerService` bloque les commandes multiples actives pour un même `numeroAgent` et un même `profilCommande`.
- `AgentEligibilityCheckerService` peut bloquer un checkout selon le référentiel `agent_eligible`.
- Les produits télétravailleurs sont filtrés via `Produit.tagTeletravailleur`.

### D4 — Gestion des créneaux de retrait
- `Creneau` utilise `capaciteMax`, `capaciteUtilisee`, `dateHeure`, `heureDebut`, `heureFin`, `type`.
- Les créneaux sont désormais généralement générés via `JourLivraison` et `CreneauGeneratorService`.
- `CreneauManager` calcule la jauge par comptage des commandes non annulées, avec cache court.
- Le checkout applique en plus les règles `reservationsOuvertes` et `exigerJourneePleine` au niveau `JourLivraison`.

---

## COMPOSANTS À CRÉER

### Entités concernées (`src/Entity`)
| Entité | Champs clés |
|---|---|
| `Panier` | id, utilisateur *(nullable)*, sessionId, dateExpiration |
| `LignePanier` | id, panier (ManyToOne), produit (ManyToOne) |
| `ReservationTemporaire` | id, produit (ManyToOne), quantite, sessionId, expireAt |
| `Commande` | utilisateur, sessionId, numeroAgent, nom, prenom, creneau, statut, profilCommande, dateValidation |
| `JourLivraison` | date, actif, reservationsOuvertes, horaires, coupure méridienne, exigerJourneePleine |
| `Creneau` | dateHeure, heureDebut, heureFin, capaciteMax, capaciteUtilisee, type, jourLivraison *(nullable)* |

### Interfaces (`src/Interface`)
- `CartManagerInterface` : `addItem`, `removeItem`, `getContents`, `validateCart`, `releaseExpired`, `extendActiveReservations`, `clear`
- `CheckoutServiceInterface` : `hasItems`, `confirmCommande`, `checkQuota`, `assignCreneau`, `modifierCreneau`, `annulerCommande`
- `SlotManagerInterface` : `getDisponibles`, `getDisponiblesPourCheckout`, `reserverCreneau`, `getJaugeDisponible`, `libererCreneau`

### Services (`src/Service`)
- `CartManager` : panier + réservations temporaires + validation
- `CheckoutService` : transaction de confirmation, règles de quota, limitation de commande, règles de journées
- `CreneauManager` : disponibilité et réservation/libération de créneau
- `QuotaCheckerService` : contrôle du nombre d'articles
- `CommandeLimitCheckerService` : unicité de commande active par agent/profil
- `ToutDoitDisparaitreService` : support du mode de complétion d'une commande existante

### Controllers (`src/Controller`)
- `ShopController` : catalogue `/boutique`
- `CartController` : panier `/panier`
- `CheckoutController` : tunnel `/commande`

### Templates (`templates/`)
- `shop/catalogue.html.twig`
- `cart/index.html.twig`
- `checkout/creneaux.html.twig`
- `checkout/confirmation.html.twig`

---

## CONTRAINTES CRITIQUES

| # | Contrainte | Impact |
|---|---|---|
| C1 | Réponse catalogue < 2s au pic d'ouverture | Mise en cache Symfony HttpCache sur `ShopController::catalogue` (TTL 30s, invalidé à chaque modif produit) |
| C2 | Race conditions interdites | `CartManager::validateCart()` dans `$em->wrapInTransaction(...)` + `LOCK_PESSIMISTIC_WRITE` sur `Produit` |
| C3 | Quota configurable | Clé `Parametre.max_produits_par_commande` (fallback historique `quota_articles_max`, défaut 3) |
| C4 | Timer serveur uniquement | `Panier.expireAt` calculé côté serveur ; le JS affiche seulement un décompte basé sur la valeur retournée |
| C5 | Profil PARTENAIRE exempté quota | Géré dans `QuotaCheckerService` |
| C6 | Chaque étape du tunnel indépendante | Catalogue, Panier et Checkout = 3 controllers découplés, pas de dépendance circulaire |
| C7 | Journée fermée non réservable | Contrôle `JourLivraison.actif` et `reservationsOuvertes` avant confirmation |
| C8 | Journée précédente prioritaire | Contrôle `exigerJourneePleine` avant réservation |

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

## ÉTAT ACTUEL

La dépendance vers la phase 4 n'est plus théorique :
- `Commande.statut` est un enum PHP `CommandeStatutEnum`
- le workflow Symfony `commande_lifecycle` est branché
- les parcours d'annulation, de validation et de remise sont déjà reliés au front-office et à la logistique
