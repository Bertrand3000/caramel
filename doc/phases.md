## Phases projet — état de référence au 24/03/2026

Ce document n'est plus uniquement un plan cible: il sert de repère entre la vision initiale et l'application réellement présente dans le dépôt.

## Phase 1 : Socle Technique optimisé pour l'IA

**OBJECTIF**
Adapter l'architecture technique et les choix technologiques pour maximiser l'efficacité, la fiabilité et la sécurité du code généré par les agents IA de codage autonome, en exploitant les capacités modernes de PHP.

**DÉCISIONS**
- **Montée de version du Framework** : Symfony 6.4 LTS est bien en place. Le dépôt annonce actuellement `php >=8.1` dans `composer.json`, même si le cadrage projet reste orienté PHP 8.2.
- **Paradigme de typage strict** : Utilisation intensive des fonctionnalités introduites jusqu'à PHP 8.2 (classes `readonly`, types DNF, types autonomes) pour réduire le périmètre d'erreur et les hallucinations typographiques des agents IA.
- **Remplacement des annotations par les attributs** : Les agents IA doivent s'appuyer sur les attributs natifs PHP 8 pour le routing, Doctrine et la validation, qui sont pleinement généralisés dans les versions modernes de Symfony [web:6].
- **Architecture atomique** : Pour contourner les limites de contexte (tokens) des IA, le code sera découpé en micro-services mono-responsables et les classes ne devront pas excéder 150 lignes.

