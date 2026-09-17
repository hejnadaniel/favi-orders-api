# Time log

Budget: 8 hours. Wall-clock time per phase, rounded to 5 minutes.

| Phase | Time |
|---|---|
| Reading the assignment, API and model design, project skeleton, tooling (PHPStan, php-cs-fixer, PHPUnit, Spectral), CI | 0h 45m |
| OpenAPI 3.1 contract and example payloads | 0h 10m |
| Domain model, migration, Doctrine adapter, unit tests (including the ORM 3.7 / DBAL 4.4 / doctrine-bridge 8.0 incompatibility that forced the move to Symfony 8.1) | 0h 30m |
| Application use cases and their unit tests | 0h 10m |
| HTTP layer: controllers, request and response DTOs, problem details, merge-patch support, functional tests | 0h 40m |
| README, manual curl smoke test, final quality gate | 0h 15m |
| **Total** | **2h 30m** |

Not done, on purpose: authentication, currency, audit trail, list endpoint.
See "What I would do next" in the README.
