# Estado del Proyecto Consultfest

**Fecha:** 14 de agosto de 2026
**Versión:** MVP demo interna
**Rama:** develop
**Suite de tests:** 209 tests, 566 assertions, todos verde

---

## Resumen Ejecutivo

Consultfest es una herramienta web para cineastas que ayuda a descubrir festivales de cine y recibir recordatorios antes de los deadlines de inscripción. La estrategia de datos es **live-on-demand contra FestivalAPI.com** (con cache interno de 1h), no sincronización local. Tiene auth completa (email/password + Google OAuth), un módulo de producciones del cineasta con matching contra festivales, sistema de suscripciones, y notificaciones transaccionales por email. Está en demo interna — usable por el developer y un cineasta de confianza, no desplegado público aún.

---

## Features que funcionan hoy

- [x] Scaffold Laravel 13 + Livewire 4 + Tailwind v4
- [x] Auth completa: register con email, login, "recordarme" (cookie HMAC), Google OAuth via Socialite, password reset
- [x] Búsqueda de festivales en vivo contra FestivalAPI.com (categoría, género, país, rango de fechas)
- [x] Cache de resultados 1h por hash de filtros (ahorra créditos cuando el usuario repite búsqueda)
- [x] Lazy redirect: el detalle solo se paga cuando el usuario hace click en una card (1 crédito)
- [x] Suscripción / desuscripción a festivales con notificación por email
- [x] CRUD de producciones del cineasta
- [x] Matcher: dado una producción, devuelve festivales rankeados por score (género, duración, categoría)
- [x] Dashboard básico del usuario autenticado
- [x] 7 notifications: welcome, subscribed, unsubscribed, production created, festival deadline, festival opening, password reset
- [x] Mail transactional vía Gmail SMTP con App Password (dev/demo) o Resend (producción planeada)
- [x] Throttling de rutas `productions` para evitar abuse
- [x] Theme dark con accent dorado cinematográfico
- [x] Rate limiter outbound `festivalapi` para no quemar créditos accidentalmente (30/min/IP)

---

## Stack

| Componente | Decisión | Notas |
|---|---|---|
| Framework | Laravel 13 | `Illuminate\Bus\Queueable`, `ShouldQueue`, `MailMessage`, `RateLimiter` |
| Interactividad | Livewire 4 | `WithPagination`, eventos Livewire |
| CSS | Tailwind v4 | Variables CSS custom, sin build de JS aparte |
| DB (dev) | SQLite | `database/database.sqlite`, migraciones + factories |
| DB (deploy) | PostgreSQL (planeado) | Migración cuando se haga deploy |
| Cache | database driver | Misma DB, tabla `cache` |
| Queue | database driver | Misma DB, tabla `jobs`, tabla `failed_jobs` |
| Mail dev | Gmail SMTP | `smtp.gmail.com:587 tls`, App Password en `.env` |
| Mail prod | Resend | Planeado, requiere dominio verificado |
| External API | FestivalAPI.com | Bearer token en `FESTIVAL_API_KEY`, 1 crédito/search |
| Auth externa | Laravel Socialite | Google OAuth client + secret |

---

## Modelos

**Festival** (`app/Models/Festival.php`)
```
api_id (string, único) — ID externo de FestivalAPI
name, category, country
deadline, opening_date
submission_fee, festival_score
accepting_submissions
details (JSON) — payload completo de la API
last_synced_at
```

**Subscriber** (`app/Models/Subscriber.php`)
```
id, name, last_name, email (único), phone
production_company
password (bcrypt vía cast `hashed`, nullable para cuentas Google-only)
notifications_enabled (bool, default true)
created_at
```

**Subscription** (`app/Models/Subscription.php`)
```
subscriber_id (FK)
festival_id (FK)
notification_type (opening|deadline|both)
notified_opening, notified_deadline (flags anti-duplicado)
```

**Production** (`app/Models/Production.php`) — añadido en feat/productions-module
```
id, subscriber_id (FK)
title, type (short|feature|documentary), genre, length, country, language, synopsis
```

---

## Componentes Livewire

| Componente | Vista | Responsabilidad |
|---|---|---|
| `FestivalCalendar` | `resources/views/livewire/festival-calendar.blade.php` | Filtros (fecha inicio/fin, categoría, género, país) + dispatch al `FestivalResults` |
| `FestivalResults` | `resources/views/livewire/festival-results.blade.php` | Lista paginada de festivales desde `FestivalSearchService`, links externos |
| `SubscriberForm` | `resources/views/livewire/subscriber-form.blade.php` | Registro rápido desde home para recibir notificaciones (legacy, auth ahora va por `AuthController`) |

---

## Servicios

**`FestivalApiService`** (`app/Services/FestivalApiService.php`)
- Cliente HTTP a `https://festivalapi.com/v1`
- Auth: `Authorization: Bearer <FESTIVAL_API_KEY>`
- Métodos: `searchFestivals($filters)`, `getScoredFestivals($filters)`, `syncFestivals()` (legacy, Estrategia A ya no usa), `getFestivalDetails($apiId)`
- Devuelve `[]` y loguea warning en errores 401/402/429/500/timeout — la UI degrada a "sin resultados", nunca 500 al usuario

