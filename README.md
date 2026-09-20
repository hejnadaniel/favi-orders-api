# Orders REST API

REST API, kterým partneři (obchody) posílají svoje objednávky a mění u nich
datum doručení. Postaveno na PHP 8.4, Symfony 8.1 a Doctrine ORM 3, databáze
PostgreSQL 16.

## Co je potřeba

- PHP 8.4 s rozšířeními `pdo_pgsql`, `intl` a `mbstring`
- Composer 2
- Docker, kvůli lokálnímu Postgresu
- Node 22, ale jen pro `composer openapi:lint`, který si Spectral stáhne přes `npx`

Symfony CLI potřeba není. Projekt schválně běží i na vestavěném PHP serveru,
aby se dal spustit bez instalace čehokoliv navíc.

## Spuštění

```bash
docker compose up -d
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php -S 127.0.0.1:8000 -t public
```

API pak poslouchá na `http://127.0.0.1:8000`.

Pokud máte port 5432 obsazený vlastním Postgresem, spusťte databázi na jiném
portu a stejnou adresu si zapište do `.env.local` a `.env.test.local`:

```bash
POSTGRES_HOST_PORT=5434 docker compose up -d
```

## Testy a kontrola kvality

Všechno jednou ranou:

```bash
composer check
```

Postupně se spustí:

- `composer lint` — php-cs-fixer v režimu dry-run, tedy kontrola stylu
- `composer stan` — PHPStan na `level: max` se strict rules, bez baseline
- `composer openapi:lint` — Spectral nad `docs/openapi.yaml`
- `composer test` — PHPUnit, unit i integrační testy

Hodí se ještě:

```bash
composer lint:fix        # automatická oprava stylu
composer db:test:reset   # drop + create + migrate testovací databáze
composer schema:validate # kontrola mapování proti schématu
```

Integrační testy potřebují testovací databázi, takže `composer db:test:reset`
je potřeba spustit jednou před prvním během.

CI (`.github/workflows/ci.yml`) pouští stejný `composer check` proti
Postgresu v kontejneru na každý push do `main` a na každý pull request.

## API ve zkratce

Kompletní kontrakt je v [`docs/openapi.yaml`](docs/openapi.yaml), psaný před
kódem. Ukázková těla requestů jsou v [`docs/examples/`](docs/examples).

Všechny endpointy žijí pod `/api/v1/partners/{partnerId}`. Partnera určuje
výhradně URL, tělo requestu ho nikdy neopakuje.

### Vytvoření objednávky

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

- `201 Created` plus hlavička `Location` a uložená objednávka v těle
- `409 Conflict` když dvojice `(partnerId, orderId)` už existuje. Druhé poslání
  nikdy nic nepřepíše.
- `422 Unprocessable Entity` při chybě validace, s polem `errors[]`, kde každá
  položka nese JSON Pointer na konkrétní pole a popis
- `415 Unsupported Media Type` při jiném Content-Type než `application/json`
- `400 Bad Request` při rozbitém JSONu

### Změna data doručení

```http
PUT /api/v1/partners/{partnerId}/orders/{orderId}/delivery-date
Content-Type: application/json

{"expectedDeliveryDate": "2026-10-19"}
```

- `200 OK` a celé tělo aktualizované objednávky
- `404 Not Found` když objednávka neexistuje, včetně případu, kdy patří jinému
  partnerovi. Napříč partnery se nedá sáhnout na cizí data.
- PUT je idempotentní, stejné tělo vede na stejný výsledný stav

### Přečtení objednávky

```http
GET /api/v1/partners/{partnerId}/orders/{orderId}
```

- `200 OK` a uložená objednávka
- `404 Not Found` když neexistuje

### Chyby

Všechny chybové odpovědi jsou Problem Details podle RFC 9457, tedy
`application/problem+json`:

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

Základ URI v poli `type` je řízený proměnnou prostředí `PROBLEM_TYPE_BASE_URI`,
aby si staging i produkce mohly publikovat vlastní dokumentaci chyb.

## Architektura

Klasické vrstvení:

```
Controller (HTTP)
  -> Factory (překlad requestu na vstup služby)
  -> Handler (logika případu užití)
     -> Repository interface (Doctrine adapter)
```

