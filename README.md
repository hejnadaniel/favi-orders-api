# Partnerské objednávky

Služba pro příjem objednávek z eshopů partnerů. Objednávku ověří, uloží
a dovolí u ní později posunout termín dodání.

Symfony 8.1 na PHP 8.4, persistence přes Doctrine ORM 3 do PostgreSQL 16.
Strojově čitelný kontrakt je v [`docs/openapi.yaml`](docs/openapi.yaml)
a vznikl dřív než kód.

## Endpointy

| Metoda | Cesta | Co udělá |
|---|---|---|
| POST | `/api/v1/partners/{partnerId}/orders` | založí objednávku |
| PUT | `/api/v1/partners/{partnerId}/orders/{orderId}/delivery-date` | posune termín dodání |
| GET | `/api/v1/partners/{partnerId}/orders/{orderId}` | vrátí uloženou objednávku |

Kdo je partner, plyne výhradně z cesty. Tělo requestu identifikátor partnera
nikdy neobsahuje, aby nemohl kolidovat s tím v URL.

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

Posun termínu:

```http
PUT /api/v1/partners/PRT-1042/orders/WEB-104172/delivery-date
Content-Type: application/json

{"expectedDeliveryDate": "2026-10-19"}
```

Návratové kódy:

| Kód | Situace |
|---|---|
| 201 | založeno, odpověď nese hlavičku `Location` |
| 200 | přečteno nebo posunuto |
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

Doménu v poli `type` nastavuje proměnná `PROBLEM_TYPE_BASE_URI`, takže si
každé prostředí ukazuje na vlastní dokumentaci chyb.

## Proč to vypadá takhle

**Termín dodání jako samostatný zdroj s PUT.** Tělo requestu je celá
reprezentace toho zdroje, takže PUT sedí a idempotence není slib, ale
vlastnost metody. Vyzkoušel jsem i obecný `PATCH` nad objednávkou s merge
patchem. Dává smysl ve chvíli, kdy je měnitelných polí víc; u jediného pole
se platí za pružnost, kterou nikdo nepoužije, a k tomu se otevírá otázka, co
znamená `null` v těle. Až polí přibude, je přechod na `PATCH` na pár řádků.

**Čtecí endpoint nad rámec zadání.** Odpověď 201 má podle HTTP ukazovat na
nově vzniklý zdroj. Odkaz, který skončí na 405, je rozbitý slib, takže jsem
raději dopsal čtení. Vedlejší efekt je, že integrační testy si ověřují
uložený stav přes veřejné API a nešťourají v tabulkách.

**Částky bez matematické knihovny.** Peníze putují jako `string` od requestu
až do sloupce `numeric(14, 2)` a nikde se nepřevádějí na `float`. Hodnotový
objekt `DecimalAmount` si v konstruktoru ohlídá tvar a doplní desetinná
místa; jeho konstanty zároveň řídí mapování sloupce i validační pravidlo, aby
ta čísla byla v kódu jen jednou. Sáhnout po `brick/math` by přineslo typovou
pojistku proti float aritmetice, ale zaplatilo by se závislostí a k ní
vlastním Doctrine typem, normalizerem a constrainty. Při pouhém ukládání se to
nevyplatí. Jakmile by se s částkami začalo počítat, přepnul bych na ni.

**Žádná měna ani daň.** Zadání o nich mlčí. Uložit cenu s tiše předpokládanou
korunou znamená, že první partner účtující v eurech celý model zboří a oprava
už nebude refaktor, ale migrace dat. Kdyby se měna dodělávala, patří na
objednávku, řádky ji přebírají a kurz se musí zmrazit k okamžiku vzniku, jinak
by pozdější přepočet měnil uzavřené účetnictví.

**Celková částka se neověřuje.** Ukládá se přesně tak, jak dorazila. Rozdíl
oproti součtu řádků sám o sobě nic nedokazuje; stát za ním může poštovné,
množstevní sleva nebo zaokrouhlení, které v datech nevidíme. V provozu bych na
ten rozdíl pověsil metriku, ale request bych propustil.

**Termín v minulosti projde.** Zpětné opravy jsou běžné a posoudit úmysl je
práce klienta, ne API. Co neprojde, je datum, které v kalendáři neexistuje:
`2026-02-30` se kontroluje ještě jako text, protože jinak ho PHP potichu
přesune na březen.

**Identifikátor partnera v cestě.** V ostrém provozu by ho dodával
autentizační token a klient by ho neposílal vůbec. Autentizace je ze zadání
vyňatá, takže zůstává v URL.

**Bez auditní stopy.** V B2B integraci by patřila mezi první doplňky, jenže
bez přihlášení by sloupec "kdo" držel konstantu. Prázdná tabulka a mrtvá třída
navíc nikomu nepomůžou.

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
zadání vyžádalo. Integrační testy projdou celý HTTP cyklus proti Postgresu pro
každý dokumentovaný návratový kód a jsou tím bonusem navíc. Každý databázový
test je obalený transakcí, která se na konci zahodí, takže na pořadí nezáleží.

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
implementací a nemusí startovat kernel. Objednávku bez jediného produktu nejde
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

Z PHP 8.4 se používá, co dává smysl: `readonly` u datových tříd, asymetrická
viditelnost u entit místo getterů, `declare(strict_types=1)` v každém souboru
a `final` jako výchozí stav. PHPStan jede na nejvyšším levelu se strict rules
a bez baseline, formátování hlídá php-cs-fixer nad `@PER-CS2.0`,
`@PHP84Migration` a `@Symfony:risky`. Komentáře jsou výjimka, docblocky nesou
jen typy, které nativní signatura neumí zapsat.
