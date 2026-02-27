CONTEXTE: Projet CARAMEL (Collecte anti-gaspillage / récupération / enlèvements & logistique).
Objectif: Simplifier le module /logistique et industrialiser la génération de documents PDF (bons) pour préparation, commande, et livraison.

ÉTAT (OBLIGATOIRE - lecture /doc):
1) Lire /doc dans l'ordre chronologique et résumer en 10 lignes max les décisions existantes impactant /logistique.
2) Identifier toute mention de "bons", "logistique", "workflow commande", "pdf", "impression".
3) Lister les fichiers déjà existants côté logistique (contrôleurs, services, templates) et routes actuelles.

ANALYSE (OBLIGATOIRE - code existant):
1) Inspecter le contrôleur Logistique (routes existantes), notamment:
   - /logistique
   - /logistique/dashboard
   - /logistique/preparation
   - /logistique/commande/{id}/bon-livraison (HTML aujourd'hui)
   (Le contrôleur actuel utilise IsGranted ROLE_DMAX et CSRF sur les POST.) 
2) Inspecter le service LogistiqueService (workflow commande_lifecycle, transitions, repositories).
3) Vérifier composer.json: bibliothèque PDF déjà présente ou non (Dompdf, TCPDF, Snappy/wkhtmltopdf, etc.).
4) Rechercher dans les templates Twig tout lien vers logistique_dashboard / logistique_preparation (menus, index, etc.) et lister occurrences.
5) Identifier le modèle de données: entité Commande et sa relation aux "produits" (attention: nombre de produits variable; on vise 3 sections produit sur le bon de préparation).

OBJECTIF (TÂCHES + CRITÈRES DE SUCCÈS):
A) Supprimer les pages:
   - Supprimer les routes et vues /logistique/dashboard et /logistique/preparation.
   - Supprimer/adapter les templates correspondants et tous liens/menu pointant vers ces pages.
   Critère: aucune route Symfony exposée sur ces endpoints, aucun template orphelin référencé, navigation cohérente.

B) Bon de commande (PDF) accessible quel que soit le statut:
   - Ajouter une route GET (protégée ROLE_DMAX) pour générer un "bon de commande" en PDF pour une Commande donnée.
   - IMPORTANT: accessible "quel que soit le statut" => ne jamais conditionner à un état workflow.
   - Contenu: récapitulatif complet de la commande avec toutes les infos disponibles (client/bénéficiaire, adresse, créneau, produits, etc. selon modèle).
   - Mise en page: réserver une zone clairement délimitée (vide) pour coller des étiquettes d'inventaire physique (ex: bloc à droite ou en bas, hauteur suffisante).
   Critères: PDF téléchargé/affiché avec Content-Type application/pdf, nom de fichier explicite, rendu stable.

C) Bons de préparation (PDF):
   - Ajouter une route GET (ROLE_DMAX) pour générer un "bon de préparation" PDF.
   - Mise en page:
     1) En haut, petit: infos générales commande (id, bénéficiaire, date/créneau, localisation/porte/étage si pertinent).
     2) Ensuite, en gros caractères: 3 sections "Produit 1/2/3" destinées à être imprimées, découpées, collées sur les produits.
        Chaque section doit afficher: nom article, numéro inventaire, porte, étage.
     3) Si <3 produits: afficher des sections vides "à compléter".
     4) Si >3 produits: définir un comportement (au choix mais justifié): pagination (3 par page) ou pages supplémentaires; pas de perte d'info.
   Critères: lisibilité à distance, sections bien séparées (traits de coupe/marges), PDF stable.

D) Bon de livraison (PDF) = attestation:
   - Ajouter une route GET (ROLE_DMAX) pour générer un "bon de livraison" PDF.
   - Le document reprend STRICTEMENT le texte fourni (ci-dessous) avec des champs/espaces pour remplir à la main:
     - Nom / Prénom
     - Service
     - Description précise – N° inventaire le cas échéant (liste des biens de la commande)
     - Fait à … / Le …
     - Signatures
   - Ne pas "réécrire" juridiquement le texte; seulement mise en forme.
   - Inclure la liste des biens (description + inventaire si présent) à l'endroit prévu.
   Texte à intégrer:
   ---
   Attestation de cession à titre gratuit de mobilier

   Je soussigné(e) :
   Nom / Prénom :
   Service :

   Déclare avoir reçu à titre gratuit le(s) bien(s) suivant(s) :
   (Description précise – N° inventaire le cas échéant)

   Je reconnais que :

   Le bien est cédé en l'état, sans garantie de fonctionnement ou de conformité.

   La CPAM est dégagée de toute responsabilité liée à l'usage, au transport ou à la détention du bien à compter de sa remise.

   Le transfert de propriété et de responsabilité est effectif à la date de signature du présent document.

   Le bien est destiné à un usage personnel.

   Fait à …………………
   Le …………………

   Signature du bénéficiaire
   (Signature de l'administration)
   ---

CONTRAINTES CRITIQUES:
- Symfony 6.4, PHP 8.1, PSR-12, attributs PHP.
- Compatibilité ascendante: pas de casse sur /logistique (index) et les actions POST existantes.
- Déploiement <48h: privilégier un ajout minimaliste et robuste.
- Sécurité: routes PDF sous ROLE_DMAX; pas de fuite de données; pas d'accès public.
- Performance: génération PDF raisonnable; éviter N+1 sur produits (charger relations correctement).

DÉCISIONS TECHNIQUES ATTENDUES (à documenter dans le PR):
- Choix de la lib PDF (réutiliser l'existant si déjà présent; sinon ajouter dépendance justifiée).
- Où placer la génération: service dédié (ex: DocumentPdfGenerator) + templates Twig, plutôt que logique dans contrôleur.
- Stratégie pagination bon préparation >3 produits.

LIVRABLES (OBLIGATOIRES):
- Fournir les fichiers modifiés avec leur CONTENU COMPLET.
- Liste des routes ajoutées/supprimées.
- Templates Twig PDF (ou HTML converti) + styles (inline CSS si nécessaire pour compat).
- Notes d'implémentation: points d'attention + raisons des choix.

TESTS (SPEC):
- Tests fonctionnels Symfony (WebTestCase):
  1) /logistique/dashboard et /logistique/preparation => 404 (ou route inexistante).
  2) Routes PDF => 200 pour un utilisateur ROLE_DMAX, et 403/302 sinon.
  3) Routes PDF => Content-Type application/pdf, et réponse non vide.
  4) Bon de commande accessible pour au moins 2 statuts de commande différents (ex: validée/prête), sans erreur.
  5) Bon de préparation: vérifie présence des libellés Produit 1/2/3 et des champs inventaire/porte/étage (même si vides).
  6) Bon de livraison: vérifie présence de la phrase "Attestation de cession à titre gratuit de mobilier" et des champs "Nom / Prénom :" etc.
- Si la lib PDF ne permet pas d'assert sur texte facilement, au minimum: headers + taille + smoke test + (optionnel) extraction texte si disponible.

FINITION:
- Aucun endpoint mort, aucun template non référencé, aucun warning/erreur phpstan/psalm si présent, tests passent.
