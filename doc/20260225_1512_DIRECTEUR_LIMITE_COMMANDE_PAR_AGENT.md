# Plan Architecture — Limitation Commande Unique par Agent par Période

Date: 2026-02-25 | Rôle: DIRECTEUR | Type: Règle métier / Sécurité commande

---

## OBJECTIF

Empêcher un même agent (identifié par `numeroAgent`) de passer plusieurs commandes actives pour un même profil de commande, tout en autorisant un agent télétravailleur à passer une commande par période d'ouverture distincte (ouverture télétravailleurs + ouverture agents).

**Règle synthétique :**
> Un `numeroAgent` ne peut avoir qu'une seule commande non-annulée par type de période (`TELETRAVAILLEUR` ou `AGENT`). Un télétravailleur peut donc cumuler exactement 2 commandes : une de profil `TELETRAVAILLEUR` et une de profil `AGENT`.

---

## CONTEXTE TECHNIQUE EXISTANT

### Identification des agents

Le compte `Utilisateur` est partagé par tous les agents. La clé d'identification individuelle est le `numeroAgent` (5 chiffres), fourni manuellement au checkout.

La résolution du profil côté contrôleur (`CheckoutController::resolveProfilUtilisateur()`) produit :

| Rôle Symfony | ProfilUtilisateur | CommandeProfilEnum (stocké en BDD) |
|---|---|---|
| `ROLE_TELETRAVAILLEUR` | `TELETRAVAILLEUR` | `TELETRAVAILLEUR` |
| `ROLE_PARTENAIRE` | `PARTENAIRE` | `PARTENAIRE` |
| `ROLE_DMAX` | `DMAX` | `DMAX` |
| *(défaut, agent normal)* | `PUBLIC` | `AGENT` |

### État actuel du contrôle

Le `QuotaCheckerService` vérifie uniquement le **nombre d'articles** (non le nombre de commandes). Aucun mécanisme n'empêche actuellement un agent de passer plusieurs commandes successives tant que le quota d'articles n'est pas atteint.

---

## DÉCISIONS ARCHITECTURALES

### D1 — Clé d'unicité : `numeroAgent` + `CommandeProfilEnum`

Le `numeroAgent` est l'identifiant universel. La vérification se fait sur le couple **(`numeroAgent`, `profilCommande`)** déjà stocké dans l'entité `Commande`. Ce couplage autorise naturellement :
- 1 commande avec `profilCommande = TELETRAVAILLEUR`
- 1 commande avec `profilCommande = AGENT`

pour le même `numeroAgent`, implémentant la règle métier sans logique temporelle complexe ni dépendance aux paramètres d'ouverture boutique.

### D2 — Statuts bloquants : tout sauf `ANNULEE`

Une commande `RETIREE` (livrée/récupérée) **ne libère pas** le droit de commander. Seul le statut `ANNULEE` autorise un agent à repasser une commande du même profil.

Statuts bloquants : `EN_ATTENTE_VALIDATION`, `VALIDEE`, `A_PREPARER`, `EN_PREPARATION`, `PRETE`, `RETIREE`
Statut non-bloquant : `ANNULEE` uniquement

La requête filtre donc `statut != ANNULEE`.

### D3 — Nouveau service dédié `CommandeLimitCheckerService`

Séparation claire des responsabilités (SRP) :
- `QuotaCheckerService` → contrôle du **nombre d'articles** par commande
- `CommandeLimitCheckerService` *(nouveau)* → contrôle du **nombre de commandes** par agent

### D4 — Vérification dans la transaction existante

L'appel à `assertPeutCommander()` s'insère dans `wrapInTransaction()` de `CheckoutService::confirmCommande()`, après la vérification du panier vide et **avant** `validateCart()`. Cela garantit l'atomicité et protège contre les double-clics simultanés (protection applicative jugée suffisante).

### D5 — Exception dédiée `CommandeDejaExistanteException`

Cohérent avec le pattern existant (`JourLivraisonNonPleinException`, `BoutiqueClosedException`). Étend `\RuntimeException` → compatible immédiatement avec le `catch (\RuntimeException $exception)` dans `CheckoutController` sans modification obligatoire du contrôleur.

---

## COMPOSANTS

### Nouveaux fichiers

#### `src/Exception/CommandeDejaExistanteException.php`

Exception métier étendant `\RuntimeException`. Porte un message utilisateur contextuel.

```php
class CommandeDejaExistanteException extends \RuntimeException {}
```

#### `src/Service/CommandeLimitCheckerService.php`

Service applicatif avec une méthode publique principale :

```
assertPeutCommander(string $numeroAgent, ProfilUtilisateur $profil): void
```

**Logique interne :**
- `PARTENAIRE` → retour immédiat sans vérification (aucune restriction)
- `DMAX` → retour immédiat sans vérification (ne commande pas)
- `TELETRAVAILLEUR` → vérifie `hasCommandeActive(numeroAgent, CommandeProfilEnum::TELETRAVAILLEUR)`
- `PUBLIC` → vérifie `hasCommandeActive(numeroAgent, CommandeProfilEnum::AGENT)`

