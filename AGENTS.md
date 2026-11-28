# DoctrineQuerySearchHelper

PHP library (Composer package `exegeseit/doctrinequerysearch-helper`) that builds dynamic
`WHERE` / `ORDER BY` clauses for Doctrine ORM (DQL) and Doctrine DBAL query builders from a
structured `$search` array. Not a Symfony app: no kernel, no web server, no database.

## Layout

- `src/QueryClauseBuilder.php` — entry point: `QueryClauseBuilder::getInstance($qb)` picks the
  DQL or DBAL builder from the query builder type.
- `src/Builder/` — `AbstractClauseBuilderProcessor` (shared parsing, sort normalisation),
  `AbstractDoctrineClauseBuilder`, `DQLClauseBuilder`, `DBALClauseBuilder`.
- `src/SearchFilter.php` — helpers that build tokenized search keys (`equal`, `like`, `null`,
  `greaterOrEqual`, `andOr`…). `src/SearchHelper.php`, `src/FilterExprFn.php` — utilities.
- `src/ValueObject/` — `WhereCriteria`, `SortCriteria`.
- `tests/` — PHPUnit 11. `tests/Fixtures/` holds Doctrine entities/repositories used by the tests.

## Commands

No PHP on the host (WSL): every command runs in an ephemeral container of the shared PHP
toolbox through the `dphp` script (`~/.local/bin/dphp`, image built from `~/docker/php-toolbox`).
Never install PHP/Composer on the host.

```bash
dphp composer install
dphp composer test        # PHPUnit
dphp composer lint        # PHPStan + Rector (dry-run) + ECS
dphp composer lint:fix    # Rector + ECS fixes
DPHP_VERSION=8.2 dphp composer test   # minimum supported PHP (image local/php-toolbox:8.2, built on first use)
dphp --rebuild            # rebuild the toolbox image (e.g. after adding an extension)
```

`composer.lock` is not committed (library). Tool caches live in `tmp/` (gitignored).

## Constraints

- Supported range is declared in `composer.json`: PHP `>=8.2`, `doctrine/orm ^2.9 | ^3.0`,
  `nette/utils ^3.2 || ^4.0`. Code must work across the whole range — no PHP 8.3+ syntax,
  no ORM-3-only API without a fallback.
- CI (`.github/workflows/ci.yml`) runs PHPUnit on PHP 8.2–8.5 × lowest/highest dependencies,
  plus `composer lint` on PHP 8.4. A change is done when that matrix is green.
- Tests only need a query builder: they use `pdo_sqlite` in memory as the DBAL platform and
  assert on `getSQL()`; nothing is executed against a database.

## Code style

- `declare(strict_types=1);` in every file.
- PHPStan level max with strict rules and 100 % type coverage (return, param, property).
  Do not grow `phpstan-baseline.neon`; fix the types instead.
- ECS: PSR-12 + PER-CS, **Yoda conditions** (`null === $x`), no unused imports.
- Run `dphp composer lint:fix` then `dphp composer lint` before committing.

## Git

- Commit messages in English, conventional prefix (`feat:`, `fix:`, `chore:`, `ci:`, `test:`, `docs:`).
- Work on a branch and open a PR against `main`; do not push to `main` directly.
- Line endings are LF (`.gitattributes`). `.idea/` is not tracked.
