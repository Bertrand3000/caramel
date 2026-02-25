# Administration des Journées de Livraison — Architecture

**Date :** 2026-02-25 11:44  
**Rôle :** DIRECTEUR  
**Fonctionnalité :** Administration des créneaux de livraison (transverse, post-Phase 4)

---

## OBJECTIF

Permettre aux administrateurs de configurer des **journées de livraison** : jours ouverts, plages horaires, coupure méridienne libre, règle "exiger que la journée soit pleine avant de proposer le jour suivant". L'admin dispose d'une **vue visuelle par journée** affichant les créneaux générés, leur taux de remplissage, les commandes associées, avec la possibilité d'annuler une réservation individuelle.

---

## DÉCISIONS ARCHITECTURALES

### D1 — Nouvelle entité `JourLivraison`

La configuration d'une journée de livraison est extraite dans une entité dédiée, distincte de `Creneau`. `Creneau` reste l'unité atomique de réservation (30 min par défaut, paramètre global). `JourLivraison` est le gabarit de configuration qui **génère** ses créneaux.

**Justification :** `Creneau` est déjà lié à `Commande` via une relation forte. Séparer la configuration de l'instance préserve la rétrocompatibilité et permet de modifier les paramètres d'une journée sans impacter les créneaux déjà réservés.

---

### D2 — Relation `JourLivraison → Creneau` : OneToMany

Un `JourLivraison` possède plusieurs `Creneau`. La FK `jour_livraison_id` est ajoutée sur la table `creneaux` en **nullable** (rétrocompatibilité avec les créneaux existants non rattachés à un jour).

---

### D3 — Champ `exigerJourneePleine` : booléen sur `JourLivraison`

Ce champ remplace et généralise l'ancienne logique couplée au type `TELETRAVAILLEUR`. Il est **indépendant du type** : n'importe quelle journée (GENERAL ou TELETRAVAILLEUR) peut activer cette règle.

**Règle métier :** lors du checkout, si un `JourLivraison` actif du **même type** avec `exigerJourneePleine = true` existe chronologiquement **avant** le jour sélectionné et n'est pas encore plein, la sélection est bloquée.

**Définition de "plein" :** tous les créneaux du jour ont `capaciteUtilisee >= capaciteMax`.

**Priorité :** ordre chronologique sur `date ASC` puis `heureDebut ASC`. Aucun rang manuel.

---

### D4 — Capacité et durée des créneaux : paramètres globaux