**`FestivalSearchService`** (`app/Services/FestivalSearchService.php`)
- Orquesta `Cache::remember(key, 1h, fn) → FestivalApiService::searchFestivals() → FestivalData::fromApi()`
- Filtros: mapea `startDate/endDate` según `dateField` (deadline vs opening_date) a `deadline_*` o `event_date_*` en la API
- Filtra client-side festivales con deadline pasada (no aceptando submissions)
- Rate limit `festivalapi:<ip>` 30/min — devuelve colección vacía si excede

**`ProductionMatcher`** (`app/Services/ProductionMatcher.php`)
- Dada una Production, devuelve Collection<Festival> rankeados por score
- Score = match en género + categoría + duración + país
- Usado por `ProductionController::matches()`

---

## Notifications

Todas extienden `Notification implements ShouldQueue`, usan el canal `mail`, devuelven `MailMessage` con subject/greeting/introLines/action.

| Clase | Disparada desde | Asunto |
|---|---|---|
| `WelcomeNotification` | `AuthController::register()` | "¡Bienvenido a Consultfest!" |
| `FestivalSubscribedNotification` | `FestivalController::subscribe()` | "Te suscribiste a {festival}" |
| `FestivalUnsubscribedNotification` | `FestivalController::unsubscribe()` | "Te desuscribiste de {festival}" |
| `ProductionCreatedNotification` | `ProductionController::store()` | "Producción creada: {title}" |
| `FestivalDeadlineNotification` | (no se dispara actualmente) | "Deadline próximo: {festival}" |
| `FestivalOpeningNotification` | (no se dispara actualmente) | "Apertura próxima: {festival}" |
| `ResetPasswordNotification` | Laravel built-in | "Reset Password" |

Las dos notifications de "deadline" y "opening" existen como clase pero **no hay scheduler que las llame**. Si se reactiva, hay un command `festivals:check-deadlines` que las puede disparar (existe, no está conectado a cron).

---

## Routes

`routes/web.php` (orden aproximado):

**Públicas:**
- `GET /` — home (form de búsqueda + resultados)
- `GET /festivals` — `FestivalController@index` (lista)
- `GET /festivals/{id}` — `FestivalController@show` (detalle de festival local — legacy, la Estrategia A lo ignora)
- `GET /festivals/search` — `FestivalController@search` (vista de búsqueda con filtros)
- `GET /festivals/{apiId}/redirect` — `FestivalController@redirectToFestival` (lazy: enriquece con 1 crédito y redirige al sitio del festival)
- `GET /register`, `GET /login`, `GET /forgot-password`, `GET /reset-password/{token}` — formularios de auth
- `GET /auth/google`, `GET /auth/google/callback` — OAuth Google

**Protegidas (requieren sesión):**
- `GET /dashboard` — `DashboardController@index`
- `GET /productions`, `GET /productions/create`, `GET /productions/{id}`, `GET /productions/{id}/edit`, `GET /productions/{id}/matches`
- `POST /register`, `POST /login` — procesan forms
- `POST /forgot-password`, `POST /reset-password`
- `POST /subscribe` — throttle `subscribe`
- `POST /logout`
- `DELETE /unsubscribe/{festivalApiId}` — throttle `subscribe`

**Throttled:**
- `Route::middleware('throttle:productions')` agrupa CRUD de productions
- `POST /subscribe` tiene throttle propio

---

## Commands

- `php artisan festivals:sync` — sync legacy, ya no se usa en Estrategia A
- `php artisan festivals:check-deadlines [--days=N]` — revisa deadlines próximos y manda notificaciones, **no está conectado a scheduler**
- `php artisan festivals:reset-notifications` — limpia flags `notified_*` para re-testing

---

## Tests

- **209 tests, 566 assertions, todos verde** (`php artisan test`)
- **Feature** (`tests/Feature/`): AuthController, FestivalController, ProductionController, FestivalCalendar, FestivalResultsApi, FestivalResults
- **Unit** (`tests/Unit/`): `Data/FestivalDataTest`, `Services/FestivalSearchServiceTest`, `Services/ProductionMatcherTest`
- Convenciones: `Http::fake()` para mockear FestivalAPI, `Mail::fake()` y `Notification::fake()` para asserts de mail/notification
- CI-level guard: `AuthControllerTest::test_welcome_notification_builds_the_expected_mail_payload` valida el contenido del mail independientemente del mailer activo (regression-safe)

---

## Decisiones de diseño tomadas

1. **Estrategia A — live API, sin sync local.** Festivales se consultan en vivo a FestivalAPI; cache 1h por hash de filtros; lazy redirect para detalle. Decidido por ahorro de créditos y por mantener datos frescos.

2. **SQLite en dev, Postgres planeado en deploy.** SQLite es filesystem-based, los PaaS modernos tienen filesystem efímero → migrar a Postgres para deploy. Cero trabajo en código, solo `.env` + nueva migración.