```
src/
  Controller/Api/V1/   OrderController, jedna třída pro celý resource
  Dto/                 CreateOrder, ChangeOrderDeliveryDate (vstupy služeb)
    Request/           příchozí DTO s validačními pravidly
    Response/          odchozí DTO
  Entity/              Order, OrderProduct
  EventListener/       ProblemDetailsListener
  Exception/           doménové výjimky a ProblemInterface
  Factory/             request DTO -> vstup služby, entita -> odpověď
  Repository/          OrderRepositoryInterface a jeho Doctrine adapter
  Service/             tři handlery a CalendarDateParser
  Validator/           ValidDecimalAmount
  ValueObject/         DecimalAmount, ProductLine
```

- Služby závisí na `OrderRepositoryInterface`, ne na Doctrine třídě za ním.
  Unit testy proto používají in-memory implementaci a vůbec nestartují kernel.
- Entity si hlídají vlastní invarianty. Konstruktor objednávky odmítne prázdný
  seznam produktů, `ProductLine` odmítne množství pod jedna a `DecimalAmount`
  odmítne cokoliv, co není nezáporné desetinné číslo vejdoucí se do sloupce.
  Jediná povolená změna je `Order::changeExpectedDeliveryDate()`.
- V `src/` není jediná statická metoda. Objekty se staví konstruktorem a
  cokoliv, co potřebuje spolupracovníka, je injectovaná služba.
- Validace běží na třech úrovních: DTO hlídá tvar a vrací 422, doména hlídá
  invarianty a unikátní index v databázi je jediná pojistka, která obstojí při
  souběžných requestech.
- Všechny chyby tečou přes `ProblemDetailsListener`, tedy jedno místo, které
  mapuje výjimky na Problem Details. Nový druh chyby znamená novou výjimku se
  třemi metodami, do listeneru se nesahá a není v něm žádný switch.
- Interní primární klíč je UUID v7 ze `symfony/uid`. Navenek se používá
  partnerská dvojice `(partnerId, orderId)`, interní ID se nikdy nezveřejňuje.
- Produktové řádky mají sloupec `position`, takže se čtou přesně v tom pořadí,
  v jakém je partner poslal.

### Testy

- Unit testy nad doménou a službami (`tests/Entity`, `tests/ValueObject`,
  `tests/Service`) běží v řádu milisekund a pokrývají šťastné i nešťastné
  cesty, hranice, idempotenci a výjimky. To je ta část, kterou zadání chtělo
  pokrýt "jak bych to dělal při standardním vývoji".
- Integrační `WebTestCase` na všechny tři endpointy (`tests/Functional`)
  projdou celý HTTP cyklus proti Postgresu, včetně mapování DTO a všech
  dokumentovaných chybových stavů. To je bonus ze zadání.
- `tests/EventListener` zvlášť fixuje překlad výjimek na Problem Details.
- Místo mocků se používají fakes, hlavně in-memory repozitář.
- `dama/doctrine-test-bundle` obalí každý databázový test transakcí a na konci
  ji rollbackne, takže testy mezi sebou neinterferují a nezáleží na pořadí.

## O čem jsem přemýšlel a co bych dělal jinak

Věci, které jsem udělal jinak, než bych je dělal v ostrém provozu, nebo je
vědomě vynechal.

### partnerId v URL versus autentizační token

Endpoint teď bere `partnerId` z URL. V ostrém provozu by partnera měl určovat
autentizační mechanismus, tedy API klíč, OAuth scope nebo subject v JWT.
Klient by ho neposílal sám, server by si ho vytáhl z tokenu. Tím zmizí celá
třída chyb, protože klient nemůže omylem ani schválně poslat data za jiného
partnera. Zadání autentizaci explicitně vyřazuje, takže partnerId zůstává
v URL.

### GET endpoint navíc

Zadání chce dva endpointy, tady jsou tři. Odpověď `201 Created` má podle
standardu nést hlavičku `Location` s adresou vytvořeného zdroje, a `Location`,
které vrací 405, je rozbitý kontrakt. Čtecí endpoint stojí jednu metodu
v controlleru a jeden případ užití, takže mi přišlo správnější ho dopsat než
hlavičku vynechat. Navíc se díky němu dají integrační testy psát přes veřejné
API a nemusí šťourat v databázi.

