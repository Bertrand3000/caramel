# Phase 1 — Socle Technique & Fondations Architecturales

**Date :** 2026-02-20 14:44
**Rôle :** @Directeur
**Projet :** CARAMEL — Application Web de don de mobilier CPAM
**Phase :** 1 / 4
**Durée estimée :** ≤ 1 jour de développement
**Criticité :** ⚠️ BLOQUANTE — Toutes les phases suivantes en dépendent

---

## OBJECTIF

Mettre en place le squelette Symfony 6.4 / PHP 8.2, le modèle de données complet issu du CdC §14, le système d'authentification multi-profils et les **contrats d'interfaces** de tous les services métier. Cette phase produit une application vide mais entièrement câblée, déployable sur le VPS dès sa conclusion.

**Critère de succès unique :** Un `symfony server:start` fonctionnel sur le VPS avec toutes les entités migrées en base, tous les profils de sécurité actifs et les interfaces de service créées — sans aucune logique métier implémentée.

> Mise à jour 24/03/2026 :
> ce document reste la référence de cadrage Phase 1, mais le dépôt a largement dépassé ce stade.
> Les sections ci-dessous ont été réalignées lorsque le code a divergé de manière significative.

---

## DÉCISIONS

### D1 — Stack technique fixée

| Paramètre | Valeur | Justification |
|---|---|---|
| **PHP** | 8.2 | Classes `readonly`, types DNF — réduction des erreurs IA |
| **Symfony** | 6.4 LTS | Support garanti jusqu'en 2027, attributs natifs PHP 8, Workflow component intégré |
| **ORM** | Doctrine 2.x (bundle Symfony) | Mapping via attributs PHP 8, migrations versionnées |
| **Base de données** | MySQL / MariaDB | Disponible VPS ~5€/mois, transactionnel pour les race conditions Phase 3 |
| **Auth** | `symfony/security-bundle` + `LoginFormAuthenticator` | Multi-profils via firewall unique, rôles hiérarchiques |

### D2 — Structure de l'application en 3 zones fonctionnelles

Reflet direct des trois acteurs principaux du CdC :

```
src/
├── Controller/
│   ├── Admin/              # Gestion boutique, comptes, paramètres
│   ├── Dmax/               # Back-office inventaire + tableau remise
│   └── Shop/               # Répertoire prévu côté front
├── Entity/                 # 11 entités Doctrine
├── Repository/             # Repositories Doctrine (1 par entité)
├── Service/                # Implémentations des interfaces
├── Interface/              # Contrats de service (créés en Phase 1, implémentés en Phase 2/3/4)
├── DTO/                    # Data Transfer Objects (readonly class PHP 8.2)
├── EventSubscriber/        # Hooks Doctrine/Symfony (purge RGPD, expiration panier)
└── Command/                # Commandes console (cron purge nocturne, exports)
```

**État code**
- Les contrôleurs front réellement utilisés sont aujourd'hui majoritairement dans `src/Controller/` à la racine: `ShopController`, `CartController`, `CheckoutController`, `LogistiqueController`, `SecurityController`, `HomeController`.
- Les sous-répertoires `Admin/` et `Dmax/` sont bien utilisés ; `Shop/` existe mais le front principal n'y a pas été déplacé.

### D3 — Sécurité multi-profils : un seul firewall, rôles distincts

Un seul firewall `main` avec `LoginFormAuthenticator`. Hiérarchie des rôles :

```
ROLE_ADMIN > ROLE_DMAX > ROLE_AGENT_RECUPERATION
ROLE_ADMIN > ROLE_PARTENAIRE
ROLE_ADMIN > ROLE_AGENT
ROLE_ADMIN > ROLE_TELETRAVAILLEUR
```

> ⚠️ **Décision critique :** `ROLE_AGENT` et `ROLE_TELETRAVAILLEUR` sont distincts et non hiérarchiques. Le code de sécurité actuel utilise bien `ROLE_TELETRAVAILLEUR` sans accent.

### D4 — Modèle de données figé en Phase 1, migration unique

Le schéma n'est plus figé à 11 entités ni à une migration unique. Le dépôt a évolué avec plusieurs ajouts structurants (`JourLivraison`, `ReservationTemporaire`, `AgentEligible`, `RegleTagger`, enrichissements produit et logistique). Ne plus lire cette section comme une contrainte encore valable.

### D5 — Workflow Symfony natif pour le cycle de vie des commandes

Le composant `symfony/workflow` gère les transitions de statut de `Commande`. Les listeners de transition déclenchent automatiquement la purge RGPD et la libération des ressources. Ce choix évite toute logique de statut dispersée dans le code.

---

## COMPOSANTS

### C1 — Entités Doctrine présentes dans le dépôt

| Entité | Table BDD | Points d'attention |
|---|---|---|
Le dépôt contient actuellement 16 entités principales :

| Entité | Table BDD | Note |
|---|---|---|
| `Produit` | `produits` | Produit enrichi : quantité, description, VNC, données Copernic |
| `Utilisateur` | `utilisateurs` | `roles` JSON + `profil` enum |
| `Partenaire` | `partenaires` | OneToOne vers `Utilisateur` |
| `TeletravailleurListe` | `teletravailleurs_liste` | Référentiel télétravailleurs |
| `AgentEligible` | `agent_eligible` | Référentiel agents autorisés |
| `Commande` | `commandes` | Statut piloté par workflow |
| `LigneCommande` | `lignes_commande` | Lignes de commande |
| `Panier` | `paniers` | Panier rattaché à session et/ou utilisateur |
| `LignePanier` | `lignes_panier` | Lignes de panier |
| `ReservationTemporaire` | `reservations_temporaires` | Réservation de stock temporaire |
| `JourLivraison` | `jours_livraison` | Gabarit de journée avec ouverture/réservations |
| `Creneau` | `creneaux` | Créneau généré, rattachable à un `JourLivraison` |
| `BonLivraison` | `bons_livraison` | Bon de livraison |
| `CommandeContactTmp` | `commande_contacts_tmp` | Contacts GRH jetables |
| `Parametre` | `parametres` | Paramètres applicatifs clé/valeur |
| `RegleTagger` | `regles_tagger` | Taggage automatique télétravailleur |

