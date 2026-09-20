# Partnerské objednávky

Služba pro příjem objednávek z eshopů partnerů. Objednávku zvaliduje, uloží
a dovolí u ní později posunout datum doručení.

Symfony 8.1 na PHP 8.4, persistence přes Doctrine ORM 3 do PostgreSQL 16.
OpenAPI kontrakt je v [`docs/openapi.yaml`](docs/openapi.yaml) a vznikl dřív
než kód.

## Endpointy

| Metoda | Cesta | Co udělá |
|---|---|---|
| POST | `/api/v1/partners/{partnerId}/orders` | založí objednávku |
| PUT | `/api/v1/partners/{partnerId}/orders/{orderId}/delivery-date` | změní datum doručení |
| GET | `/api/v1/partners/{partnerId}/orders/{orderId}` | vrátí uloženou objednávku |

Partnera určuje výhradně URL. Tělo requestu jeho identifikátor nikdy
neobsahuje, aby nemohl kolidovat s tím v cestě.

Založení objednávky:

```http
POST /api/v1/partners/PRT-1042/orders
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

Změna data doručení:

```http
PUT /api/v1/partners/PRT-1042/orders/WEB-104172/delivery-date
Content-Type: application/json

{"expectedDeliveryDate": "2026-10-19"}
```

Stavové kódy:

| Kód | Situace |
|---|---|
| 201 | založeno, odpověď nese hlavičku `Location` |
| 200 | vráceno GET, nebo úspěšně změněno |
| 400 | tělo není platný JSON |
| 404 | objednávka neexistuje, nebo patří jinému partnerovi |
| 409 | dvojice `(partnerId, orderId)` je obsazená, původní záznam zůstává |
| 415 | jiný Content-Type než JSON |
| 422 | tělo porušuje kontrakt, odpověď ukazuje na konkrétní pole |
| 500 | neočekávaný pád, detaily jen v logu |

Chyby vracíme jako Problem Details podle RFC 9457:

```json
{
  "type": "https://api.favi.test/problems/validation-failed",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The request body did not pass validation.",
  "instance": "/api/v1/partners/PRT-1042/orders",
  "errors": [
    {"pointer": "/products/0/quantity", "message": "This value should be between 1 and 1000000."}
  ]
}
```

Základ URI v poli `type` nastavuje proměnná `PROBLEM_TYPE_BASE_URI`, takže si
každé prostředí může ukazovat na vlastní dokumentaci chyb.

## Proč to vypadá takhle

**PUT na datum doručení, ne PATCH na objednávku.** Mění se jedno pole a tělo
requestu je celá jeho hodnota, takže PUT sedí a idempotence plyne z metody.
Až bude polí víc, dává smysl přejít na PATCH.

**GET navíc.** Zadání ho nechce, ale odpověď 201 vrací hlavičku `Location`
a ta musí někam vést. Vedle toho díky němu testuju přes API místo přes
databázi.

**Částky jako `string`, bez `brick/math`.** V databázi `numeric(14, 2)`, nikde
`float`. Formát a rozsah hlídá `DecimalAmount`. Knihovna by dala typovou
pojistku proti float aritmetice, ale musel bych k ní dopsat Doctrine typ,
normalizer a validátory. Na ukládání to nestojí za to, na počítání ano.

**Bez měny a DPH.** Zadání je nezmiňuje. Tiše předpokládat korunu je horší než
je nemít: první partner účtující v eurech znamená migraci dat, ne refaktor.

**`totalValue` se ukládá, jak přijde.** Nepočítám ho ze součtu řádků ani ho
proti němu neověřuju. Rozdíl může být sleva nebo poštovné. V provozu bych na
něj dal metriku, ale request pustil.

**Datum v minulosti projde.** Zpětné opravy jsou běžné a posoudit úmysl je
práce klienta. Datum, které v kalendáři neexistuje, jako `2026-02-30`, ale
neprojde.

**`partnerId` v URL.** V ostrém provozu by šel z tokenu a klient by ho
neposílal. Autentizace je mimo zadání.

## Rozjetí

Stačí PHP 8.4 s `pdo_pgsql`, `intl` a `mbstring`, Composer a Docker. Symfony
CLI potřeba není, aplikace běží i na vestavěném serveru. Node se hodí jen pro
kontrolu OpenAPI přes `npx`.

```bash
docker compose up -d
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php -S 127.0.0.1:8000 -t public
```

Máte-li port 5432 zabraný vlastním Postgresem, přemapujte kontejner jinam
a stejnou adresu zapište do `.env.local` a `.env.test.local`:

```bash
POSTGRES_HOST_PORT=5434 docker compose up -d
```

## Kontrola kvality

```bash
composer db:test:reset
composer check
```

První příkaz postaví testovací databázi a stačí jednou. Druhý projede
php-cs-fixer, PHPStan na `level: max` bez baseline, Spectral nad kontraktem
a PHPUnit. Totéž hlídá pipeline na každý push i pull request.

Testů je 68. Unit testy nad doménou a službami běží v milisekundách a míří na
hraniční hodnoty, duplicity, idempotenci a výjimky; to je ta část, kterou si
zadání vyžádalo. Funkční testy přes `WebTestCase` projdou celý HTTP cyklus proti Postgresu pro
každý dokumentovaný stavový kód a jsou tím bonusem navíc. Každý databázový
test běží v transakci, která se na konci rollbackne, takže na pořadí nezáleží.

## Jak je to poskládané

```
Controller -> Factory -> Handler -> rozhraní repozitáře
```

```
src/
  Controller/Api/V1/   OrderController
  Dto/                 vstupy služeb, v Request/ a Response/ příchozí a odchozí tvary
  Entity/              Order, OrderProduct
  EventListener/       ProblemDetailsListener
  Exception/           doménové výjimky a ProblemInterface
  Factory/             překlady mezi vrstvami
  Repository/          OrderRepositoryInterface a Doctrine adapter
  Service/             tři handlery a CalendarDateParser
  Validator/           ValidDecimalAmount
  ValueObject/         DecimalAmount, ProductLine
```

Handlery znají jen rozhraní repozitáře, takže unit testy pracují s in-memory
implementací a nestartují kernel. Objednávku bez jediného produktu nejde
zkonstruovat, množství pod jedna neprojde konstruktorem řádku a `DecimalAmount`
nepustí dál nic, co se nevejde do sloupce. Statická metoda není v `src/` ani
jedna.

Na duplicitu dohlíží unikátní index, což je jediná pojistka, která obstojí při
souběžných requestech; tvar těla řeší DTO a doména si hlídá vlastní pravidla.
Veškeré výjimky sbírá jediný listener, takže nový typ chyby znamená novou
výjimku se třemi metodami a nic jiného. Interní klíč je UUID v7, navenek se
objednávka adresuje dvojicí `(partnerId, orderId)`. Řádky nesou sloupec
`position`, aby se četly přesně v pořadí odeslání.

## Konvence

Z PHP 8.4 se používá, co dává smysl: `readonly` u DTO, asymetrická
viditelnost u entit místo getterů, `declare(strict_types=1)` v každém souboru
a `final` jako výchozí stav. PHPStan jede na nejvyšším levelu se strict rules
a bez baseline, formátování hlídá php-cs-fixer nad `@PER-CS2.0`,
`@PHP84Migration` a `@Symfony:risky`. Komentáře jsou výjimka, docblocky nesou
jen typy, které nativní signatura neumí zapsat.