**COMPOSANTS**
- **Contrats d'interface (`Interface`)** : Le dépôt contient désormais un ensemble élargi d'interfaces métier, au-delà du noyau initial, couvrant inventaire, checkout, logistique, import GRH, PDF, exports et contrôles d'accès.
- **Data Transfer Objects (DTO)** : Les entrées utilisateurs (formulaires, import de la liste des télétravailleurs) transiteront par des DTOs implémentés sous forme de `readonly class` (nouveauté PHP 8.2) garantissant l'immuabilité des données pendant leur traitement.
- **Validateurs Symfony natifs** : Sécurisation des données entrantes (numéro d'agent à 5 chiffres, capacités des créneaux) gérée exclusivement par le composant Validator via les attributs PHP 8 [web:6].

**CONTRAINTES**
- La directive `declare(strict_types=1);` est strictement obligatoire en en-tête de chaque fichier PHP généré par l'IA.
- Les prompts fournis par le rôle @Prompt devront isoler chaque fichier à créer en fournissant son interface de référence comme contexte.
- Interdiction stricte de coder de la logique métier complexe directement dans les `Controller` : tout doit être délégué à des `Service` indépendants pour faciliter la génération autonome des tests PHPUnit associés.

---

**État code**
- Le socle Symfony 6.4, l'authentification et le workflow sont déjà intégrés.
- Le modèle de données a dépassé le périmètre initial avec `JourLivraison`, `ReservationTemporaire`, `AgentEligible` et `RegleTagger`.
- Les routes métier exposées sont principalement `/admin`, `/dmax`, `/boutique`, `/panier`, `/commande` et `/logistique`.

## Phase 2 : Module Inventaire DMAX & Gestion Catalogue

**OBJECTIF**
Outiller les équipes DMAX pour la constitution de la base produits sur le terrain (mobile/tablette) et fournir à l'administrateur les leviers de paramétrage de la boutique (ouverture, import télétravailleurs).

**DÉCISIONS**
- Interface back-office impérativement "Mobile-First" pour faciliter le travail de manutention.
- Traitement des médias côté serveur : compression agressive en JPEG pour les photos produits (optimisation de l'espace disque VPS), et conservation du format PNG strict pour les numéros d'inventaire afin de préserver la lisibilité.

**COMPOSANTS**
- CRUD Produit (Formulaire rapide avec champs obligatoires/facultatifs).
- Service d'Upload et de traitement d'images (redimensionnement à la volée).
- Parseur CSV pour l'import de la `teletravailleurs_liste`.
- Import XLSX complémentaire pour les agents éligibles.
- Paramétrage global (ouverture boutique par profils, quotas, capacités de créneaux).

**CONTRAINTES**
- Expérience de saisie fluide, même avec un réseau mobile instable.
- Absence de numéro d'inventaire traitée comme un avertissement non bloquant.

---

**État code**
- Le CRUD produit DMAX, les uploads d'images et les règles de taggage sont implémentés.
- L'admin gère déjà les paramètres globaux, les utilisateurs, les imports télétravailleurs et agents éligibles, ainsi que les journées de livraison.

## Phase 3 : Front-Office Boutique & Moteur de Réservation

**OBJECTIF**
Développer les tunnels de commande différenciés par profil (filtrage télétravailleurs, quotas d'articles, exemptions pour les partenaires) et sécuriser la réservation physique des produits.

**DÉCISIONS**
- Implémentation d'un système de réservation de stock temporaire lié à la session via `ReservationTemporaire` pour éviter la surréservation.
- Gestion logicielle des conflits d'accès (verrouillage de ligne ou requêtes transactionnelles) lors de la validation simultanée de deux paniers sur un même produit.
- Séparation visuelle claire des produits "tagués télétravailleur".

**COMPOSANTS**
- Catalogue vitrine dynamique avec filtres d'accès.
- Gestionnaire de panier + réservations temporaires.
- Tunnel de validation avec contrôle quota, contrôle commande unique par agent/profil, contrôle agents éligibles et contrôle des journées ouvertes.
- Gestion des `JourLivraison` et génération des `Creneau`.

**CONTRAINTES**
- Temps de réponse < 2 secondes lors du pic de charge prévisible à l'ouverture du site [1].
- Prévention absolue des "race conditions" lors de l'attribution des créneaux et la validation des paniers.

---

**État code**
- Les parcours front sont en place via `/boutique`, `/panier` et `/commande`.
- Les quotas, la réservation de créneau, l'annulation et la limitation à une commande active par profil sont implémentés.
- La logique de "journée précédente à remplir" et l'ouverture/fermeture des réservations se font au niveau `JourLivraison`.

## Phase 4 : Workflows RH, Remise Logistique et Conformité RGPD

**OBJECTIF**
Automatiser les vérifications d'identité via le croisement des données RH, équiper les agents de récupération sur le parking, et garantir la stricte conformité RGPD.

**DÉCISIONS**
- Isolation architecturale des données personnelles : création d'une table "jetable" (`commande_contacts_tmp`) pour l'import GRH, décorrélée de l'historique métier [1].
- Utilisation du composant Symfony Workflow pour gérer le cycle de vie réel des commandes (`en_attente_validation` → `validee` → `en_preparation` → `prete` → `retiree`, avec `annulee` comme sortie d'annulation).

**COMPOSANTS**
- Service de croisement de données GRH (Nom + Prénom + Numéro).
- Notificateur Mailer branché sur les transitions `valider`, `annuler_commande` et `acter_retrait`.
- Générateur de Bon de Livraison (PDF ou format HTML print-friendly pour impression unitaire par DMAX).
- Dashboard logistique / agents de récupération, génération PDF unitaire et batch, liste agents, récap matériel XLSX.
- Commande Console (Cron) de purge nocturne pour l'effacement définitif des données de contact.

**CONTRAINTES**
- RGPD : effacement/anonymisation des contacts temporaires déclenché à `retiree` et `annulee`, avec commande de purge complémentaire.
- Génération fluide des exports (Ventes, Stocks) sans surcharge serveur.

**État code**
- Le workflow, les imports GRH/XLSX, les notifications mail, les vues logistiques et les documents PDF sont déjà présents dans le dépôt.
- La documentation de phase doit désormais être lue comme historique + état courant, pas comme liste exhaustive de travail restant.