Les champs `capaciteMax` (défaut 10) et `dureeCreneauMinutes` (défaut 30) restent dans `Parametre`. Le générateur lit ces valeurs **au moment de la génération** et les fige dans chaque `Creneau` créé (pour éviter les effets de bord d'une modification ultérieure des globaux sur des créneaux existants).

---

### D5 — Régénération partielle (Option C)

Le service `CreneauGeneratorService` applique la logique suivante à chaque (ré)génération :

1. Partitionner les créneaux existants du jour :
   - **Verrouillés** : `capaciteUtilisee > 0` → intouchables
   - **Libres** : `capaciteUtilisee = 0` → supprimables
2. Calculer la **grille théorique** des créneaux à partir des paramètres du `JourLivraison` (plages horaires, coupure méridienne, durée globale).
3. Supprimer les créneaux **libres** qui ne correspondent plus à la nouvelle grille.
4. Créer les créneaux **manquants** dans la grille théorique, en ignorant les tranches horaires en conflit avec des créneaux verrouillés.
5. Retourner un `GenerationResult` avec les métriques de l'opération.

---

### D6 — Annulation de réservation depuis la vue admin

L'annulation passe **obligatoirement** par le Workflow `commande_lifecycle` (transition `annuler_commande`) pour garantir :
- Libération du slot (`capaciteUtilisee--`)
- Remise en vente des produits concernés
- Purge RGPD des données de contact temporaires
- Envoi de la notification email de refus/annulation

Aucun accès direct au Repository pour annuler depuis cette vue.

---

### D7 — Coupure méridienne libre

L'admin définit librement `heureCoupureDebut` et `heureCoupureFin` (ex : 11h30–13h30). Ces deux champs sont **nullables** et ignorés si `coupureMeridienne = false`. Des contraintes de cohérence temporelle sont validées côté formulaire.

---

## COMPOSANTS

### Entité `JourLivraison` _(nouvelle)_

```
src/Entity/JourLivraison.php
```

| Champ               | Type Doctrine         | Défaut  | Description                                          |
|---------------------|-----------------------|---------|------------------------------------------------------|
| `id`                | int (PK auto)         | —       | Identifiant                                          |
| `date`              | date_immutable        | —       | Date de la journée de livraison                      |
| `actif`             | bool                  | `true`  | Jour ouvert ou fermé                                 |
| `heureOuverture`    | time                  | `08:00` | Début de la première plage horaire                   |
| `heureFermeture`    | time                  | `17:00` | Fin de la dernière plage horaire                     |
| `coupureMeridienne` | bool                  | `false` | Active la pause méridienne                           |
| `heureCoupureDebut` | time (nullable)       | `null`  | Début de la pause (ex : 12:00)                       |
| `heureCoupureFin`   | time (nullable)       | `null`  | Fin de la pause (ex : 13:00)                         |
| `exigerJourneePleine` | bool                | `false` | Doit être plein avant de proposer le jour suivant    |
| `type`              | CreneauTypeEnum       | GENERAL | GENERAL ou TELETRAVAILLEUR                           |
| `creneaux`          | Collection\<Creneau\> | []      | OneToMany (mapped by `jourLivraison`)                |

**Contraintes de validation (Assert) :**
- `heureCoupureDebut > heureOuverture` si coupure active
- `heureCoupureFin < heureFermeture` si coupure active
- `heureCoupureDebut < heureCoupureFin` si coupure active
- `heureOuverture < heureFermeture`

---

### Entité `Creneau` _(modifiée)_

```
src/Entity/Creneau.php
```

Ajout d'un champ :

```php
#[ORM\ManyToOne(targetEntity: JourLivraison::class, inversedBy: 'creneaux')]
#[ORM\JoinColumn(nullable: true)]
private ?JourLivraison $jourLivraison = null;
```

Aucun autre champ modifié. Rétrocompatibilité totale avec les créneaux existants (FK nullable).

---

### Contrôleur `Admin/JourLivraisonController` _(nouveau)_

```
src/Controller/Admin/JourLivraisonController.php
```

| Nom de route                              | Méthode HTTP  | URI                                                                          | Action                                      |
|-------------------------------------------|---------------|------------------------------------------------------------------------------|---------------------------------------------|
| `admin_jours_livraison_index`             | GET           | `/admin/jours-livraison`                                                     | Liste des journées configurées              |
| `admin_jours_livraison_new`               | GET / POST    | `/admin/jours-livraison/new`                                                 | Création d'une journée                      |
| `admin_jours_livraison_edit`              | GET / POST    | `/admin/jours-livraison/{id}/edit`                                           | Modification du gabarit                     |
| `admin_jours_livraison_generer`           | POST          | `/admin/jours-livraison/{id}/generer`                                        | (Ré)génération des créneaux                 |
| `admin_jours_livraison_creneaux`          | GET           | `/admin/jours-livraison/{id}/creneaux`                                       | Vue visuelle créneaux + réservations        |
| `admin_jours_livraison_annuler_reservation` | POST        | `/admin/jours-livraison/{id}/creneaux/{creneauId}/commandes/{commandeId}/annuler` | Annulation d'une commande via Workflow |

**Sécurité :** toutes ces routes sont protégées par `ROLE_ADMIN`, cohérent avec le préfixe `/admin/` existant dans `security.yaml`.

---

### Form Type `JourLivraisonType` _(nouveau)_

```
src/Form/JourLivraisonType.php
```

Champs :
- `date` → `DateType` (widget: single_text)
- `actif` → `CheckboxType`
- `type` → `EnumType` (CreneauTypeEnum)
- `heureOuverture` → `TimeType` (widget: single_text, with_seconds: false)
- `heureFermeture` → `TimeType`
- `coupureMeridienne` → `CheckboxType`
- `heureCoupureDebut` → `TimeType` (required: false)
- `heureCoupureFin` → `TimeType` (required: false)
- `exigerJourneePleine` → `CheckboxType`

`heureCoupureDebut` et `heureCoupureFin` sont affichés/masqués dynamiquement via un contrôleur **Stimulus** réagissant au toggle de `coupureMeridienne`.

---

### Service `CreneauGeneratorService` _(nouveau)_

```
src/Service/CreneauGeneratorService.php
src/Interface/CreneauGeneratorInterface.php
```

**Interface :**
```php
interface CreneauGeneratorInterface
{
    public function generate(JourLivraison $jour): GenerationResult;
}
```

**DTO `GenerationResult` :**
```
src/DTO/GenerationResult.php
```
| Champ           | Type     | Description                          |
|-----------------|----------|--------------------------------------|
| `crees`         | int      | Nombre de créneaux créés             |
| `supprimes`     | int      | Nombre de créneaux supprimés         |
| `verrouilles`   | int      | Créneaux inchangés (avec commandes)  |
| `avertissements`| string[] | Conflits horaires non résolus        |

**Algorithme principal :**
1. Lire `Parametre` → `dureeCreneauMinutes`, `capaciteMax`
2. Calculer la grille théorique : plage matin (`heureOuverture` → `heureCoupureDebut`) + plage après-midi (`heureCoupureFin` → `heureFermeture`) si coupure active, sinon plage unique
3. Récupérer les `Creneau` existants du jour, partitioner en "verrouillés" vs "libres"
4. Supprimer les libres hors grille théorique
5. Créer les créneaux manquants, sauf si conflit horaire exact avec un créneau verrouillé (→ avertissement)
6. Flush et retourner `GenerationResult`

---

### Repository `JourLivraisonRepository` _(nouveau)_

```
src/Repository/JourLivraisonRepository.php
```

Méthodes à exposer :

| Méthode                                                    | Description                                                                 |
|------------------------------------------------------------|-----------------------------------------------------------------------------|
| `findActifsByTypeOrderedByDate(CreneauTypeEnum): array`    | Jours actifs d'un type, triés ASC par date                                 |
| `findPremierJourNonPleinAvant(\DateTimeImmutable, CreneauTypeEnum): ?JourLivraison` | Premier jour actif avec `exigerJourneePleine=true`, non plein, avant la date cible — utilisé dans le checkout |

La méthode `findPremierJourNonPleinAvant` utilise un `JOIN` sur `creneaux` avec agrégation pour calculer "plein" directement en SQL (performance).

---

### Templates Twig _(nouveaux)_

```
templates/admin/jours_livraison/
  index.html.twig       — tableau des journées (date, type, actif, exigerPleine, nb créneaux, % remplissage, actions)
  form.html.twig        — formulaire création/édition partagé (new + edit)
  creneaux.html.twig    — vue visuelle des créneaux et réservations
```

#### Structure de `creneaux.html.twig`

- En-tête : date, type, stats globales (X/Y commandes, Z créneaux)
- Bouton "Régénérer les créneaux" → POST vers `generer`, avec **modal de confirmation** affichant les stats `GenerationResult` avant validation
- Tableau chronologique des créneaux :

| Heure | Capacité | Commandes | Action |
|-------|----------|-----------|--------|
| 08:00–08:30 | 3/10 🟡 | Agent Dupont #12345, … | — |
| 08:30–09:00 | 10/10 🔴 | (plein) | — |
| 09:00–09:30 | 0/10 🟢 | — | — |

- Pour chaque commande dans un créneau : numéro agent, nom, prénom, statut
- Bouton "Annuler" par commande → POST vers `annulerReservation`, avec confirmation JS

**Badges de remplissage :**
- 🟢 0 % → < 50 %
- 🟡 50 % → < 100 %
- 🔴 100 % (plein)

---

### Modification de `CheckoutService` _(existant)_

Intégrer la règle `exigerJourneePleine` dans la méthode de sélection/validation du créneau :

```
Avant de valider le créneau choisi :
  → appeler JourLivraisonRepository::findPremierJourNonPleinAvant($dateChoisie, $type)
  → si résultat non null : lever une exception métier "Le jour du [date] doit être complet avant de choisir cette date"
  → le CheckoutController affiche le message à l'utilisateur sans invalider le panier
```

---

## MIGRATIONS

Deux migrations distinctes, dans cet ordre :

**Migration 1 — Création `jours_livraison`**
```sql
CREATE TABLE jours_livraison (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  actif TINYINT(1) NOT NULL DEFAULT 1,
  heure_ouverture TIME NOT NULL,
  heure_fermeture TIME NOT NULL,
  coupure_meridienne TINYINT(1) NOT NULL DEFAULT 0,
  heure_coupure_debut TIME DEFAULT NULL,
  heure_coupure_fin TIME DEFAULT NULL,
  exiger_journee_pleine TINYINT(1) NOT NULL DEFAULT 0,
  type VARCHAR(20) NOT NULL
);
```

**Migration 2 — Ajout FK sur `creneaux`**
```sql
ALTER TABLE creneaux
  ADD COLUMN jour_livraison_id INT DEFAULT NULL,
  ADD CONSTRAINT FK_creneaux_jour_livraison
    FOREIGN KEY (jour_livraison_id) REFERENCES jours_livraison(id)
    ON DELETE SET NULL;
```

---

## CONTRAINTES CRITIQUES

### Sécurité
- Routes `/admin/jours-livraison/*` : `ROLE_ADMIN` obligatoire
- Annulation de commande : passage **obligatoire** par le Workflow, jamais par accès direct à l'EntityManager seul

### Rétrocompatibilité
- `Creneau.jourLivraison` nullable → créneaux existants sans `JourLivraison` continuent de fonctionner normalement
- `CreneauTypeEnum` non modifié

### Cohérence des données
- Un `JourLivraison` avec `exigerJourneePleine = true` et aucun créneau généré est considéré **non plein** (ne pas bloquer à tort)
- La règle ne s'applique qu'aux journées **actives** (`actif = true`)

### Performance
- Vue `creneaux.html.twig` : une requête avec `JOIN FETCH` sur `Creneau + Commande` (éviter N+1)
- `findPremierJourNonPleinAvant` : agrégation SQL directe, pas de chargement en mémoire

### Ordre de déploiement
1. Migration 1 (`jours_livraison`)
2. Migration 2 (FK sur `creneaux`)
3. Déploiement du code
4. Saisie des journées dans l'admin
5. Génération des créneaux depuis la vue admin

---

## POINTS D'ATTENTION POUR L'IMPLÉMENTATION

- **Stimulus controller** pour le toggle conditionnel de la coupure méridienne dans le formulaire
- La **confirmation de régénération** (modal) doit être un appel en deux temps : un premier POST qui simule et retourne les stats (dry-run), puis un second POST qui exécute. Alternativement, afficher les stats après coup avec un flash message si la simplicité est préférée.
- La vue créneaux est **read-only pour les créneaux verrouillés** : pas de suppression directe d'un créneau avec commandes, seulement l'annulation des commandes une par une.
- Prévoir un **test fonctionnel** sur la règle `exigerJourneePleine` dans `CheckoutService` (cas : jour plein → accès autorisé ; jour non plein → accès bloqué).
