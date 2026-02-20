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
│   └── Shop/               # Front-office boutique (agents, télétravailleurs, partenaires)
├── Entity/                 # 11 entités Doctrine
├── Repository/             # Repositories Doctrine (1 par entité)
├── Service/                # Implémentations des interfaces
├── Interface/              # Contrats de service (créés en Phase 1, implémentés en Phase 2/3/4)
├── DTO/                    # Data Transfer Objects (readonly class PHP 8.2)
├── EventSubscriber/        # Hooks Doctrine/Symfony (purge RGPD, expiration panier)
└── Command/                # Commandes console (cron purge nocturne, exports)
```

### D3 — Sécurité multi-profils : un seul firewall, rôles distincts

Un seul firewall `main` avec `LoginFormAuthenticator`. Hiérarchie des rôles :

```
ROLE_ADMIN > ROLE_DMAX > ROLE_AGENT_RECUPERATION
ROLE_ADMIN > ROLE_PARTENAIRE
ROLE_ADMIN > ROLE_AGENT
ROLE_ADMIN > ROLE_TELÉTRAVAILLEUR
```

> ⚠️ **Décision critique :** `ROLE_AGENT` et `ROLE_TELÉTRAVAILLEUR` sont **distincts et non hiérarchiques**. Un télétravailleur possède les DEUX rôles sur son compte, ce qui lui permet de passer deux commandes séparées (une par profil), chacune avec son propre créneau et son propre quota. Un agent non-télétravailleur ne possède que `ROLE_AGENT`.

### D4 — Modèle de données figé en Phase 1, migration unique

Le schéma complet des 11 entités est créé via **une seule migration Doctrine** (`Version20260220000000`). Cela facilite le rollback et garantit un état de base reproductible sur le VPS. Aucun ajout de colonne ne sera fait par les agents IA dans les phases suivantes sans passer par une nouvelle migration versionnée.

### D5 — Workflow Symfony natif pour le cycle de vie des commandes

Le composant `symfony/workflow` gère les transitions de statut de `Commande`. Les listeners de transition déclenchent automatiquement la purge RGPD et la libération des ressources. Ce choix évite toute logique de statut dispersée dans le code.

---

## COMPOSANTS

### C1 — Les 11 entités Doctrine (schéma complet CdC §14)

| Entité | Table BDD | Points d'attention |
|---|---|---|
| `Produit` | `produits` | Statut en enum PHP : `disponible`, `réservé_temporaire`, `réservé`, `remis` |
| `Utilisateur` | `utilisateurs` | Implémente `UserInterface` + `PasswordAuthenticatedUserInterface`. Champ `roles` JSON. |
| `Partenaire` | `partenaires` | Relation OneToOne vers `Utilisateur`. Champ `type` enum : `institution`, `association` |
| `TeletravaitleurListe` | `télétravailleurs_liste` | Table de référence uniquement — colonne `numéro_agent` CHAR(5) unique |
| `Commande` | `commandes` | Champ `statut` géré par Symfony Workflow. `id_créneau` nullable (partenaires) |
| `LigneCommande` | `lignes_commande` | ManyToOne vers `Commande` et `Produit` |
| `Panier` | `paniers` | `date_expiration` = `CURRENT_TIMESTAMP + 30min`. OneToOne vers `Utilisateur` |
| `LignePanier` | `lignes_panier` | ManyToOne vers `Panier` et `Produit` |
| `Creneau` | `créneaux` | Type enum : `télétravailleur`, `général`. Champ `capacité_utilisée` incrémenté à la commande |
| `BonLivraison` | `bons_livraison` | OneToOne vers `Commande`. Champ `signé` booléen |
| `CommandeContactTmp` | `commande_contacts_tmp` | Contient email + téléphone. Purgé automatiquement à `retirée` ou `annulée` |
| `Parametre` | `parametres` | Table clé-valeur : `boutique_ouverte_agents`, `boutique_ouverte_télétravailleurs`, `boutique_ouverte_partenaires`, `max_produits_par_commande`, `durée_panier_minutes` |

### C2 — Les 11 interfaces de service (contrats pour les agents IA)

Ces fichiers sont créés **vides** en Phase 1. Les agents IA reçoivent l'interface comme contexte de prompt dans les phases suivantes.

```php
interface InventoryManagerInterface       // CRUD produits, gestion tag télétravailleur, règles de taggage par libellé
interface CartManagerInterface            // Ajout/suppression articles panier, calcul expiration
interface StockReservationInterface       // Réservation temporaire (30min) et définitive à la validation
interface CheckoutServiceInterface        // Validation commande : contrôle quota, choix créneau, création Commande
interface SlotManagerInterface            // Gestion créneaux : disponibilité, saturation, libération
interface OrderWorkflowInterface          // Déclenchement des transitions Symfony Workflow
interface GrhImportServiceInterface       // Parsing et insertion CSV GRH dans commande_contacts_tmp
interface NotificationServiceInterface    // Envoi emails acceptation/refus via données GRH importées
interface DeliverySheetGeneratorInterface // Génération bon de livraison (HTML print-friendly ou PDF)
interface ExportServiceInterface          // Exports CSV : ventes, stocks restants, comptabilité
interface PurgeServiceInterface           // Suppression données commande_contacts_tmp (RGPD)
```

### C3 — Les 6 DTOs `readonly class` PHP 8.2

```php
readonly class CreateProduitDTO           // Saisie DMAX : libellé, état, dimensions, étage, porte, tag
readonly class CartAddItemDTO             // id_produit + id_utilisateur
readonly class CheckoutAgentDTO           // numéro_agent (format \d{5}), nom, prénom, id_créneau
readonly class CheckoutPartenaireDTO      // Validation sans créneau, liée au compte partenaire
readonly class GrhImportRowDTO            // numéro_agent, nom, prénom, email, téléphone (jetable)
readonly class ProduitFilterDTO           // Filtres catalogue : tag_télétravailleur, état, disponibilité
```

### C4 — Configuration sécurité (`security.yaml`)

Accès contrôlé par préfixe de chemin :

| Préfixe | Rôles autorisés |
|---|---|
| `/admin/**` | `ROLE_ADMIN` uniquement |
| `/dmax/**` | `ROLE_DMAX`, `ROLE_AGENT_RECUPERATION` |
| `/shop/**` | `ROLE_AGENT`, `ROLE_TELÉTRAVAILLEUR`, `ROLE_PARTENAIRE` |
| `/login` | Public |

### C5 — Workflow Symfony pour `Commande`

```
[en_attente_validation]
        │
        ├─ confirmer ──────────────────► [confirmée]
        │                                     │
        └─ refuser ──► [refusée]         à_préparer
                       (produits                │
                        remis en vente)    [à_préparer]
                                               │
                                          préparer
                                               │
                                         [en_préparation]
                                               │
                                           terminer
                                               │
                                            [prête]
                                               │
                              ┌────────────────┤
                              │                │
                           retirer          annuler
                              │                │
                          [retirée]        [annulée]
                         (purge RGPD)    (purge RGPD +
                                         remise en vente)
```

**Listener sur `retirée` ET `annulée` :**
1. Suppression de `CommandeContactTmp` associé
2. Libération du créneau (`capacité_utilisée--`)
3. Remise des produits à `disponible` (uniquement sur `annulée`)

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