3. **Gmail SMTP en dev, Resend en prod planeado.** Mientras no se tenga dominio propio verificado, Gmail App Password sirve. Cuando llegue `consultfest.com`, se cambia `MAIL_MAILER=resend` y se configura la API key de Resend. `.env.example` documenta ambos paths.

4. **Sin API REST para la app.** Todo va por Livewire + controllers tradicionales. No hay capa JSON expuesta, no hay `api.php` con tokens. Esto es intencional: la única API externa es FestivalAPI (cliente, no servidor).

5. **Auth dual: email/password + Google OAuth.** Cubrimos los dos flujos típicos sin third-party providers (sin Auth0, sin Clerk). Socialite es estándar Laravel.

6. **ShouldQueue en todas las notifications.** El usuario nunca espera un email. La cola es `database` (no Redis) para no sumar infra en demo.

7. **Rate limiter outbound `festivalapi`.** Bloquea scripts que podrían quemar créditos accidentalmente. 30/min/IP es generoso para humanos.

8. **Sin tracking/analytics todavía.** Ni Posthog, ni GA, ni Mixpanel. Cuando tengamos usuarios reales, lo evaluamos.

---

## Variables de entorno importantes

```env
APP_NAME=Consultfest
APP_ENV=local
APP_KEY=base64:...          # generada con php artisan key:generate
APP_URL=http://localhost

DB_CONNECTION=sqlite        # cambiar a pgsql en deploy

MAIL_MAILER=smtp            # o log (para dev offline) o resend (producción)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=orlandorojasoyarzun@gmail.com
MAIL_PASSWORD=<app-password-16-chars>   # de https://myaccount.google.com/apppasswords

FESTIVAL_API_KEY=fes_...   # de https://festivalapi.com/dashboard/api-keys/
RESEND_API_KEY=re_...      # solo si MAIL_MAILER=resend

GOOGLE_CLIENT_ID=...        # de console.cloud.google.com
GOOGLE_CLIENT_SECRET=...

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

---

## Comandos de mantenimiento

```bash
# Reset DB con seeds
php artisan migrate:fresh --seed

# Correr la cola una vez (procesa notifications pendientes)
php artisan queue:work --once

# Worker continuo (para dev: dejalo corriendo en otra terminal)
php artisan queue:work --stop-when-empty

# Tests
php artisan test

# Build assets
npm run build

# Disparar check-deadlines manualmente (no está conectado a scheduler)
php artisan festivals:check-deadlines --days=7

# Reset flags de notifications (para re-testing sin re-seed)
php artisan festivals:reset-notifications
```

---

## Problemas conocidos / limitaciones

1. **`FestivalDeadlineNotification` y `FestivalOpeningNotification` no se disparan.** Las clases existen, el command existe, pero no hay cron. Cuando se decida cómo y cuándo disparar (¿daily? ¿a la hora de suscribirse? ¿manual?), se conecta.

2. **SQLite en demo — sin backups.** Si el server muere, perdés subs/users. Para demo está OK; en deploy Postgres resuelve esto.

3. **`FestivalController::show` quedó como legacy.** En Estrategia A no se usa (los festivales no se persisten), pero el route sigue ahí por compatibilidad con código viejo. Tests lo siguen usando con `FestivalFactory`.

4. **PRs de las últimas 3 features tienen historia messy.** El usuario notó en el commit de PR #2 que `FestivalController` quedó donde no debía (parte del +86 fue a `feat/notifications` por error). El código funciona, los tests pasan, pero la separación de concerns entre PRs no es limpia. Decisión: dejarlo así, no vale reordenar historia por cosmética.

5. **Rate limiter outbound sin telemetría.** Si FestivalAPI nos throttlea, lo vemos en `storage/logs/laravel.log` pero no hay alerta. Aceptable para demo.

---

## Pendientes / next steps

1. **Deploy**: usuario + 1 cineasta amigo. Decidido: Postgres, opciones a evaluar (Railway.app, Hetzner + Coolify, Laravel Forge + DO).
2. **Scheduler de check-deadlines**: decidir frecuencia + implementación (cron en host vs `schedule:work` continuo vs Laravel Scheduler de Forge).
3. **Conectar `FestivalDeadline/OpeningNotification` al scheduler** una vez que exista.
4. **Migrar a Postgres**: cuando se haga deploy.
5. **Brand final**: nombre del producto, dominio, logo. Todo TBD.

---

## Recuperar contexto (para IA)

Si una sesión futura de Claude pierde contexto:

1. Lee este archivo entero (`PROJECT_STATUS.md`).
2. Lee `README.md` para la portada del proyecto.
3. Corre `php artisan test` — 209 tests verde = sistema sano.
4. Si querés datos frescos: `php artisan migrate:fresh --seed`.
5. Si una feature no anda, su test específico está en `tests/Feature/` o `tests/Unit/`.
6. Cambios pequeños en código: leé los archivos relevantes directamente (están bien organizados).
7. Cambios grandes o refactors: pedime un plan antes de tocar nada.

---

*Documento vivo. Última actualización: 14 de agosto de 2026.*
