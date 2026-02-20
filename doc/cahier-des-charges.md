# Cahier des charges — Application Web de don de mobilier CPAM

**Date de création :** 18/02/2026  
**Dernière mise à jour :** 20/02/2026  
**Développeur :** Bertrand  
**Date cible de mise en production :** 27/02/2026 (5 jours de développement)

---

## 1. Contexte

La CPAM organise une opération de don de son ancien mobilier dans le cadre d'un déménagement géré par **DMAX** (inventaire, manutention, remise aux bénéficiaires). Les produits sont mis à disposition de différentes catégories de bénéficiaires selon un calendrier d'ouverture priorisé. Les produits non attribués à l'issue de l'opération sont orientés vers la filière **déchets**.

Les bénéficiaires sont répartis en deux grandes familles :

- **Agents CPAM** : agents salariés et télétravailleurs, avec quota de produits configurable (défaut : 3) et obligation de réserver un créneau de retrait.
- **Partenaires** : institutions (URSSAF, établissements de santé, mairie…) et associations de récupération de matériel, sans limite de quantité ni obligation de créneau.

---

## 2. Calendrier de l'opération

| Date | Événement |
|---|---|
| **2 – 6 mars** | DMAX constitue la base produits (inventaire, photos, dimensions…) |
| **9 mars** | Validation interne par la direction |
| **10 mars** | Ouverture du site |
| **10 mars → 13 mars (midi)** | Phase commande **télétravailleurs** (produits tagués télétravailleur uniquement) |
| **À définir** | Phase commande **agents/salariés** |
| **À définir** | Phase commande **institutions** puis **associations** |
| **14 mars (samedi)** | Remise **télétravailleurs uniquement** |
| **21 mars (samedi)** | Remise **télétravailleurs** (si 14 mars saturé) + **autres catégories** (choix libre 21 ou 28) |
| **28 mars (samedi)** | Remise **autres catégories** (choix libre 21 ou 28) |
| Fin d'opération | Produits non retirés → filière **déchets** |

---

## 3. Workflow général de l'opération

Les quatre phases peuvent se **chevaucher** selon les besoins opérationnels. Aucune phase n'est un prérequis bloquant pour la suivante.

### Phase 1 — Constitution de la base produits *(back-office DMAX, 2–6 mars)*

- DMAX parcourt les locaux et saisit chaque produit dans l'application depuis mobile, tablette ou ordinateur.
- La base peut être complétée ou modifiée à tout moment, y compris pendant la vente.
- La boutique reste fermée jusqu'à activation manuelle par l'admin.

### Phase 2 — Mise en vente *(front-office, activation manuelle par l'admin)*

- L'admin contrôle l'ouverture **indépendamment par type de profil** : télétravailleurs, agents/salariés, partenaires.
- Les télétravailleurs ne peuvent commander que des **produits tagués télétravailleur**.
- Les Partenaires commandent sans créneau de retrait.
- Aucun email automatique à ce stade : l'email de l'agent n'est pas encore connu.

### Phase 3 — Contrôle et validation des commandes *(direction/responsables CPAM)*