### PUT na datum doručení, ne PATCH na objednávku

Změna data doručení má vlastní sub-resource a metodu PUT. Tělo requestu je
celá reprezentace toho sub-resource, takže PUT je poctivá volba a idempotence
plyne ze sémantiky, ne z domluvy. Partner navíc čte jednu konkrétní
dokumentovanou operaci místo obecného editoru, u kterého o výsledku rozhoduje
tělo.

Zkoušel jsem předtím i variantu s `PATCH /orders/{orderId}` a JSON Merge Patch
podle RFC 7396. Funguje to a má to svoje kouzlo ve chvíli, kdy je
aktualizovatelných polí víc, protože další pole je pak jen vlastnost v DTO
a žádná nová routa. Při jednom poli to ale znamená platit za obecnost, kterou
nikdo nevyužije, a otevírat otázku, co znamená `null` v těle. Kdyby přibylo
víc měnitelných polí, vrátil bych se k PATCH a nechal PUT jen tam, kde se
opravdu nahrazuje celá reprezentace.

### Peníze bez knihovny

Částky jsou v celé cestě `string`, v databázi `numeric(14, 2)`. Nikde se
nepracuje s `float`, protože tam se dřív nebo později ztratí haléř. Hlídá to
hodnotový objekt `DecimalAmount`, který si v konstruktoru ověří tvar
a normalizuje počet desetinných míst. Jeho konstanty `PRECISION` a `SCALE`
zároveň řídí mapování sloupce i validační pravidlo, takže to číslo je v kódu
napsané jednou.

Šlo by na to použít `brick/math` a ukládat `BigDecimal`. Dá to typovou záruku,
že nad částkou nikdo nezavolá float operaci, protože prostě neexistuje. Cena
je jedna závislost navíc a k ní vlastní Doctrine typ, normalizer do serializeru
a vlastní validační constrainty, protože `BigDecimal` není skalár a nikdo z toho
řetězce ho neumí sám. Pro tenhle rozsah mi nepřišlo správné přidávat závislost
kvůli něčemu, co dvě malé třídy pokryjí. Kdyby se s částkami začalo skutečně
počítat, tedy sčítat, násobit sazbou nebo přepočítávat měnu, přešel bych na
`brick/math` hned, protože tam už ta typová pojistka vydělá.

### Měna

Zadání měnu nezmiňuje, takže v modelu není. Je to vědomé: cena bez měny není
peněžní hodnota, je to jen číslo. Jakmile by se uložila s implicitním
předpokladem, typicky CZK, rozbije ho první partner s eshopem v eurech,
a oprava po faktu už není změna modelu, ale migrace dat.

Čistý model měny stojí na třech věcech. ISO 4217 kód jako číselník. Měnu nese
objednávka a produktové řádky ji dědí, protože jedna objednávka v několika
měnách nedává účetní smysl. A při přepočtu do reportingové měny musí být kurz
zafixovaný k okamžiku vzniku objednávky, jinak by pozdější přepočet zpětně
měnil už uzavřené účetní výstupy.

### DPH

Stejná logika jako u měny. `price` a `totalValue` jsou teď "číslo, které
přišlo". Nevíme, jestli s daní nebo bez, neumíme to rozlišit a neumíme to
dopočítat. Reálně by se to chtělo rozdělit na cenu bez daně, sazbu a cenu
s daní, a navázat na číselník sazeb. To je netriviální doména, protože sazba
se mění zákonem a uplatňuje se podle druhu zboží i místa dodání, takže bez
konkrétního zadání jsem do toho nešel.

### Celková hodnota objednávky

`totalValue` se ukládá přesně tak, jak ji partner poslal. Nedopočítává se ze
součtu produktů a ani se s ním neporovnává, protože zadání chce uložit, co
přišlo. V provozu bych přidal jednu věc: při rozdílu mezi celkovou hodnotou
a součtem řádků logovat metriku, ale request neodmítnout. Rozdíl totiž nemusí
být chyba, může jít o slevu, akční cenu, dopravu nebo zaokrouhlení, které
nevidíme. Kolik objednávek chodí s nesedícím součtem je ale zajímavé číslo pro
datový tým.

