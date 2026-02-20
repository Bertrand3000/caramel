## Phase 1 : Socle Technique optimisé pour l'IA (PHP 8.2)

**OBJECTIF**
Adapter l'architecture technique et les choix technologiques pour maximiser l'efficacité, la fiabilité et la sécurité du code généré par les agents IA de codage autonome, en exploitant les capacités modernes de PHP.

**DÉCISIONS**
- **Montée de version du Framework** : Remplacement de Symfony 5.4 par Symfony 6.4 LTS. Cette version LTS requiert au minimum PHP 8.1 et s'intègre de manière optimale avec PHP 8.2 [web:3].
- **Paradigme de typage strict** : Utilisation intensive des fonctionnalités introduites jusqu'à PHP 8.2 (classes `readonly`, types DNF, types autonomes) pour réduire le périmètre d'erreur et les hallucinations typographiques des agents IA.
- **Remplacement des annotations par les attributs** : Les agents IA doivent s'appuyer sur les attributs natifs PHP 8 pour le routing, Doctrine et la validation, qui sont pleinement généralisés dans les versions modernes de Symfony [web:6].
- **Architecture atomique** : Pour contourner les limites de contexte (tokens) des IA, le code sera découpé en micro-services mono-responsables et les classes ne devront pas excéder 150 lignes.

**COMPOSANTS**
- **Contrats d'interface (`Interface`)** : Création préalable d'interfaces explicites (ex: `InventoryManagerInterface`, `CheckoutServiceInterface`). L'IA recevra la tâche stricte d'implémenter ces contrats plutôt que d'inventer sa propre structure.
- **Data Transfer Objects (DTO)** : Les entrées utilisateurs (formulaires, import de la liste des télétravailleurs) transiteront par des DTOs implémentés sous forme de `readonly class` (nouveauté PHP 8.2) garantissant l'immuabilité des données pendant leur traitement.
- **Validateurs Symfony natifs** : Sécurisation des données entrantes (numéro d'agent à 5 chiffres, capacités des créneaux) gérée exclusivement par le composant Validator via les attributs PHP 8 [web:6].

**CONTRAINTES**
- La directive `declare(strict_types=1);` est strictement obligatoire en en-tête de chaque fichier PHP généré par l'IA.
- Les prompts fournis par le rôle @Prompt devront isoler chaque fichier à créer en fournissant son interface de référence comme contexte.
- Interdiction stricte de coder de la logique métier complexe directement dans les `Controller` : tout doit être délégué à des `Service` indépendants pour faciliter la génération autonome des tests PHPUnit associés.

---

## Phase 2 : Module Inventaire DMAX & Gestion Catalogue

**OBJECTIF**
Outiller les équipes DMAX pour la constitution de la base produits sur le terrain (mobile/tablette) et fournir à l'administrateur les leviers de paramétrage de la boutique (ouverture, import télétravailleurs).

**DÉCISIONS**
- Interface back-office impérativement "Mobile-First" pour faciliter le travail de manutention.
- Traitement des médias côté serveur : compression agressive en JPEG pour les photos produits (optimisation de l'espace disque VPS), et conservation du format PNG strict pour les numéros d'inventaire afin de préserver la lisibilité.

**COMPOSANTS**
- CRUD Produit (Formulaire rapide avec champs obligatoires/facultatifs).
- Service d'Upload et de traitement d'images (redimensionnement à la volée).
- Parseur CSV pour l'import de la `télétravailleurs_liste`.
- Paramétrage global (Boutons on/off d'ouverture par profils).

**CONTRAINTES**
- Expérience de saisie fluide, même avec un réseau mobile instable.
- Absence de numéro d'inventaire traitée comme un avertissement non bloquant.

---

## Phase 3 : Front-Office Boutique & Moteur de Réservation

**OBJECTIF**
Développer les tunnels de commande différenciés par profil (filtrage télétravailleurs, quotas d'articles, exemptions pour les partenaires) et sécuriser la réservation physique des produits.

**DÉCISIONS**
- Implémentation d'un système de réservation de stock temporaire (30 minutes) lié à la session/panier pour éviter la surréservation.
- Gestion logicielle des conflits d'accès (verrouillage de ligne ou requêtes transactionnelles) lors de la validation simultanée de deux paniers sur un même produit.
- Séparation visuelle claire des produits "tagués télétravailleur".

**COMPOSANTS**
- Catalogue vitrine dynamique avec filtres d'accès.
- Gestionnaire de Panier (Stockage BDD temporaire avec timer).
- Tunnel de Validation (Check des quotas limités à 3 par défaut, Sélection de `Créneau` avec contrôle de la jauge max de 10 commandes/30min).

**CONTRAINTES**
- Temps de réponse < 2 secondes lors du pic de charge prévisible à l'ouverture du site [1].
- Prévention absolue des "race conditions" lors de l'attribution des créneaux et la validation des paniers.

---

## Phase 4 : Workflows RH, Remise Logistique et Conformité RGPD

**OBJECTIF**
Automatiser les vérifications d'identité via le croisement des données RH, équiper les agents de récupération sur le parking, et garantir la stricte conformité RGPD.

**DÉCISIONS**
- Isolation architecturale des données personnelles : création d'une table "jetable" (`commande_contacts_tmp`) pour l'import GRH, décorrélée de l'historique métier [1].
- Utilisation du composant Symfony Workflow pour gérer rigoureusement le cycle de vie d'une commande (de `en_attente_validation` à `retirée` / `annulée`).

**COMPOSANTS**
- Service de croisement de données GRH (Nom + Prénom + Numéro).
- Notificateur Mailer (Envois des validations/refus à la volée).
- Générateur de Bon de Livraison (PDF ou format HTML print-friendly pour impression unitaire par DMAX).
- Dashboard DMAX / Agents de récupération (Pointage des remises).
- Commande Console (Cron) de purge nocturne pour l'effacement définitif des données de contact.

**CONTRAINTES**
- RGPD : Principe de limitation de conservation strict (effacement en base déclenché immédiatement au passage de l'état `Retirée` ou `Annulée`) [1].
- Génération fluide des exports (Ventes, Stocks) sans surcharge serveur.