- Import "jetable" d'un extrait GRH (numéro d'agent → nom, prénom, email, téléphone) pour notification et vérification.
- Croisement **nom + prénom + numéro d'agent** avec la base GRH interne.
- Envoi d'un **email d'acceptation ou de refus** via les données GRH importées.
- L'admin peut supprimer une commande pour débloquer un agent (usurpation d'identité, litige…).
- En cas de refus : produits remis en vente, quota agent rétabli, agent peut recommander.
- La validation peut démarrer sans attendre la fin de la mise en vente (traitement au fil de l'eau).

### Phase 4 — Remise du matériel *(parking fermé, anciens locaux ESP au CAD)*

- Les commandes sont descendues par DMAX le jour J.
- Suivi du statut de préparation de chaque commande (cf. section 9).
- Bons de livraison imprimés **en avance**, signés par l'agent le jour J.
- Non-présentation à la **fin de la journée** → annulation automatique, produits remis en vente.

---

## 4. Infrastructure technique

| Paramètre | Valeur |
|---|---|
| **Hébergement** | VPS ~5 €/mois |
| **Stack** | PHP / Symfony |
| **Interface** | Responsive mobile/tablette (obligatoire agents et DMAX terrain) — ordinateur possible pour DMAX |
| **Apparence** | Non prioritaire |
| **Photos produits** | Compressées JPEG (économie serveur) |
| **Photo numéro d'inventaire** | PNG (qualité préservée pour lisibilité des caractères) |

---

## 5. Acteurs et profils d'accès

Authentification par **login / mot de passe** pour tous les profils. Les comptes peuvent être **activés ou désactivés** par l'admin.

| Profil | Qui | Accès | Limite commandes | Créneau requis |
|---|---|---|---|---|
| **Admin** | Responsable technique | Tout faire, gestion boutique et comptes | — | — |
| **DMAX** | Opérateurs DMAX | Back-office inventaire + préparation/remise | — | — |
| **Agent** | Agents CPAM (code partagé) | Front-office boutique | 1 commande, quota produits configurable (défaut : 3) | Oui |
| **Télétravailleur** | Agents CPAM télétravailleurs | Front-office boutique (produits télétravailleur uniquement) | 1 commande, quota produits configurable (défaut : 3) | Oui |
| **Partenaire** | Institutions et associations (1 compte / structure) | Front-office boutique | Illimité, pas de limite produits | **Non** |
| **Agent récupération** | Agents CPAM nommément identifiés (3) | Back-office remise matériel | — | — |

> Un agent **télétravailleur** peut passer **deux commandes au total** : une via le profil Agent (créneau 21 ou 28 mars) et une via le profil Télétravailleur (créneau 14 mars, ou 21 mars si 14 saturé), chacune avec son propre créneau.  
> Un agent **non-télétravailleur** n'a droit qu'à **une seule commande** et un seul créneau.

---

## 6. Gestion de la liste télétravailleurs

- Import possible d'une **liste de numéros d'agents télétravailleurs** (format : CSV minimal, numéros d'agent uniquement).
- Lors d'une commande via le profil **Télétravailleur** : le numéro d'agent est **contrôlé contre cette liste** — accès refusé si absent.
- Lors d'une commande via le profil **Agent** : le numéro d'agent est requis mais **non contrôlé**.
- L'admin peut gérer cette liste manuellement (ajout/suppression).

---

## 7. Fiche produit *(saisie DMAX — Phase 1)*

Tous les champs sont **obligatoires sauf le numéro d'inventaire et la photo du numéro d'inventaire**.

| Champ | Obligatoire | Remarques |
|---|---|---|
| **Numéro d'inventaire** | Non | Vérifié contre la base Copernic (Excel) : avertissement si inconnu, pas de blocage |
| **Photo du produit** | Oui | JPEG compressé |
| **Photo du numéro d'inventaire** | Non | PNG, qualité préservée — facultative si numéro d'inventaire indisponible |
| **Libellé** | Oui | |
| **État** | Oui | TBE (Très Bon État) / Bon État / Abîmé |
| **Dimensions** | Oui | Largeur × Hauteur × Profondeur (en cm) |
| **Étage** | Oui | |
| **Porte / Bureau** | Oui | |
| **Tag télétravailleur** | Non | Booléen — positionnable manuellement par l'admin ou DMAX |

### Tag télétravailleur
Positionnable manuellement par l'admin, avec possibilité d'appliquer des règles de taggage par libellé (ex : tout produit dont le libellé contient "caisson" → tag télétravailleur = true).

---

## 8. Boutique — Front-office *(Phase 2)*

### Comportement du panier

- Le matériel est **réservé 30 minutes** dès sa mise au panier.
- Passé ce délai sans validation, il redevient automatiquement disponible.
- À la **validation** : retrait définitif du stock.
- Les **agents et télétravailleurs** choisissent un créneau de retrait à la validation.
- Les **partenaires** valident sans choisir de créneau.

### Informations collectées à la validation

| Champ | Agent / Télétravailleur | Partenaire |
|---|---|---|
| **Numéro d'agent** | Oui (5 chiffres, format `\d{5}`) | Non |
| **Nom** | Oui | Non (rattaché au compte structure) |
| **Prénom** | Oui | Non |
| **Email** | Non (pas stocké à ce stade) | Non |
| **Téléphone** | Non (pas stocké à ce stade) | Non |

### Règles commande par profil

- Un agent ayant déjà commandé via ce profil reçoit un message l'invitant à **contacter l'admin** (cas d'usurpation d'identité possible).
- Tout changement de commande = **annulation** par l'admin puis nouvelle commande par l'agent.

### Créneaux disponibles selon profil

| Profil | Créneaux proposés |
|---|---|
| **Télétravailleur** | 14 mars en priorité ; 21 mars proposé uniquement si 14 mars saturé |
| **Agent / Salarié** | Choix libre entre 21 mars et 28 mars |
| **Partenaire** | Aucun créneau |

---

## 9. Gestion des créneaux de retrait

Configurable par l'admin :

- **Durée :** 30 minutes par créneau
- **Capacité :** 10 commandes (paniers) par créneau
- **Plages horaires :** 8h00–12h00 et 13h00–17h00
- **Capacité totale :** 160 commandes par samedi
- **Lieu :** parking fermé / anciens locaux ESP au CAD

---

## 10. Préparation et remise des commandes *(Phase 4)*

Outil de suivi utilisé par DMAX et les agents de récupération CPAM :

| Statut | Description |
|---|---|
| **À préparer** | Commande confirmée, en attente de préparation |
| **En préparation** | DMAX descend les produits |
| **Prête** | Commande disponible sur le parking |
| **Retirée** | Remise effectuée, agent signataire |
| **Annulée** | Non-présentation en fin de journée, produits remis en vente |

### Bon de livraison

- Imprimé **en avance** avant le créneau, généré depuis l'application (PDF à imprimer).
- Contenu : identification agent, liste des produits, confirmation de remise en main propre.
- Signé par l'agent bénéficiaire le jour J.
- **Un seul exemplaire**, conservé par la CPAM.
- Badge agent exigé le jour J pour vérification d'identité.

---

## 11. Contrôle humain des commandes *(Phase 3)*

- Import "jetable" d'un extrait GRH : numéro d'agent → nom, prénom, email, téléphone.
- Croisement avec les données saisies à la commande (nom + prénom + numéro d'agent) pour vérifier l'identité.
- Motifs de refus possibles :
  - Agent non identifié ou usurpation d'identité suspectée
  - Tentatives de commandes multiples avec des identités différentes
  - Agent signalé
- En cas de refus : produits **remis en vente**, quota **rétabli**, agent peut recommander.
- Le créneau étant déjà choisi, un refus **libère également le créneau** associé.

---

## 12. Protection des données personnelles *(RGPD)*

Conformément au principe de **limitation de la conservation** (RGPD, art. 5-1-e) [web:25][web:28] :

- Les données issues de l'import GRH (email, téléphone) sont stockées dans une **table temporaire** liée à la commande.
- Ces données sont **effacées automatiquement** dès que la commande passe à l'état **Retirée** ou **Annulée**.
- Un job de purge nocturne assure la suppression des données orphelines en cas d'anomalie.
- Seuls les champs nécessaires au contrôle métier (numéro d'agent, nom, prénom, historique de statuts) sont conservés durablement dans la commande.

---

## 13. Exports

| Export | Destinataire | Usage |
|---|---|---|
| **Export des ventes** | Manon (opératrice) | Mise à jour manuelle de Copernic et G-MAT après finalisation |
| **Export du stock restant** | Manon / responsables | Produits non attribués en fin d'opération |
| **Export des ventes (comptabilité)** | Responsables / comptabilité | Planification, contrôle numéros d'agent, croisement GRH |

**Workflow Copernic / G-MAT :**

1. Base de vente finalisée après la Phase 3.
2. Export des ventes + stock restant depuis l'application.
3. Manon procède au croisement et à la mise à jour manuellement.

---

## 14. Modèle de données *(ébauche)*

- **produits** — id, numéro_inventaire *(nullable)*, libellé, photo_produit, photo_num_inventaire *(nullable)*, état (TBE/bon/abîmé), tag_télétravailleur, étage, porte, largeur, hauteur, profondeur, statut (`disponible` / `réservé_temporaire` / `réservé` / `remis`)
- **utilisateurs** — id, login, mot_de_passe, rôle (admin / dmax / agent / télétravailleur / partenaire / agent_recuperation), actif (booléen)
- **partenaires** — id, id_utilisateur, nom, type (institution / association), contact
- **télétravailleurs_liste** — numéro_agent (5 chiffres, liste de référence importable)
- **commandes** — id, id_utilisateur, numéro_agent, nom, prénom, id_créneau *(nullable pour partenaires)*, date_validation, statut (`en_attente_validation` / `confirmée` / `refusée` / `à_préparer` / `en_préparation` / `prête` / `retirée` / `annulée`)
- **lignes_commande** — id, id_commande, id_produit
- **paniers** — id, id_utilisateur, date_expiration (30 min)
- **lignes_panier** — id, id_panier, id_produit
- **créneaux** — id, date, heure_début, heure_fin, capacité_max (10), capacité_utilisée, type (télétravailleur / général)
- **bons_livraison** — id, id_commande, date_impression, signé
- **commande_contacts_tmp** — id, id_commande, email, téléphone, import_batch_id, imported_at *(purgé à Retirée/Annulée)*
- **parametres** — boutique_ouverte_agents (bool), boutique_ouverte_télétravailleurs (bool), boutique_ouverte_partenaires (bool), max_produits_par_commande (int, défaut 3), durée_panier_minutes (int, défaut 30), plages_horaires…

---

## 15. Évolutions futures *(hors périmètre v1)*

- Reconnaissance OCR du numéro d'inventaire à partir de la photo.
- Automatisation de la mise à jour Copernic / G-MAT (actuellement manuelle via Manon).
- Valeur comptable : blocage automatique des produits à valeur comptable non nulle pour les profils Agent et Télétravailleur (données disponibles dans le fichier Excel Copernic).
