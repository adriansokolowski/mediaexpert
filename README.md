# System rezerwacji terminów

Backendowe REST API do rezerwowania 30-minutowych wizyt: sprawdzanie dostępności,
rezerwacja, anulowanie i przesunięcie terminu.

**Stack:** PHP 8.3+, Laravel 12, MySQL 8.4 (testy na SQLite in-memory), Docker

## Zakres

Wymagane funkcje API (pobranie dostępnych slotów, utworzenie rezerwacji,
anulowanie) są zrealizowane w całości. Z listy „mile widziane":

| Element | Gdzie |
| --- | --- |
| Docker | `compose.yaml`, `Dockerfile`, start jednym poleceniem |
| Przesunięcie rezerwacji | `PATCH /api/appointments/{id}` |
| Lista z filtrowaniem i paginacją | `GET /api/appointments`, paginacja kursorowa |
| Obsługa wielu lokalizacji | `location_id` w całym schemacie, pokryte `MultiLocationTest` |
| Komenda generująca wolumen | `php artisan appointments:generate` |
| Zachowanie przy setkach tysięcy rezerwacji | [zmierzone na 200 tys. rekordów](#wydajność-przy-dużym-wolumenie) |

Dodatkowo: analiza statyczna (PHPStan/Larastan), pipeline CI na PHP 8.3 i 8.4
oraz pokrycie zmian czasu (DST).

---

## Spis treści

1. [Szybki start](#szybki-start)
2. [Testy](#testy)
3. [API](#api)
4. [Model danych i indeksy](#model-danych-i-indeksy)
5. [Kluczowe decyzje projektowe](#kluczowe-decyzje-projektowe)
6. [Wydajność przy dużym wolumenie](#wydajność-przy-dużym-wolumenie)
7. [Świadome uproszczenia i kolejna iteracja](#świadome-uproszczenia-i-kolejna-iteracja)

---

## Szybki start

### Docker (zalecane)

```bash
docker compose up -d --build
```

To wszystko. Kontener przy starcie sam instaluje zależności, tworzy `.env`, generuje
klucz aplikacji oraz uruchamia migracje i seedery. API nasłuchuje na
http://localhost:8000.

Weryfikacja:

```bash
curl "http://localhost:8000/api/availability?date=2026-09-14"
```

Przydatne komendy:

```bash
docker compose logs -f app                       # logi
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test
docker compose down -v                           # sprzątanie razem z wolumenem MySQL
```

### Bez Dockera

Wymagane: PHP 8.3+ (`pdo_sqlite` lub `pdo_mysql`) i Composer.

```bash
composer install
cp .env.example .env
```

W `.env` ustaw bazę, najprościej SQLite:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/pełna/ścieżka/do/database/database.sqlite
```

```bash
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Seedery

| Seeder | Co robi |
| --- | --- |
| `LocationSeeder` | Lokalizacja `main` (Europe/Warsaw, slot 30 min) i jej godziny otwarcia |
| `ClosingDaySeeder` | Polskie święta państwowe na bieżący rok i 2 lata do przodu |

Oba są idempotentne, można je uruchamiać wielokrotnie.

---

## Testy

```bash
docker compose exec app php artisan test     # w Dockerze
php artisan test                             # lokalnie
```

**67 testów / 285 asercji.** Suite działa na SQLite in-memory i nie wymaga
konfiguracji: komitowany plik `.env.testing` zawiera komplet ustawień testowych.
`php artisan test` działa od razu po sklonowaniu repozytorium.

Uruchomienie testów jest bezpieczne również wewnątrz kontenera, patrz
[izolacja testów](#izolacja-testów-od-bazy-deweloperskiej).

Zegar jest zamrożony na czwartek 10.09.2026, 08:00 (`Tests\TestCase`), dzięki
czemu dzień tygodnia każdej daty w testach i reguła „slot nie może być w przeszłości"
są deterministyczne.

Co pokrywają testy:

| Plik | Zakres |
| --- | --- |
| `SlotGenerationTest` | Generowanie slotów: 16 w dzień powszedni, 8 w sobotę, 0 w niedzielę, dzień wolny, rozpoznawanie początku slotu |
| `AvailabilityApiTest` | Endpoint dostępności, ukrywanie zajętych i minionych slotów, walidacja daty |
| `BookAppointmentTest` | Rezerwacja, strefy czasowe w żądaniu, wszystkie odmowy (poza grafikiem, poza siatką 30 min, przeszłość, dzień wolny), walidacja |
| `CancelAppointmentTest` | Zwalnianie slotu, ponowna rezerwacja, idempotencja anulowania |
| `RescheduleAppointmentTest` | Przesunięcie terminu, konflikt, cel poza grafikiem, rezerwacja anulowana |
| `ConcurrentBookingTest` | Odporność na wyścigi, patrz [Współbieżność](#3-współbieżność-niezmiennik-pilnowany-przez-bazę) |
| `DaylightSavingTest` | Zmiana czasu: pominięta godzina wiosną, powtórzona jesienią, zapis do bazy |
| `MultiLocationTest` | Izolacja lokalizacji: własne strefy czasowe, ten sam moment w dwóch lokalizacjach, zakres dni wolnych i listy |
| `ListAppointmentsTest` | Filtrowanie, sortowanie, paginacja kursorowa |
| `ClosingDayAdminTest` | Zarządzanie dniami wolnymi i ochrona tokenem |
| `ClosingDaySeederTest` | Święta stałe i ruchome (wyliczana data Wielkanocy) |
| `TimeIntervalTest` | Parsowanie godzin otwarcia (test jednostkowy, bez bazy) |
| `TestEnvironmentTest` | Bezpiecznik: suite musi działać na bazie jednorazowej |

Poza produkcją włączony jest `Model::shouldBeStrict()`. Przypadkowe zapytanie N+1
albo literówka w nazwie atrybutu wysypuje wtedy test, zamiast przejść niezauważona.

### Jakość kodu

```bash
composer check           # styl + analiza statyczna + testy
composer lint            # Laravel Pint (--test)
composer analyse         # PHPStan poziom 8: app, database, tests
composer analyse:strict  # PHPStan poziom max na app/
```

Wszystkie cztery przechodzą bez błędów. Analiza stoi na PHPStan 8 (Larastan) dla
całego kodu, a katalog `app/` przechodzi dodatkowo poziom max. Globalnego poziomu
nie podniosłem do max celowo: pozostałe uwagi dotyczą wyłącznie rzutowania `mixed`
na wyniki `$response->json()` w testach, a obchodzenie tego dodałoby asercjom
szumu bez zysku dla poprawności.

`.github/workflows/ci.yml` uruchamia ten sam zestaw na PHP 8.3 i 8.4. Dolna
granica to wersja, którą projekt deklaruje w `composer.json`.

### Izolacja testów od bazy deweloperskiej

`RefreshDatabase` usuwa i odtwarza wszystkie tabele, więc wycelowanie testów w
prawdziwą bazę oznacza utratę danych. Jest to łatwiejsze do przeoczenia, niż się
wydaje, i w trakcie pracy nad zadaniem faktycznie się na tym potknąłem:

- wpisy `<env>` w `phpunit.xml` nie nadpisują zmiennych już obecnych w
  środowisku, dopóki nie mają atrybutu `force="true"`;
- ale samo `force="true"` nie wystarcza, bo PHPUnit zapisuje wtedy `$_ENV`, a
  `Illuminate\Support\Env` czyta najpierw `$_SERVER`.

Dlatego `phpunit.xml` ustawia krytyczne zmienne równolegle jako `<server>` i
`<env force="true">`, `compose.yaml` celowo nie eksportuje `DB_*` do kontenera
(dane dostępowe pochodzą z `.env`), a `TestEnvironmentTest` asercją pilnuje efektu
końcowego: jeśli ktoś kiedyś usunie te zabezpieczenia, wywali się test, a nie
czyjaś baza.

---

## API

Wszystkie odpowiedzi są w JSON. Parametr `location_id` jest wszędzie opcjonalny,
domyślnie używana jest lokalizacja `main`.

| Metoda | Ścieżka | Opis |
| --- | --- | --- |
| `GET` | `/api/availability?date=YYYY-MM-DD` | Wolne sloty wybranego dnia |
| `POST` | `/api/appointments` | Utworzenie rezerwacji |
| `GET` | `/api/appointments` | Lista z filtrowaniem i paginacją |
| `GET` | `/api/appointments/{id}` | Szczegóły rezerwacji |
| `PATCH` | `/api/appointments/{id}` | Przesunięcie na inny termin |
| `DELETE` | `/api/appointments/{id}` | Anulowanie |
| `GET` | `/api/locations` | Lista lokalizacji |
| `GET` | `/api/closing-days` | Lista dni wolnych |
| `POST` | `/api/closing-days` | Dodanie dnia wolnego *(wymaga tokenu)* |
| `DELETE` | `/api/closing-days/{id}` | Usunięcie dnia wolnego *(wymaga tokenu)* |

`{id}` to publiczny identyfikator ULID (np. `01M265Z5MXMH6D8BG4733Y34YA`), a nie
klucz główny, patrz [Model danych](#model-danych-i-indeksy).

Obowiązuje limit 120 żądań na minutę na adres IP.

### Dostępność

```bash
curl "http://localhost:8000/api/availability?date=2026-09-12"
```

```json
{
  "data": [
    { "starts_at": "2026-09-12T10:00:00+02:00", "ends_at": "2026-09-12T10:30:00+02:00" },
    "...",
    { "starts_at": "2026-09-12T13:30:00+02:00", "ends_at": "2026-09-12T14:00:00+02:00" }
  ],
  "meta": {
    "date": "2026-09-12",
    "location_id": 1,
    "timezone": "Europe/Warsaw",
    "slot_duration_minutes": 30,
    "count": 8
  }
}
```

### Rezerwacja

```bash
curl -X POST http://localhost:8000/api/appointments \
  -H 'Content-Type: application/json' \
  -d '{"starts_at":"2026-09-14 09:00","customer_name":"Anna Kowalska","customer_email":"anna@example.test"}'
```

`starts_at` przyjmuje zarówno czas lokalny (`2026-09-14 09:00`), interpretowany
w strefie lokalizacji, jak i pełny ISO-8601 z przesunięciem
(`2026-09-14T07:00:00Z`). Wymagane jest `customer_name` lub `customer_email`.

### Lista rezerwacji

```bash
curl "http://localhost:8000/api/appointments?from=2026-09-14&to=2026-09-20&status=booked&per_page=25"
```

Parametry: `location_id`, `status` (`booked` / `cancelled`), `from`, `to`
(daty `Y-m-d`, obie granice włącznie), `customer_email`, `per_page` (od 1 do 100),
`cursor`. Kolejną stronę pobiera się przekazując `meta.next_cursor` jako `cursor`.

### Dni wolne (administracyjne)

```bash
curl -X POST http://localhost:8000/api/closing-days \
  -H 'X-Admin-Token: local-admin-token' \
  -H 'Content-Type: application/json' \
  -d '{"date":"2026-12-24","reason":"Wigilia"}'
```

### Format błędów

Błędy naruszenia reguł biznesowych mają stabilny, maszynowo czytelny kod:

```json
{ "message": "The slot starting at 2026-09-14T09:00:00+02:00 is already taken.", "code": "slot_already_booked" }
```

| Kod | HTTP | Znaczenie |
| --- | --- | --- |
| `slot_outside_schedule` | 422 | Poza godzinami pracy, w dniu wolnym lub poza siatką 30 minut |
| `slot_in_the_past` | 422 | Termin już minął |
| `slot_already_booked` | 409 | Slot zajęty przez inną aktywną rezerwację |
| `appointment_not_active` | 409 | Próba przesunięcia anulowanej rezerwacji |

Błędy walidacji zwracają standardowy format Laravela (422 + `errors`).

---

## Model danych i indeksy

```
locations ──┬── business_hours   (godziny otwarcia wg dnia tygodnia)
            ├── closing_days     (dni wolne)
            └── appointments     (rezerwacje)
```

| Tabela | Rola |
| --- | --- |
| `locations` | Nazwa, slug, własna strefa czasowa i długość slotu |
| `business_hours` | Jeden wiersz = jeden przedział otwarcia jednego dnia tygodnia (ISO: 1 = poniedziałek, 7 = niedziela) |
| `closing_days` | Dzień kalendarzowy, w którym lokalizacja nie przyjmuje |
| `appointments` | Rezerwacje wraz z historią anulowań |

### Indeksy i uzasadnienie

| Indeks | Tabela | Po co |
| --- | --- | --- |
| `UNIQUE (location_id, active_starts_at)` | `appointments` | **Najważniejszy.** Pełni dwie role: jest niezmiennikiem „jedna aktywna rezerwacja na slot" wymuszanym przez bazę oraz indeksem obsługującym zapytanie o dostępność. Zapytanie o dostępność czyta wyłącznie kolumnę `active_starts_at`, dzięki czemu MySQL realizuje je jako covering index scan (`Using index`), bez sięgania do wierszy tabeli. |
| `INDEX (location_id, starts_at)` | `appointments` | Listowanie i filtrowanie po zakresie dat, niezależnie od statusu. Kolejność kolumn odpowiada `WHERE location_id = ? AND starts_at BETWEEN ? AND ? ORDER BY starts_at`, więc indeks dostarcza gotowe sortowanie i eliminuje `filesort`. Jest też fundamentem paginacji kursorowej. |
| `INDEX (customer_email, starts_at)` | `appointments` | Historia konkretnego klienta. Nie ma modelu użytkownika, więc e-mail jest jedynym uchwytem do rezerwacji klienta; druga kolumna daje darmowe sortowanie chronologiczne. |
| `UNIQUE (public_id)` | `appointments` | Route model binding po identyfikatorze publicznym. |
| `UNIQUE (location_id, date)` | `closing_days` | Zapobiega duplikatom i jednocześnie obsługuje sprawdzenie „czy ten dzień jest wolny?". |
| `INDEX (location_id, day_of_week)` | `business_hours` | Odczyt godzin otwarcia przy każdym żądaniu o dostępność i przy każdej rezerwacji. |

**Czego nie dodałem:** indeksu `(location_id, status, starts_at)`. Byłby szybszy
dla listy filtrowanej po statusie, ale duplikuje `(location_id, starts_at)` i
dokłada koszt przy każdym zapisie. Warto go dodać dopiero wtedy, gdy takie
zapytanie okaże się gorące w praktyce.

### Klucz główny a identyfikator publiczny

`appointments.id` to zwykły `BIGINT AUTO_INCREMENT`, dzięki czemu zapisy dopisują
się na końcu indeksu klastrowanego InnoDB, zamiast losowo rozrzucać strony, co
robiłby UUID jako klucz główny. Na zewnątrz wystawiany jest osobny `public_id`
(ULID), nieodgadywalny i nieujawniający liczby rezerwacji w systemie.

---

## Kluczowe decyzje projektowe

### 1. Strefy czasowe: UTC w bazie, czas lokalny w regułach

Wszystkie znaczniki czasu trafiają do bazy w UTC. Godziny otwarcia są natomiast
danymi lokalnymi („sobota 10:00-14:20") i są interpretowane w strefie zapisanej
przy lokalizacji. Konwersja odbywa się na granicy: generator slotów buduje je w
czasie lokalnym, a zapis normalizuje do UTC.

Eloquent formatuje daty przy zapisie bez konwersji strefy. Przekazanie mu
instancji w `Europe/Warsaw` zapisałoby czas ścienny i po cichu przesunęło
rezerwację o aktualne przesunięcie UTC. Stąd cast `App\Casts\UtcDateTime`:
decyzja o normalizacji jest w jednym miejscu, a nie w każdym wywołaniu.
Analogicznie `App\Casts\CalendarDate` trzyma `closing_days.date` jako czyste
`Y-m-d`, co pozwala wyszukiwać przez zwykłe `where('date', ...)` (używa indeksu)
zamiast `whereDate()` (funkcja na kolumnie blokuje indeks).

**Zmiana czasu.** Przejścia DST w Polsce wypadają nad ranem w niedzielę, a
niedziela jest zamknięta, przez co obecny grafik nigdy ich nie spotyka. Nie
chciałem jednak zostawić tego jako założenia, bo pierwsza wersja generatora była
tu błędna: granice przedziału wyznaczałem dodając minuty do lokalnej północy,
czyli czas *rzeczywisty*, podczas gdy godziny otwarcia to czas *zegarowy*. W dniu
zmiany czasu zamknięcie „06:00" wypadało o 07:00.

Poprawka to `setTime()` zamiast `addMinutes()` w `TimeInterval::on()`.
`DaylightSavingTest` przybija zachowanie dla lokalizacji otwartej przez zmianę
czasu w obie strony:

- **wiosną** (28.03.2027, gdy zegary skaczą z 02:00 na 03:00) nieistniejąca
  godzina jest pominięta, a zamknięcie trzyma się 06:00 czasu zegarowego;
- **jesienią** (31.10.2027, gdy 03:00 cofa się na 02:00) powtórzona godzina daje
  różne momenty w czasie, przez co nie powstają zduplikowane rezerwacje, a sloty
  pozostają rozłączne, ciągłe i uporządkowane.

### 2. Slot musi zmieścić się w całości

Sloty generowane są od godziny otwarcia co 30 minut, a warunek pętli brzmi
`początek + 30 min <= zamknięcie`. To dlatego sobota 10:00-14:20 daje 8 slotów,
z których ostatni to 13:30-14:00. Slot 14:00-14:30 wystawałby poza godziny pracy.

Generator jest jedynym źródłem prawdy: zarówno endpoint dostępności, jak i
walidacja rezerwacji przechodzą przez tę samą metodę i nie mogą się rozjechać.

### 3. Współbieżność: niezmiennik pilnowany przez bazę

Sprawdzenie „czy slot jest wolny?" przed zapisem nie wystarcza, bo dwa równoległe
żądania mogą je przejść jednocześnie. Rozwiązanie:

Kolumna `active_starts_at` powiela `starts_at`, dopóki rezerwacja jest aktywna, a
przy anulowaniu jest ustawiana na `NULL`. Na parze `(location_id, active_starts_at)`
stoi indeks UNIQUE. W indeksie unikalnym wartości NULL nie kolidują (zachowanie
identyczne w MySQL, PostgreSQL i SQLite), co daje dokładnie pożądaną semantykę:

- najwyżej jedna aktywna rezerwacja na slot,
- dowolnie wiele anulowanych rezerwacji na tym samym slocie, czyli historia
  zostaje zachowana, a slot jest wolny.

Zapis wykonywany jest optymistycznie, a `UniqueConstraintViolationException` jest
tłumaczony na `409 slot_already_booked`. Nie ma blokad, nie ma zależności od
poziomu izolacji transakcji i działa to również przy wielu instancjach aplikacji.

Testy w `ConcurrentBookingTest` sprawdzają to trzytorowo: bezpośredni zapis do
tabeli z pominięciem aplikacji musi rzucić wyjątkiem; przegrany wyścigu (wiersz
konkurenta wstrzyknięty pomiędzy walidację a zapis) dostaje 409 zamiast duplikatu;
trzy anulowane rezerwacje na tym samym slocie współistnieją bez konfliktu.

### 4. Dni wolne: podejście hybrydowe

Dni wolne żyją w tabeli `closing_days`, a nie w kodzie, i można je ustawiać dwiema
drogami:

1. **Seeder** (`ClosingDaySeeder`) obsługuje polskie święta państwowe na bieżący
   rok i dwa lata do przodu. Święta ruchome (Poniedziałek Wielkanocny, Boże Ciało)
   są wyliczane algorytmem gaussowskim Meeusa/Jonesa/Butchera, dzięki czemu seeder
   działa dla dowolnego roku i nie wymaga rozszerzenia `calendar`.
2. **Endpoint administracyjny**, czyli `POST` / `DELETE /api/closing-days`.

W projekcie nie ma modelu użytkownika, więc endpointy zapisujące chroni pojedynczy
sekret przesyłany w nagłówku `X-Admin-Token` i porównywany przez `hash_equals()`.
Gdy `SCHEDULING_ADMIN_TOKEN` jest pusty, endpointy zwracają 503, czyli domyślnie
są wyłączone, a nie otwarte.

### 5. Godziny otwarcia w bazie, nie w kodzie

`business_hours` przechowuje jeden wiersz na przedział, a nie na dzień. Dzień bez
wiersza jest zamknięty (tak zamodelowana jest niedziela), a dzień z dwoma wierszami
miałby przerwę. Obsługa przerwy obiadowej nie wymaga zatem zmiany schematu ani
logiki, tylko dodania wiersza.

### 6. Anulowanie jest idempotentne

`DELETE` na już anulowanej rezerwacji zwraca 200 i ten sam zasób, zamiast błędu.
Ponowione żądanie (timeout, retry klienta) nie może się w ten sposób wywrócić.
Przesunięcie terminu celowo nie jest idempotentne: anulowanej rezerwacji nie da
się przesunąć (`409 appointment_not_active`), bo zwolniony slot mógł już zostać
zajęty przez kogoś innego.

### 7. Warstwa domenowa oddzielona od HTTP

Reguły biznesowe żyją w `app/Domain/Scheduling` i nie wiedzą nic o żądaniach HTTP.
Kontrolery zajmują się tłumaczeniem wejścia i wyjścia, a wyjątki domenowe same
niosą swój kod HTTP i kod błędu, mapowany centralnie w `bootstrap/app.php`.

---

## Wydajność przy dużym wolumenie

W repozytorium jest komenda generująca realistyczny wolumen danych:

```bash
docker compose exec app php artisan appointments:generate --count=200000 --chunk=5000
```

Wstawia wsadowo (`insertOrIgnore`, domyślnie 2000 wierszy na `INSERT`), z około
10% rezerwacji anulowanych. Wygenerowanie 200 000 rekordów zajęło około 30 s.

### Zmierzone wyniki

Pomiary na MySQL 8.4 w Dockerze, tabela `appointments` z 200 001 wierszami
(179 816 aktywnych):

| Operacja | Czas |
| --- | --- |
| Dostępność dnia (pełna ścieżka, 3 zapytania, bez cache) | 1,50 ms |
| Strona listy rezerwacji (keyset, 25 pozycji) | 1,25 ms |
| Zapytanie o zajęte sloty (`EXPLAIN`) | `type: range`, `key: appointments_active_slot_unq`, `Using index`, `rows: 13` |

Zapytanie o dostępność dotyka 13 wpisów indeksu zamiast 200 000 wierszy i nie
schodzi do tabeli, bo indeks pokrywa całe zapytanie. Koszt jest funkcją liczby
slotów w dniu, a nie rozmiaru tabeli, i taki pozostanie przy milionach rekordów.

### Dlaczego paginacja kursorowa, a nie `OFFSET`

Lista rezerwacji używa `cursorPaginate()` (paginacja keyset), a nie `paginate()`.
Przy tych samych danych, dla tej samej pozycji w zbiorze:

| Sposób | Czas |
| --- | --- |
| `LIMIT 25 OFFSET 150000` | 92,7 ms |
| Keyset w tym samym miejscu | 0,31 ms |
| `COUNT(*)` doliczany przez zwykłą paginację | 12,2 ms |

`OFFSET` musi przejść i odrzucić 150 000 wpisów indeksu, przez co koszt rośnie
liniowo z numerem strony. Keyset skacze od razu w odpowiednie miejsce po
`(starts_at, id)`, dzięki czemu strona 10 000 kosztuje tyle samo co pierwsza, i
dodatkowo pomija `COUNT(*)`, który przy każdym żądaniu doliczałby kolejne
kilkanaście milisekund. Sortowanie po `(starts_at, id)` jest deterministyczne
(`id` rozstrzyga remisy), więc kursor nie gubi ani nie dubluje wierszy.

### Pozostałe decyzje nastawione na skalę

- Zapytanie o dostępność czyta jedną kolumnę przez query builder, bez
  hydratowania modeli Eloquenta, a różnica zbiorów liczona jest w pamięci na
  tablicy indeksowanej znacznikiem czasu (`isset`, O(1)).
- `ScheduleRepository` memoizuje godziny otwarcia i dni wolne na czas żądania,
  bo te same dane są odpytywane wielokrotnie, a zmieniają się bardzo rzadko.
- Lista rezerwacji eager-loaduje relację `location`; `Model::preventLazyLoading()`
  sprawia, że ewentualny regres N+1 wysypie testy.
- Komenda generująca dane wczytuje dni wolne dla całego zakresu jednym
  zapytaniem, zamiast wykonywać `EXISTS` dla każdego dnia.

### Gdzie to przestanie wystarczać

Przy setkach tysięcy rezerwacji wąskim gardłem nie jest odczyt, tylko rozmiar
indeksów przy zapisie i archiwizacja. Przy milionach wierszy sięgnąłbym po
partycjonowanie `appointments` po `starts_at` (partycje miesięczne, tanie
odcinanie historii) i przeniósł stare rezerwacje do tabeli archiwalnej.
Endpoint dostępności jest naturalnym kandydatem do cache'owania z inwalidacją
przy zapisie, ale przy 1,5 ms na żądanie byłaby to na razie przedwczesna
optymalizacja.

---

## Świadome uproszczenia i kolejna iteracja

Zadanie było wycenione na około 3 godziny, więc część rzeczy odpuściłem celowo.

### Co pominąłem i dlaczego

| Pominięte | Uzasadnienie |
| --- | --- |
| Model użytkowników i uwierzytelnianie | Wprost wyłączone z zakresu. Endpointy administracyjne chroni token współdzielony, prymitywny, ale domyślnie wyłączony. |
| Powiadomienia (e-mail / SMS) | Wymagałyby kolejek i obsługi awarii dostawcy; nie wnosi to nic do ocenianej logiki biznesowej. |
| Walidacja nakładających się `business_hours` | Schemat dopuszcza wiele przedziałów dziennie, ale nie sprawdzam, czy się nie nachodzą. Dane wchodzą wyłącznie przez seeder. Docelowo: reguła walidacyjna przy edycji grafiku. |
| Globalne dni wolne (ponad lokalizacjami) | `closing_days.location_id` jest `NOT NULL`, bo `NULL` w indeksie unikalnym nie zapobiegałby duplikatom. Święto ogólnokrajowe jest dziś powielane per lokalizacja. Docelowo: osobna tabela świąt i tabela łącząca. |
| Usługi o różnej długości | Długość slotu jest atrybutem lokalizacji, nie usługi. Sztywna siatka 30 minut wynika wprost z treści zadania. |
| Specyfikacja OpenAPI | Zamiast niej opis endpointów powyżej i testy funkcjonalne jako kontrakt wykonywalny. |
| Soft delete i pełny audyt | Anulowanie zachowuje wiersz i `cancelled_at`, co pokrywa potrzebę historii w tym zakresie. |

### Co zmieniłbym w kolejnej iteracji

1. **Kontrakt API**: OpenAPI generowane z kodu i walidowane w CI, żeby dokumentacja
   nie rozjeżdżała się z implementacją.
2. **Model usług**: `services` z własnym czasem trwania i buforem, oraz zasoby
   (gabinet, pracownik) jako osobny wymiar dostępności. To największa zmiana
   koncepcyjna, bo rezerwacja przestaje być „slotem", a staje się przedziałem, przez
   co niezmiennik unikalności trzeba zastąpić wykluczaniem zakresów
   (w PostgreSQL: `EXCLUDE USING gist` na `tstzrange`, czysto i wciąż po stronie bazy).
3. **Klucze idempotencji dla `POST /api/appointments`**: dziś powtórzone żądanie
   (timeout po stronie klienta, retry proxy) dostanie `409`, bo slot zajęła jego
   własna pierwsza próba. Nagłówek `Idempotency-Key` zapisywany razem z rezerwacją
   pozwoliłby zwrócić ten sam zasób zamiast konfliktu. Anulowanie jest już
   idempotentne, więc to domknięcie tej samej zasady na tworzeniu.
4. **Test obciążeniowy współbieżności**: obecne testy dowodzą niezmiennika
   deterministycznie, ale nie mierzą, jak zachowuje się odsetek konfliktów pod
   realnym ruchem równoległym.
5. **Indeks `(location_id, status, starts_at)`**, jeśli lista filtrowana po statusie
   okaże się gorąca; dziś byłby to koszt zapisu bez potwierdzonej korzyści.
6. **Partycjonowanie i archiwizacja**, patrz sekcja o wydajności.
7. **Obserwowalność**: logowanie strukturalne z korelacją żądań i metryki
   biznesowe (konflikty rezerwacji, odsetek anulowań) obok metryk technicznych.

---

## Struktura projektu

```
app/
├── Casts/                      # UtcDateTime, CalendarDate, normalizacja czasu przy zapisie
├── Console/Commands/           # appointments:generate
├── Domain/Scheduling/          # logika biznesowa, niezależna od HTTP
│   ├── Enums/                  # AppointmentStatus
│   ├── Exceptions/             # wyjątki domenowe niosące kod HTTP i kod błędu
│   ├── Slots/                  # Slot, TimeInterval, SlotGenerator
│   ├── AppointmentBooker.php   # rezerwacja / anulowanie / przesunięcie
│   ├── AvailabilityService.php # wolne sloty dnia
│   ├── LocationResolver.php
│   └── ScheduleRepository.php  # godziny otwarcia i dni wolne (memoizowane)
├── Http/                       # kontrolery, form requesty, zasoby, middleware
├── Models/
└── Support/                    # LocalInstant, parsowanie czasu na granicy API
database/
├── factories/
├── migrations/
└── seeders/
tests/
├── Feature/
└── Unit/
compose.yaml                    # aplikacja + MySQL
Dockerfile
phpstan.neon                    # PHPStan poziom 8 (Larastan)
.github/workflows/ci.yml        # styl, analiza statyczna i testy na PHP 8.3 / 8.4
```
