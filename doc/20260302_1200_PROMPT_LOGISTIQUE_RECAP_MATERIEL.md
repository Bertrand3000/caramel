# Logistique — Récapitulatif matériel à récupérer

**Date :** 2026-03-02  
**Auteur :** Bertrand  
**Statut :** Implémenté

---

## Objectif

Ajouter une nouvelle page accessible depuis `/logistique` qui récapitule le matériel à récupérer pour les commandes de la journée, triée par étage puis par bureau/porte.

Cette page permet aux équipes DMAX de préparer efficacement les commandes en regroupant les produits par emplacement physique (étage + porte), optimisant ainsi les déplacements lors de la préparation.

---

## Décisions métier

### Définition de la "journée"

**Option A retenue** : La "journée" correspond au **prochain JourLivraison actif** (via `findNextDeliveryDay()`).

Cette décision assure la cohérence avec l'écran logistique existant `/logistique` qui utilise déjà cette définition.

### Statuts de commandes inclus

Seules les commandes **non encore prêtes ou retirées** sont incluses :
- `VALIDEE` — Commande validée, à préparer
- `EN_PREPARATION` — Commande en cours de préparation

Les commandes `PRETE` et `RETIREE` sont exclues car le matériel a déjà été descendu ou remis.

### Règles de tri

1. **Tri principal par étage** : Tri naturel (`natsort`) pour gérer correctement les valeurs numériques (1, 2, 10 au lieu de 1, 10, 2)
2. **Tri secondaire par porte** : Tri naturel également
3. **Tri tertiaire par libellé produit** : Dans la requête Doctrine pour un ordre cohérent au sein d'un même emplacement

---

## Fichiers touchés

### Repository
- `src/Repository/CommandeRepository.php`
  - Nouvelle méthode : `findLignesForRecapMateriel(JourLivraison $jour): array`
  - Requête avec jointures préchargées (commande, lignes, produit, créneau, jour) pour éviter le N+1
  - Retourne un tableau plat de lignes avec leurs relations

### Service
- `src/Interface/LogistiqueServiceInterface.php`
  - Nouvelle méthode : `findRecapMateriel(JourLivraison $jour): array`
- `src/Service/LogistiqueService.php`
  - Implémentation de `findRecapMateriel()` avec groupement par étage → porte
  - Méthode privée `natKsort()` pour le tri naturel des clés

### Controller
- `src/Controller/LogistiqueController.php`
  - Nouvelle route : `/logistique/recap` (`logistique_recap`)
  - Action `recapMateriel()` protégée par `ROLE_DMAX`
  - Gestion du cas `jour === null` avec redirection et flash message

### Templates
- `templates/logistique/recap_materiel.html.twig` (nouveau)
  - Affichage hiérarchique : Étage → Porte → Produits
  - Informations affichées : libellé, n° inventaire, quantité, commande (lien PDF), agent
  - Message "aucun matériel" si vide
  - Lien retour vers `/logistique`
- `templates/logistique/index.html.twig` (modifié)
  - Ajout bouton "📦 Récap matériel à récupérer" dans l'en-tête

---

## Structure des données retournées

```php
[
    "Étage 1" => [
        "Bureau 101" => [
            [
                'produit' => Produit,
                'quantite' => int,
                'commandeId' => int,
                'agent' => string (prénom nom + n° agent ou "Commande #X")
            ],
            // ...
        ],
        // ...
    ],
    // ...
]
```

---

## Performance

- **Une seule requête Doctrine** avec jointures et `addSelect` pour précharger toutes les relations
- **Pas de lazy-loading** dans le template Twig
- Tri naturel effectué en PHP après récupération des données

---

## Sécurité

- Route protégée par `#[IsGranted('ROLE_DMAX')]`
- Accès refusé (403) pour les utilisateurs sans le rôle approprié

---

## Tests

### Tests unitaires (à ajouter si nécessaire)
- Tester le groupement par étage/porte dans `LogistiqueServiceTest`
- Tester le tri naturel avec des valeurs comme "1", "2", "10"

### Vérification manuelle
1. `/logistique` affiche le bouton "Récap matériel à récupérer"
2. `/logistique/recap` affiche la page avec les données groupées
3. Page vide avec message approprié si aucune commande
4. Tri correct des étages (1, 2, 10) et des portes

---

## Évolutions possibles

- Ajout d'un filtre par statut (inclure/exclure `EN_PREPARATION`)
- Export PDF du récapitulatif par étage
- Ajout d'un comptage total des produits par emplacement