### C2 — Interfaces de service présentes

Le dépôt ne se limite plus aux 11 contrats initiaux. Il contient aujourd'hui un ensemble plus large d'interfaces couvrant :
- inventaire, checkout, panier et créneaux
- workflow de commande, décisions admin et contrôles d'éligibilité
- import GRH CSV/XLSX
- génération PDF, bons et exports
- logistique, dashboard admin et contrôles d'ouverture boutique

```php
InventoryManagerInterface
CartManagerInterface
CheckoutServiceInterface
SlotManagerInterface
GrhImportServiceInterface
CommandeGrhImportServiceInterface
MailerNotifierInterface
DocumentPdfGeneratorInterface
LogistiqueServiceInterface
ExportServiceInterface
PurgeServiceInterface
```

### C3 — DTOs `readonly class`

Le dépôt contient actuellement les DTOs `readonly` suivants :

```php
readonly class CreateProduitDTO
readonly class CartAddItemDTO
readonly class CheckoutAgentDTO
readonly class CheckoutPartenaireDTO
readonly class GrhImportRowDTO
readonly class ProduitFilterDTO
readonly class CommandeGrhImportResult
readonly class GenerationResult
```

### C4 — Configuration sécurité (`security.yaml`)

Accès contrôlé par préfixe de chemin :

| Préfixe | Rôles autorisés |
|---|---|
| `/admin/**` | `ROLE_ADMIN` uniquement |
| `/dmax/**` | `ROLE_DMAX`, `ROLE_AGENT_RECUPERATION` |
| `/boutique/**` | `ROLE_ADMIN`, `ROLE_AGENT`, `ROLE_TELETRAVAILLEUR`, `ROLE_PARTENAIRE` |
| `/panier/**` | `ROLE_ADMIN`, `ROLE_AGENT`, `ROLE_TELETRAVAILLEUR`, `ROLE_PARTENAIRE` |
| `/commande/**` | `ROLE_ADMIN`, `ROLE_AGENT`, `ROLE_TELETRAVAILLEUR`, `ROLE_PARTENAIRE` |
| `/logistique/**` | `ROLE_DMAX`, `ROLE_AGENT_RECUPERATION` |
| `/login` | Public |

### C5 — Workflow Symfony pour `Commande`

```
[en_attente_validation]
        │
        ├─ valider ─────────────────────► [validee]
        │                                    │
        │                             demarrer_preparation
        │                                    │
        │                              [en_preparation]
        │                                    │
        │                           terminer_preparation
        │                                    │
        │                                  [prete]
        │                                    │
        │                               acter_retrait
        │                                    │
        │                                 [retiree]
        │
        └─ annuler_commande ────────────► [annulee]
```

**Effets observés dans le code :**
1. Email de validation sur `valider`
2. Email de refus/annulation sur `annuler_commande`
3. Purge/anonymisation RGPD sur `acter_retrait` et `annuler_commande`
4. Libération du créneau et remise en stock gérées côté service d'annulation

---

## CONTRAINTES

| # | Contrainte | Niveau | Vérification |
|---|---|---|---|
| **CT1** | `declare(strict_types=1)` en tête de TOUS les fichiers PHP | 🔴 Bloquant | Reviewable par @DevSenior |
| **CT2** | Logique métier interdite dans les `Controller` | 🔴 Bloquant | Toute logique → `Service` implémentant une `Interface` |
| **CT3** | Classes ≤ 150 lignes | 🟠 Majeur | Limite contexte IA — découper si dépassé |
| **CT4** | Une migration Doctrine unique pour toute la Phase 1 | 🟠 Majeur | Facilite rollback VPS |
| **CT5** | VPS provisionné (PHP 8.2, MySQL, Nginx, Composer) avant fin Phase 1 | 🔴 Bloquant | Prérequis déploiement |
| **CT6** | Interfaces créées AVANT toute implémentation | 🔴 Bloquant | Les agents IA reçoivent l'interface comme contexte |
| **CT7** | Phase 1 terminée en ≤ 1 jour calendaire | 🔴 Critique | J+7 = 27/02/2026 |

---

## PLANNING PHASE 1 (objectif : ≤ 1 journée)

| # | Tâche | Durée |
|---|---|---|
| 1 | Installation Symfony 6.4 sur VPS + config PHP 8.2 + Nginx | 1h |
| 2 | Création des 11 entités Doctrine + relations + enums | 2h |
| 3 | Génération et exécution de la migration unique | 30min |
| 4 | Configuration sécurité multi-profils + formulaire de login | 1h |
| 5 | Création des 11 fichiers d'interface (corps vides) | 45min |
| 6 | Création des 6 DTOs `readonly class` | 30min |
| 7 | Configuration du Symfony Workflow (commandes) | 30min |
| 8 | Test déploiement VPS + smoke test sécurité (accès par profil) | 30min |

**Total estimé : ~6h45 — Phase 1 livrée en J+1 (21/02/2026)**

---

## ENTRÉE PHASE 2

La Phase 2 peut démarrer dès que :
- [ ] `php bin/console doctrine:migrations:migrate` passe sans erreur sur le VPS
- [ ] Un utilisateur `ROLE_DMAX` peut se connecter et accéder à `/dmax/`
- [ ] Toutes les interfaces de service sont commitées dans `src/Interface/`
