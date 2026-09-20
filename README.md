# FAVI Partner Orders API

REST API through which partner shops submit their orders and later change the
expected delivery date. PHP 8.4, Symfony 8.1, Doctrine ORM 3, PostgreSQL 16.

Contract: [`docs/openapi.yaml`](docs/openapi.yaml) (OpenAPI 3.1, written before
the code). Example payloads: [`docs/examples/`](docs/examples).

## Requirements

- PHP 8.4 with `pdo_pgsql`, `intl`, `mbstring`
- Composer 2
- Docker (local PostgreSQL)
- Node 22, only for `composer openapi:lint` (runs Spectral through `npx`)

## Run

```bash
docker compose up -d
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php -S 127.0.0.1:8000 -t public
```

If port 5432 is taken on your machine, start the database with
`POSTGRES_HOST_PORT=5434 docker compose up -d` and put the matching
`DATABASE_URL` into `.env.local` and `.env.test.local`.

## Quality gate

```bash
composer check
```

Runs, in order:

- `composer lint` - php-cs-fixer, PER-CS 2.0 + Symfony rule sets, dry run
- `composer stan` - PHPStan level max with strict rules, no baseline
- `composer openapi:lint` - Spectral on `docs/openapi.yaml`
- `composer test` - PHPUnit, unit and functional suites

Functional tests need the test database once:

```bash
composer db:test:reset
```

`dama/doctrine-test-bundle` wraps every test in a transaction and rolls it
back, so tests never see each other's rows and the suite is order-independent.

CI (`.github/workflows/ci.yml`) runs the same `composer check` against a
PostgreSQL service container on every push to `main` and every pull request.

## API tour

All endpoints live under `/api/v1/partners/{partnerId}`. The partner is
identified by the URL only; the body never repeats it.

### Create an order

```bash
curl -sS -i -X POST http://127.0.0.1:8000/api/v1/partners/PRT-1042/orders \
  -H 'Content-Type: application/json' \
  --data @docs/examples/create-order.json
```

```http
HTTP/1.1 201 Created
Content-Type: application/json
Location: /api/v1/partners/PRT-1042/orders/WEB-104172

{"partnerId":"PRT-1042","orderId":"WEB-104172","expectedDeliveryDate":"2026-10-05","totalValue":"47940.00","products":[{"productId":"SOFA-OSLO-3S","name":"Oslo three-seater sofa, grey","price":"18990.00","quantity":2},{"productId":"CHAIR-VELVET-GRN","name":"Velvet dining chair, green","price":"2490.00","quantity":4}],"createdAt":"2026-09-17T15:56:42+00:00","updatedAt":"2026-09-17T15:56:42+00:00"}
```

Sending the same `(partnerId, orderId)` again never overwrites anything:

```json
{"type":"https://api.favi.test/problems/duplicate-order","title":"Duplicate Order","status":409,"detail":"Order \"WEB-104172\" already exists for partner \"PRT-1042\".","instance":"/api/v1/partners/PRT-1042/orders"}
```

Validation failures carry one JSON Pointer per field:

```json
{"type":"https://api.favi.test/problems/validation-failed","title":"Validation Failed","status":422,"detail":"One or more fields are invalid.","instance":"/api/v1/partners/PRT-1042/orders","errors":[{"pointer":"/totalValue","message":"This value should be a non-negative decimal with at most 12 integer and 2 fractional digits."},{"pointer":"/products/0/quantity","message":"This value should be greater than or equal to 1."}]}
```

### Change the expected delivery date

```bash
curl -sS -i -X PATCH http://127.0.0.1:8000/api/v1/partners/PRT-1042/orders/WEB-104172 \
  -H 'Content-Type: application/merge-patch+json' \
  --data @docs/examples/patch-order.json
```

Returns `200 OK` with the whole updated order. `application/json` is accepted
as well. Fields other than `expectedDeliveryDate` are rejected with `422`, an
unknown order (including one that belongs to another partner) with `404`.

### Read an order

```bash
curl -sS http://127.0.0.1:8000/api/v1/partners/PRT-1042/orders/WEB-104172
```

| Situation | Status |
|---|---|
| Created | 201 + `Location` |
| Read or updated | 200 |
| Malformed JSON | 400 |
| Unknown order, unknown route | 404 |
| Method not supported on the path | 405 + `Allow` |
| Duplicate `(partnerId, orderId)` | 409 |
| `Content-Type` other than JSON | 415 |
| Schema violation, unknown field | 422 + `errors[]` |
| Anything unexpected | 500, opaque body, details only in logs |

Every error is `application/problem+json` (RFC 9457). The base of the `type`
URI comes from `PROBLEM_TYPE_BASE_URI`, so staging and production can publish
their own problem documentation.

## Architecture

```
src/
  Controller/Api/V1/  OrderController: one class for the order resource
  Dto/                CreateOrder, ChangeOrderDeliveryDate (service input)
    Request/          inbound DTOs with validation constraints
    Response/         outbound DTOs
  Entity/             Order, OrderProduct
  EventListener/      ProblemDetailsListener
  Exception/          domain exceptions and the ProblemInterface they implement
  Factory/            request DTO -> service DTO, entity -> response DTO
  Repository/         OrderRepositoryInterface and its Doctrine adapter
  Service/            CreateOrderHandler, ChangeOrderDeliveryDateHandler,
                      GetOrderHandler, CalendarDateParser
  Validator/          ValidDecimalAmount compound constraint
  ValueObject/        DecimalAmount, ProductLine
```

