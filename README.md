# FAVI Partner Orders API

REST API through which partner shops submit their orders and later change the
expected delivery date. PHP 8.4, Symfony 8, Doctrine ORM 3, PostgreSQL 16.

This branch holds the project skeleton and tooling. The API itself lands in a
pull request on top of it.

## Requirements

- PHP 8.4 with `pdo_pgsql`, `intl`, `mbstring`
- Composer 2
- Docker (local PostgreSQL)
- Node 22 (only for `composer openapi:lint`, runs Spectral through `npx`)

## Run

```bash
docker compose up -d
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php -S 127.0.0.1:8000 -t public
```

## Quality gate

```bash
composer check
```

Runs, in order:

- `composer lint` - php-cs-fixer, PER-CS 2.0 + Symfony rule sets, dry run
- `composer stan` - PHPStan level max with strict rules, no baseline
- `composer openapi:lint` - Spectral on `docs/openapi.yaml`
- `composer test` - PHPUnit (unit + functional suites)

Functional tests need the test database:

```bash
composer db:test:reset
```

## Time spent

See [docs/time-log.md](docs/time-log.md).
