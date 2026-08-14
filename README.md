# Consultfest

**Film festival discovery and deadline tracking for independent filmmakers.**

A web tool that helps filmmakers find cinema festivals by date, category, genre or country; build a watchlist; and get email reminders before submissions close. Built as an internal demo to validate the product before committing to public launch.

---

## What it does

The user lands on a two-column layout: filters on the left, festival results on the right. Each result is a card showing the festival name, country, deadline, fee and a quality score. Clicking a card opens the organizer's submission URL in a new tab. Authenticated users can register productions (their own short films or features) and the app will match them against festivals by genre, length and category.

The app also sends transactional emails when certain user actions happen: account creation, festival subscribe/unsubscribe, and production creation. Emails go through Gmail SMTP using a 16-character App Password while the product is in demo mode.

---

## Status

**Active internal demo.** Not yet publicly distributed, no public install instructions, no production hosting. Live data is fetched on demand from FestivalAPI.com (paid external service); dev and demo use a SQLite database seeded by `FestivalFactory`. The product brand, domain and final feature set are still in flux.

---

## Tech stack

| Layer | Choice | Why |
|---|---|---|
| Framework | Laravel 13 | Eloquent + queue + notifications + mail out of the box |
| Interactivity | Livewire 4 | Reactive components without a separate JS build |
| Styling | Tailwind CSS v4 | Design tokens via CSS variables |
| Database (dev) | SQLite | Zero-config local development |
| Database (deploy, planned) | PostgreSQL | Standard for production, free tier on most hosts |
| Mail (dev/demo) | Gmail SMTP | Already has the user's account, App Password gives a free SMTP relay |
| Mail (production, planned) | Resend | Transactional email API, needs verified domain |
| External data | FestivalAPI.com | ~12k festivals, 1 credit per search, cached 1h internally |
| Cache + queue | Database driver | No Redis needed for low traffic |
| Auth | Email/password + Google OAuth (Socialite) | Standard stack, no third-party auth provider |

---

## Architecture

### Two data sources

The app deliberately has **two sources of truth** for festivals, and that's by design:

1. **FestivalAPI.com** (live): every search hits the external API, results are cached in the DB for one hour, then discarded. The list view only pays one credit regardless of how many cards the user scrolls. Clicking a card costs a second credit to enrich details before redirecting to the organizer's site. This is Strategy A — chosen over persisting everything locally — because FestivalAPI is a paid service and we want to be deliberate about credit usage.

2. **SQLite (dev only)**: a `FestivalFactory` seeds a small dataset so the app can be developed and tested without burning FestivalAPI credits. In production this dataset is irrelevant.

The `FestivalData` DTO sits between the API and the Livewire components so the components never talk to the HTTP client directly.

### Notifications, not transactional email services

The app uses Laravel's `Notification` system with the mail channel. Each notification is `ShouldQueue` so emails go out via the database queue without blocking user-facing requests. The list of notifications today: `WelcomeNotification` (on register), `FestivalSubscribedNotification` / `FestivalUnsubscribedNotification`, `ProductionCreatedNotification`, plus the stock `ResetPasswordNotification`. Festival-deadline reminders (`FestivalDeadlineNotification`, `FestivalOpeningNotification`) exist as classes but the scheduled job that would dispatch them is currently off.

### Where the code lives

```
app/
  Data/                  # DTOs that decouple UI from external APIs (FestivalData)
  Http/Controllers/      # FestivalController, ProductionController, AuthController, GoogleController, PasswordResetController, DashboardController
  Livewire/              # FestivalCalendar (filters), FestivalResults (list), SubscriberForm
  Models/                # Festival, Subscriber, Subscription, Production
  Notifications/         # 7 notification classes, all ShouldQueue
  Services/              # FestivalApiService (HTTP), FestivalSearchService (cache + map), ProductionMatcher (scoring)
database/                # migrations, factories, seeders
resources/views/         # Blade templates for every page + Livewire component view
tests/                   # 209 tests, 566 assertions, Feature + Unit split
```

---

## A note on this repo

This README intentionally has **no installation instructions**. The app is configured per-developer and the demo data is local. If you have access to this repo you're expected to know how to spin it up; if you're reading it publicly, the demo is the artifact, not the codebase.

The deeper state of the system — what works, what's pending, where things live — is documented in `PROJECT_STATUS.md`.
