# AGENTS.md — CARAMEL

## Contexte
Application Symfony 6.4 / PHP 8.2 de don de mobilier CPAM (anti-gaspillage).
Repo cloné dans `/workspace/caramel`.

## À lire AVANT de coder (ordre obligatoire)
1. `doc/cahier-des-charges.md` — exigences fonctionnelles complètes
2. `doc/phases.md` — découpage en 4 phases
3. `doc/20260220_1444_DIRECTEUR_PHASE1_SOCLE_TECHNIQUE.md` — architecture Phase 1 (bloquant)

## Contraintes impératives
- `declare(strict_types=1)` en tête de TOUS les fichiers PHP
- Jamais de logique métier dans les Controller (→ Service + Interface)
- Classes ≤ 150 lignes
- Standards PSR-12
- Structure : `src/Controller/{Admin,Dmax,Shop}/`, `src/Service/`, `src/Interface/`, `src/DTO/`, `src/Entity/`
- Créer les Interfaces AVANT les implémentations

## Commandes à utiliser
```bash
# Migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Validation schéma
php bin/console doctrine:schema:validate

# Cache
php bin/console cache:clear

# Tests
php bin/phpunit