Dépendance unique : `CommandeRepository`

### Fichiers modifiés

#### `src/Repository/CommandeRepository.php`

Ajout d'une méthode :

```
hasCommandeActiveForNumeroAgentEtProfil(
    string $numeroAgent,
    CommandeProfilEnum $profil
): bool
```

Requête DQL :
```sql
SELECT COUNT(c.id) > 0
FROM commandes c
WHERE c.numeroAgent = :numeroAgent
  AND c.profilCommande = :profil
  AND c.statut != :annulee
```

#### `src/Service/CheckoutService.php`

- Injection de `CommandeLimitCheckerService` dans le constructeur
- Appel de `$this->commandeLimitChecker->assertPeutCommander($numeroAgent, $profil)` dans `confirmCommande()`, après le check panier vide, avant `$this->quotaChecker->check()`

#### `src/Controller/CheckoutController.php` *(optionnel — UX)*

Ajout d'un `catch (CommandeDejaExistanteException $exception)` **avant** le `catch (\RuntimeException $exception)` existant, pour rediriger l'agent vers sa commande existante plutôt que vers la page des créneaux.

---

## FLUX DÉCISIONNEL `confirmCommande()`

```
confirmCommande(sessionId, creneau, profil, utilisateur, numeroAgent, ...)
│
├─ panier vide ? ──────────────────────────────────── RuntimeException 'Panier vide'
│
├─ [NOUVEAU] assertPeutCommander(numeroAgent, profil)
│    ├─ PARTENAIRE → skip (sans restriction)
│    ├─ DMAX       → skip (ne commande pas)
│    ├─ TELETRAVAILLEUR → hasCommandeActive(numeroAgent, TELETRAVAILLEUR) ?
│    │    └─ OUI → CommandeDejaExistanteException
│    └─ PUBLIC     → hasCommandeActive(numeroAgent, AGENT) ?
│         └─ OUI → CommandeDejaExistanteException
│
├─ quota articles dépassé ? ───────────────────────── RuntimeException 'Quota dépassé'
│
├─ assertJourneePleineRule(creneau) ───────────────── JourLivraisonNonPleinException
│
└─ validateCart() + reserverCreneau() ─────────────── Commande créée ✓
```

---

## SCHÉMA DES DÉPENDANCES

```
CheckoutController
      │
      ▼
CheckoutService ──────── [NOUVEAU] CommandeLimitCheckerService
      │                                       │
      │                              CommandeRepository
      │                              hasCommandeActiveForNumeroAgentEtProfil()
      │
      ├── QuotaCheckerService      (inchangé)
      ├── CartManager              (inchangé)
      └── CreneauManager           (inchangé)
```

---

## CONTRAINTES ET POINTS D'ATTENTION

### Décision actée — Quota par commande (doublé si 2 commandes possibles)

Le quota d'articles est calculé par commande, donc par couple (`numeroAgent`, `profilCommande`).
Un télétravailleur a un quota effectif doublé (quota indépendant sur chaque commande).
Implémentation : `countArticlesActifsForNumeroAgentEtProfil()` filtre par `profilCommande`.

### Compatibilité ascendante

- Aucun changement de schéma BDD requis
- `CheckoutServiceInterface` non modifiée
- Les agents PARTENAIRE non impactés
- Les commandes existantes en BDD non touchées

---

## TESTS À PRÉVOIR

| Scénario | Résultat attendu |
|---|---|
| Agent passe 1ère commande | ✓ Autorisé |
| Agent tente une 2ème commande (statut PRETE) | ✗ `CommandeDejaExistanteException` |
| Agent annule sa commande et retente | ✓ Autorisé (ANNULEE ne bloque pas) |
| Agent avec commande RETIREE retente | ✗ Bloqué (RETIREE est bloquant) |
| Télétravailleur commande en période TELETRAVAILLEUR | ✓ Autorisé |
| Télétravailleur commande en période AGENT | ✓ Autorisé (profil différent = AGENT) |
| Télétravailleur tente 2ème commande TELETRAVAILLEUR | ✗ Bloqué |
| Télétravailleur tente 2ème commande AGENT | ✗ Bloqué |
| PARTENAIRE commande plusieurs fois | ✓ Toujours autorisé |

---

## RÉSUMÉ DES FICHIERS

| Fichier | Action | Priorité |
|---|---|---|
| `src/Exception/CommandeDejaExistanteException.php` | CRÉER | P0 |
| `src/Service/CommandeLimitCheckerService.php` | CRÉER | P0 |
| `src/Repository/CommandeRepository.php` | MODIFIER (+2 méthodes) | P0 |
| `src/Service/QuotaCheckerService.php` | MODIFIER | P0 |
| `src/Service/CheckoutService.php` | MODIFIER (+1 injection, +1 appel) | P0 |
| `src/Controller/CheckoutController.php` | MODIFIER (+1 catch ciblé) | P1 (UX) |