Folders are named after what the classes inside are, so a handler is never
mistaken for a DTO. There are no static methods anywhere in `src/`: objects are
built with constructors, and anything that needs collaborators (clock, parser)
is an injected service.

- Services depend on `OrderRepositoryInterface`, not on the Doctrine class
  behind it; unit tests use an in-memory implementation and never boot the
  kernel.
- Entities own their invariants: the `Order` constructor refuses an empty
  product list, `ProductLine` refuses a quantity below one, `DecimalAmount`
  refuses anything that is not a non-negative decimal with at most two
  fractional digits. The only mutation is `Order::changeExpectedDeliveryDate()`.
- The controller maps an HTTP request to an application DTO and an entity to a
  response; nothing else. One class covers the order resource, one method per
  operation.
  `#[MapRequestPayload]` does deserialization and validation, and the
  translation itself lives in injected factories rather than in the controller.
- One `kernel.exception` listener produces every error response. A domain
  exception becomes an API error by implementing `ProblemInterface` (slug,
  status, title); the listener never needs a `switch` over exception classes.
- Validation is layered: request DTO (shape, 422), domain (invariants), database
  (unique index, the only guard that holds under concurrent submissions).

## Tests

The part covered "as in standard development" is the application and domain
layer: the tests mirror the source folders, run in milliseconds,
use fakes rather than mocks, and cover happy paths, boundaries (amount and
quantity limits), idempotency, duplicate and not-found paths.

The bonus integration test is the functional suite in `tests/Functional`: full HTTP round trips against PostgreSQL for
all three operations, including every documented error response.
`tests/EventListener` pins the problem-details mapping itself.

```
composer test
PHPUnit 13, 67 tests, 0 failures
```

## Design decisions

**Hierarchical URLs.** `/partners/{partnerId}/orders/{orderId}` rather than a
flat composite. Partners are a domain concept; future partner-scoped resources
(products, shipments) slot in without restructuring, and once authentication
exists the partner segment is the natural place to enforce it.

**PATCH with JSON Merge Patch for the delivery date.** The assignment asks for
"an endpoint to change the delivery date". Two designs fit: an action endpoint
per field (`PUT .../delivery-date`) or a document-oriented `PATCH` on the
order. I chose `PATCH` with an RFC 7396 body: the second patchable field is one
property in `PatchOrderRequestDto`, not a new route, and the resource keeps one
canonical URL. Unknown fields are rejected so the schema stays explicit. The
trade-off is that per-field authorization would live in the service rather
than on the route.

**A `GET` that was not asked for.** `201 Created` must carry a `Location`, and
a `Location` that returns 405 is a broken contract. The read endpoint costs one
controller and one use case.

**Amounts are decimal strings, not floats and not a Money library.** Doctrine
`numeric(14, 2)` maps to a PHP `string`; `DecimalAmount` validates and
normalises it. Its precision and scale constants drive both the column mapping
and the request validation, so the accepted format is exactly what the column
can store without rounding or overflow. No `brick/money`: the assignment has no currency, so there is
nothing for a Money type to protect, and the library would add a custom
Doctrine type, a normalizer and validators for no benefit within 8 hours.

**No currency field.** The specification does not mention one and asks for raw
data to be stored as it arrived. Guessing `CZK` would be wrong for the first
foreign partner and a silent default is worse than an explicit gap. When it is
needed: ISO 4217 code on the order, inherited by the lines, plus a fixed
exchange rate at creation time for reporting.

**`totalValue` is stored as sent.** Not recomputed from the lines, not
verified against them. A mismatch is not necessarily an error (discounts,
rounding, shipping), so the right production follow-up is a metric on
`total != sum(price * quantity)`, not a rejection.

**Past delivery dates are accepted.** Backdating is a legitimate correction
and the API has no business rule that says otherwise. Overflowing dates such as
`2026-02-30` are rejected, though: `Assert\Date` runs before any conversion,
because `createFromFormat()` would silently roll them over to March.

**UUID v7 internal keys.** Time-ordered, so inserts append to the primary key
index instead of fragmenting it as v4 does. The partner-facing identity remains
`(partnerId, orderId)`; internal ids never appear in the API.

**Product lines keep their submission order.** An explicit `position` column
with an `OrderBy` mapping, so a read-back returns exactly what was sent.

**No authentication.** Explicitly out of scope. With it, the partner would come
from the credential (API key, OAuth scope) and the `{partnerId}` segment would
be validated against it or dropped from the URL entirely.

**Strictness.** `declare(strict_types=1)` everywhere, `final` by default,
readonly DTOs, asymmetric visibility on entities instead of getters, PHPStan at
`level: max` with strict rules and no baseline, php-cs-fixer on PER-CS 2.0.

## What I would do next

- Authentication (API key per partner) and rate limiting per partner.
- `Idempotency-Key` support on `POST` so retries after a network failure do not
  need to interpret `409`.
- Currency (Money pattern) and VAT breakdown, once the domain defines them.
- Observability: a metric on `totalValue` mismatches, request logging with
  partner and order ids.
- Domain events (`OrderPlaced`, `DeliveryDateChanged`) once a second consumer
  exists; today nothing would listen.
- List endpoint with cursor pagination, if FAVI needs read-back at scale.
