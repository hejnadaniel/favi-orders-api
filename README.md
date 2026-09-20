# Orders REST API

REST API, kterým partneři posílají objednávky a mění u nich datum doručení.
PHP 8.4, Symfony 8.1, Doctrine ORM 3, PostgreSQL 16.

Kontrakt: [`docs/openapi.yaml`](docs/openapi.yaml), psaný před kódem.
Ukázková těla: [`docs/examples/`](docs/examples).

## Rozhodnutí

- **PUT, ne PATCH.** Datum doručení má vlastní sub-resource, tělo je jeho celá
  reprezentace. Zkoušel jsem i `PATCH /orders/{id}` s merge patchem; vyplatí se
  až u více měnitelných polí, u jednoho se platí za obecnost, kterou nikdo
  nevyužije. Kdyby polí přibylo, vrátil bych se k PATCH.
- **GET navíc.** Zadání chce dva endpointy. `201` má nést `Location` a
  `Location` vracející 405 je rozbitý kontrakt, takže čtení dopisuji.
- **Peníze bez knihovny.** Částky jsou `string` end to end, v databázi
  `numeric(14, 2)`, nikde `float`. `brick/math` by dal typovou záruku, že nad
  částkou nikdo nezavolá float operaci, ale stojí závislost navíc plus vlastní
  Doctrine typ, normalizer a constrainty. Na ukládání bez počítání to nestojí
  za to. Jakmile by se částky sčítaly nebo násobily sazbou, přešel bych na ni.
- **Bez měny a DPH.** Zadání je nezmiňuje. Cena s implicitně předpokládanou
  měnou se rozbije o prvního partnera v eurech a oprava je pak migrace dat, ne
  změna modelu. Správně měnu nese objednávka, řádky ji dědí a kurz se fixuje
  k okamžiku vzniku.
- **`totalValue` se ukládá, jak přišla.** Nedopočítává se ani neporovnává se
  součtem řádků, protože rozdíl nemusí být chyba, může jít o slevu nebo dopravu.
  V provozu bych na rozdíl logoval metriku, ale request nezamítal.
- **Datum v minulosti projde.** Zpětné opravy jsou legitimní, kontrolu úmyslu
  má dělat klient. Neexistující datum jako `2026-02-30` neprojde:
  validuje se jako řetězec dřív, než ho PHP tiše překlopí na březen.
- **partnerId v URL.** V provozu by ho dodával token, ne klient. Autentizace je
  mimo zadání.
- **Bez audit logu.** V B2B by patřil mezi první věci, ale bez autentizace by
  bylo "kdo" konstanta, takže mrtvá tabulka.

## Spuštění

Potřeba je PHP 8.4 (`pdo_pgsql`, `intl`, `mbstring`), Composer, Docker a pro
`openapi:lint` i Node. Symfony CLI potřeba není.

```bash
docker compose up -d
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php -S 127.0.0.1:8000 -t public
```

Při obsazeném portu 5432 spusťte databázi jinde a adresu zapište do
`.env.local` a `.env.test.local`:

```bash
POSTGRES_HOST_PORT=5434 docker compose up -d
```

## Testy

```bash
composer db:test:reset   # jednou, kvůli integračním testům
composer check
```

`composer check` spustí php-cs-fixer, PHPStan na `level: max` se strict rules
a bez baseline, Spectral nad OpenAPI a PHPUnit. Stejný příkaz běží v CI proti
Postgresu v kontejneru.

## API

Vše pod `/api/v1/partners/{partnerId}`. Partnera určuje URL, tělo ho neopakuje.

```http
POST /api/v1/partners/{partnerId}/orders
Content-Type: application/json

{
  "orderId": "WEB-104172",
  "expectedDeliveryDate": "2026-10-05",
  "totalValue": "47940.00",
  "products": [
    {"productId": "SOFA-OSLO-3S", "name": "Oslo three-seater sofa, grey", "price": "18990.00", "quantity": 2}
  ]
}
```

```http
PUT /api/v1/partners/{partnerId}/orders/{orderId}/delivery-date
Content-Type: application/json

{"expectedDeliveryDate": "2026-10-19"}
```

```http
GET /api/v1/partners/{partnerId}/orders/{orderId}
```

| Stav | Kdy |
|---|---|
| 201 | vytvořeno, plus hlavička `Location` |
| 200 | přečteno nebo změněno |
| 400 | rozbitý JSON |
| 404 | objednávka neexistuje, i když patří jinému partnerovi |
| 409 | dvojice `(partnerId, orderId)` už existuje, nic se nepřepíše |
| 415 | jiný Content-Type než JSON |
| 422 | porušený kontrakt, s ukazatelem na pole |
| 500 | neočekávaná chyba, tělo bez detailů, ty jdou do logu |

Chyby jsou Problem Details podle RFC 9457:

```json
{
  "type": "https://api.favi.test/problems/validation-failed",
  "title": "Validation Failed",
  "status": 422,
  "detail": "One or more fields are invalid.",
  "instance": "/api/v1/partners/PRT-1042/orders",
  "errors": [
    {"pointer": "/products/0/quantity", "message": "This value should be between 1 and 1000000."}
  ]
}
```

Základ URI v poli `type` řídí proměnná `PROBLEM_TYPE_BASE_URI`.

## Architektura

```
Controller -> Factory (request na vstup služby) -> Handler -> Repository interface
```

```
src/
  Controller/Api/V1/   OrderController
  Dto/                 vstupy služeb, v Request/ a Response/ příchozí a odchozí DTO
  Entity/              Order, OrderProduct
  EventListener/       ProblemDetailsListener
  Exception/           doménové výjimky a ProblemInterface
  Factory/             překlady mezi DTO a entitami
  Repository/          OrderRepositoryInterface a Doctrine adapter
  Service/             tři handlery a CalendarDateParser
  Validator/           ValidDecimalAmount
  ValueObject/         DecimalAmount, ProductLine
```

- Služby závisí na rozhraní repozitáře, ne na Doctrine. Unit testy proto jedou
  na in-memory implementaci a nestartují kernel.
- Entity si hlídají invarianty. Objednávka odmítne prázdné produkty,
  `ProductLine` množství pod jedna, `DecimalAmount` cokoliv, co není nezáporné
  desetinné číslo do rozsahu sloupce.
- V `src/` není jediná statická metoda.
- Validace na třech úrovních: DTO vrací 422, doména hlídá invarianty, unikátní
  index obstojí při souběžných requestech.
- Všechny chyby jdou přes jeden listener. Nový druh chyby znamená novou výjimku
  se třemi metodami, do listeneru se nesahá.
- Interní klíč je UUID v7, navenek se používá `(partnerId, orderId)`.
- Produktové řádky mají `position`, takže se čtou v pořadí odeslání.

## Coding standard

PHP 8.4 naplno: `readonly` DTO, asymetrická viditelnost u entit místo getterů,
`declare(strict_types=1)` všude, `final` jako výchozí stav. PHPStan `level: max`
se strict rules a bez baseline, php-cs-fixer s `@PER-CS2.0`, `@PHP84Migration`
a `@Symfony:risky`. Komentáře skoro nejsou, docblocky nesou jen typy, které
nativní signatura neumí.