### Datum doručení v minulosti

Backend přijme i datum v minulosti a uloží ho. Držíme se zadání, takže tvrdá
validace by šla proti jeho smyslu. Kontrolu úmyslu považuji za úlohu klienta,
tedy zobrazit potvrzení ve stylu "opravdu chcete datum v minulosti". Tím se
chytí překlepy, ale legitimní zpětné opravy, třeba po vrácení zboží nebo po
špatném importu, projdou. Tvrdé omezení na backendu by bez konkrétního
obchodního pravidla blokovalo i tyhle případy.

Co se ale odmítá, je datum, které neexistuje. `2026-02-30` by PHP tiše
překlopilo na druhého března, takže se datum nejdřív validuje jako řetězec
a teprve pak převádí.

### UUID v7 jako interní klíč

V7 má časovou složku, takže nové záznamy jdou na konec indexu místo aby ho
tříštily jako čistě náhodné v4. Při současném objemu je to jedno, při vyšším
zápisu to začne být znát, a nic to nestojí.

### Audit log

Nedělám ho. V B2B integracích je auditní stopa často regulatorní požadavek
a "kdo to změnil" bývá první otázka při každém sporu, takže v ostrém provozu
by to byla jedna z prvních věcí. Prakticky by to byla tabulka se záznamem kdo,
kdy, na čem a co se změnilo, zapisovaná ve stejné transakci jako samotná
změna, aby nemohl nastat stav "data se změnila, ale audit chybí". Bez
autentizace by ale to "kdo" byla konstanta, takže by to teď byla mrtvá tabulka
a mrtvá třída navíc.

## Na co jsem narazil

Věci, které při vývoji nefungovaly a stály čas. Píšu je sem, protože z nich je
víc vidět než z hotového kódu.

- **Symfony 8.0 neumělo vygenerovat migraci.** Doctrine bridge ve verzi 8.0
  volá `GenerateSchemaEventArgs::setSchema()` bez podmínky, jenže ORM 3.7 to
  na DBAL 4.4 odmítne, protože potřebné API je až v nevydaném DBAL 4.5.
  `doctrine:migrations:diff` tím padal. Bridge v 8.1 to volání podmiňuje,
  takže projekt běží na 8.1. Osmičková nula už je navíc po konci údržby.
- **Serializer nedenormalizoval vnořené produkty.** Typ `list<CreateOrderProductRequest>`
  je jen v docblocku konstruktoru, takže na něj property-info samo nedosáhne.
  Chtělo to zapnout `with_constructor_extractor` a doinstalovat
  `phpstan/phpdoc-parser` s `phpdocumentor/type-resolver`. Bez toho přišlo do
  handleru pole polí místo pole objektů.
- **CI padalo na `composer validate --strict`.** Composer si zapsal
  `phpstan/phpdoc-parser` s omezením `*`, protože balíček už v projektu byl
  jako tranzitivní závislost, a striktní validace nevázané omezení odmítá.
- **Dvě díry ve validaci, obě končily pětistovkou.** Množství nad rozsah
  integer sloupce prošlo validací a spadlo až v Postgresu. Objednávka
  s desítkami tisíc řádků vyčerpala paměť PHP ještě při validaci, a sloupec
  `position` je smallint, takže by stejně přetekl. Obojí teď končí na 422
  s ukazatelem na konkrétní pole, meze jsou milion kusů na položku a tisíc
  položek na objednávku.

## Coding standard

PHP 8.4 naplno: `readonly` třídy pro DTO, asymetrická viditelnost u entit
místo getterů, promoce vlastností v konstruktoru, `declare(strict_types=1)`
v každém souboru a `final` jako výchozí stav.

PHPStan na `level: max` se strict rules a rozšířeními pro Symfony, Doctrine
i PHPUnit, bez baseline. Chyby se mají chytat při statické analýze, ne
v provozních logách.

php-cs-fixer s `@PER-CS2.0`, `@PHP84Migration`, `@Symfony` a `@Symfony:risky`.

Komentáře v kódu skoro nejsou. Docblocky nesou jen typové informace, které
nativní signatura neumí vyjádřit, tedy generika a tvary polí. Zbytek má říct
název.
