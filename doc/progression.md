# Progression

## Phase 2 — (conservé tel quel)

### Bloc A — Interfaces & services métier
- [x] Compléter `InventoryManagerInterface`
- [x] Compléter `GrhImportServiceInterface`
- [x] Implémenter `InventoryManager`
- [x] Implémenter `ImageProcessorService`
- [x] Implémenter `GrhImportService`

### Bloc B — CRUD Produit DMAX
- [x] Créer `ProduitType`
- [x] Ajouter routes/actions DMAX
- [x] Créer templates DMAX mobile-first

### Bloc C — Administration
- [x] Créer `ParametreType`
- [x] Créer `ImportTeletravailleursType`
- [x] Ajouter routes/actions admin
- [x] Créer dashboard admin

### Bloc D — Tests
- [x] Ajouter tests service inventory
- [x] Ajouter tests service import GRH
- [x] Ajouter tests contrôleur DMAX

## Phase 3 — Front-Office Boutique & Moteur de Réservation

### Bloc A — Contrats & Entités
- [x] ReservationTemporaire entity
- [x] ProfilUtilisateur enum
- [x] CartManagerInterface complétée
- [x] SlotManagerInterface complétée
- [x] Produit.isTeletravailleur ajouté
- [x] Migrations générées et validées

### Bloc B — Services métier
- [x] CartManager (addItem, validateCart LOCK, releaseExpired)
- [x] QuotaCheckerService
- [x] CreneauManager (cache 30s + jauge directe)
- [x] CheckoutService (transaction atomique)

### Bloc C — Controllers & Templates
- [x] ShopController + catalogue.html.twig
- [x] CartController + cart/index.html.twig
- [x] CheckoutController + creneaux.html.twig + confirmation.html.twig

### Bloc D — Tests
- [x] CartManagerTest
- [x] QuotaCheckerServiceTest
- [x] CreneauManagerTest
- [x] CheckoutServiceTest
- [x] Tests fonctionnels Controllers

## Phase 4 — Workflows RH, Remise Logistique et Conformité RGPD

### Bloc A — Automatisation Workflow RH
- [x] Workflow `commande_lifecycle` configuré (validation, refus, préparation, retrait, annulation)
- [x] Subscriber Workflow branché (`CommandeWorkflowSubscriber`)
- [x] Purge RGPD sur transitions de retrait/annulation
- [x] Service de croisement GRH implémenté

### Bloc B — Notifications Mail
- [x] Créer `MailerNotifierInterface`
- [x] Implémenter `MailerNotifier` (validation + refus/annulation)
- [x] Brancher les notifications sur les transitions Workflow (`valider`, `refuser`, `annuler_commande`)

### Bloc C — Remise Logistique
- [x] `LogistiqueController` et dashboard de suivi
- [x] Créer `BonLivraisonGeneratorInterface`
- [x] Implémenter `BonLivraisonGenerator`
- [x] Ajouter le template print-friendly `templates/logistique/bon_livraison_print.html.twig`

### Bloc D — Conformité & maintenance
- [x] Purge RGPD nocturne (commande console + service)
- [x] Entité temporaire `CommandeContactTmp` utilisée pour les contacts importés
- [x] Tests unitaires service mailer
- [x] Tests unitaires générateur de bon de livraison
