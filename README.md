# DPNC UMS

<p align="left">
  <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white">
  <img alt="PHP 8.5" src="https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white">
  <img alt="Vue 3" src="https://img.shields.io/badge/Vue-3.x-42B883?logo=vuedotjs&logoColor=white">
  <img alt="Inertia v3" src="https://img.shields.io/badge/Inertia-v3-9553E9?logo=inertia&logoColor=white">
  <img alt="Tailwind CSS v4" src="https://img.shields.io/badge/Tailwind_CSS-v4-38BDF8?logo=tailwindcss&logoColor=white">
  <img alt="Pest 4" src="https://img.shields.io/badge/Pest-4.x-95459B">
</p>

DPNC UMS is a debate tournament management and judging system built with Laravel, Inertia, and Vue.

It handles the practical tournament work: registering teams and rooms, scheduling matches, assigning judges, collecting score sheets, calculating results, and producing rankings. The app has two main roles, `superadmin` for tournament operations and `judge` for assigned-match scoring.

This is not a generic admin panel. Most of the code is shaped around debate-specific rules: government vs opposition sides, judge panels, score-sheet locking, force completion, match-specific speaker lineups, and ranking calculations.

## What It Does

For superadmins:

- Manage judges, rounds, rooms, teams, and team members.
- Create and delete matches.
- Assign judge panels manually or by randomization.
- Set match-specific speaker lineups without changing the base team roster.
- Force-complete matches when some judging data is missing.
- Reopen completed matches for corrections.
- Correct judge score sheets from the admin view.
- View match reports, tournament reports, team rankings, and speaker rankings.

For judges:

- View only matches assigned to them.
- Check in before scoring.
- Save score sheets as drafts.
- Submit final score sheets.
- See match status and result information after completion.

The backend is the source of truth. The frontend renders state from the API instead of inventing its own tournament logic.

## Stack

| Layer | Technology |
| --- | --- |
| Backend | Laravel 13, PHP 8.4+ |
| Auth | Laravel Fortify, session auth, email verification support |
| Frontend | Inertia v3, Vue 3, TypeScript |
| Styling | Tailwind CSS v4, Reka UI primitives, lucide-vue-next icons |
| Routing helpers | Laravel Wayfinder |
| Database | SQLite for quick local setup, PostgreSQL-ready for production |
| Queue/session/cache | Laravel database drivers by default |
| Tests | Pest 4, PHPUnit 12 |
| Formatting | Laravel Pint, Prettier, ESLint |
| CI | GitHub Actions on PHP 8.4 and 8.5 |

## Domain Model

The main tables are:

- `users`, with `superadmin` and `judge` roles.
- `rounds`, tournament rounds such as `Pusingan 1`.
- `rooms`, debate rooms or sidang locations.
- `teams` and `team_members`.
- `matches`, using the `DebateMatch` model because `Match` is not a great PHP class name.
- `judge_assignments`, one row per judge assigned to a match.
- `score_sheets`, one row per judge per match.
- `match_speakers`, optional lineup overrides for a specific match.
- `match_results`, the materialized result snapshot.
- `audit_logs`, used for force-complete and reopen actions.

Important enums live in `app/Domain/Debate/Enums`:

- `UserRole`: `superadmin`, `judge`
- `MatchStatus`: `pending`, `in_progress`, `completed`
- `MatchCompletionType`: `normal`, `force_completed`
- `MatchResultState`: `provisional`, `final`
- `JudgePanelSize`: `1`, `3`, `5`, `7`
- `ScoreSheetState`: `draft`, `submitted`
- `TeamSide`: `government`, `opposition`
- `SpeakerPosition`: `speaker_1`, `speaker_2`, `speaker_3`, `speaker_4`

## Match Lifecycle

A match starts as `pending`.

When every assigned judge checks in, the match moves to `in_progress`.

When every assigned judge submits a final score sheet, the match moves to `completed` with:

- `completion_type = normal`
- `result_state = final`

A superadmin can force-complete a match. If some judges have not submitted, the result is marked provisional:

- `completion_type = force_completed`
- `result_state = provisional`

If every judge has submitted and the admin force-completes anyway, the result is still final.

Completed matches can be reopened for correction. Reopen and force-complete actions write audit logs with reasons.

## Scoring Rules

Each submitted score sheet stores:

| Side | Fields |
| --- | --- |
| Government | `mark_pm`, `mark_tpm`, `mark_m1`, `mark_penggulungan_gov` |
| Opposition | `mark_kp`, `mark_tkp`, `mark_p1`, `mark_penggulungan_opp` |
| Shared | `margin`, `winner_side`, `best_debater_member_id` |

Per-sheet totals are calculated by `ScoreSheetService`.

Match results are calculated by `MatchResultCalculator`:

- Winner is determined by majority judge votes.
- Vote split is stored as winner and loser vote counts.
- Official margin is the average submitted margin, rounded to 1 decimal.
- Official team scores are averaged, rounded to 1 decimal.
- Best speaker is selected by best-debater votes, with score-based tie-breaking.

Ranking logic lives in `RankingService`.

## Local Setup

### Requirements

Use versions close to CI and production:

- PHP 8.4 or newer. PHP 8.5 is recommended because the Docker image uses it.
- Composer 2.
- Node 22.
- npm.
- SQLite for the quickest local setup, or PostgreSQL if you want to match production more closely.

### Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

The default `.env.example` uses SQLite. Create the file before migrating:

```bash
mkdir -p database
touch database/database.sqlite
php artisan migrate --seed
```

Build frontend assets once:

```bash
npm run build
```

Or start the full dev loop:

```bash
composer run dev
```

That command runs the Laravel server, queue listener, Laravel Pail logs, and Vite together.

If you prefer separate terminals:

```bash
php artisan serve
npm run dev
php artisan queue:listen --tries=1 --timeout=0
```

## Demo Accounts

The seeder creates one superadmin and ten judges.

| Role | Email | Password |
| --- | --- | --- |
| Superadmin | `admin@example.com` | `12345678` |
| Judge | `judge1@example.com` through `judge10@example.com` | `12345678` |

Do not use these credentials in production. They are development fixtures.

## App Routes

Main browser routes:

| Area | Path |
| --- | --- |
| Welcome | `/` |
| Dashboard | `/dashboard` |
| Admin judges | `/debate/admin/judges` |
| Admin rounds | `/debate/admin/rounds` |
| Admin rooms | `/debate/admin/rooms` |
| Admin teams | `/debate/admin/teams` |
| Admin matches | `/debate/admin/matches` |
| Admin team rankings | `/debate/admin/rankings/teams` |
| Admin speaker rankings | `/debate/admin/rankings/speakers` |
| Admin tournament report | `/debate/admin/reports/tournament` |
| Judge matches | `/debate/judge/matches` |

API-style JSON routes are mounted under `/admin/*` and `/judge/*` with role middleware.

Wayfinder generates typed route helpers for the frontend. Prefer generated imports from `@/routes` and `@/actions` over hardcoded paths in Vue code.

## Common Commands

```bash
# Laravel tests
php artisan test --compact

# Pest directly
./vendor/bin/pest --compact

# PHP formatting
vendor/bin/pint --dirty --format agent

# PHP formatting check
composer lint:check

# Frontend build
npm run build

# Frontend lint check
npm run lint:check

# Frontend type check
npm run types:check

# Frontend format check
npm run format:check
```

For a pre-push sweep:

```bash
npm run lint:check
npm run types:check
npm run build
php artisan test --compact
```

`composer ci:check` also exists, but note that it includes frontend format checking and the full Laravel test script.

## Testing

Feature tests for the debate system live in `tests/Feature/Debate`.

Current coverage includes:

- Authorization boundaries.
- Admin match management.
- Judge assignment.
- Judge check-in and score submission.
- Force-complete and reopen flows.
- Match-specific lineup management.
- Team member validation.
- Inertia frontend route availability.

Unit tests for calculation and ranking live in `tests/Unit/Debate`.

Run a focused file while working:

```bash
php artisan test --compact tests/Feature/Debate/MatchManagementTest.php
```

Run one test by name:

```bash
php artisan test --compact --filter="superadmin can delete match"
```

## CI

GitHub Actions runs two workflows.

`tests`:

- PHP matrix: 8.4 and 8.5.
- Node 22.
- `npm i`.
- `composer install --no-interaction --prefer-dist --optimize-autoloader`.
- `npm run build`.
- `./vendor/bin/pest`.

`linter`:

- PHP 8.4.
- `composer lint` for Pint.
- `npm run format` for frontend formatting.
- `npm run lint` for ESLint fixes.

PHP 8.3 is intentionally not in the test matrix because the current lockfile installs Symfony 8 packages that require PHP 8.4 or newer.

## Project Layout

```text
app/
  Domain/Debate/Enums/       Debate-specific enums
  Domain/Debate/Services/    Business logic for assignment, lifecycle, scoring, ranking
  Http/Controllers/Admin/    Superadmin JSON endpoints
  Http/Controllers/Judge/    Judge JSON endpoints
  Http/Requests/             Form request validation
  Models/                    Eloquent models

database/
  factories/                 Test factories
  migrations/                Schema
  seeders/                   Demo users

resources/js/
  pages/debate/admin/        Superadmin Inertia pages
  pages/debate/judge/        Judge Inertia pages
  routes/                    Wayfinder-generated route helpers
  actions/                   Wayfinder-generated controller action helpers
  types/debate.ts            Frontend debate domain types

routes/
  debate.php                 Role-protected JSON endpoints
  debate_frontend.php        Role-protected Inertia pages
  web.php                    App entry routes

tests/
  Feature/Debate/            Debate feature tests
  Unit/Debate/               Calculation and ranking tests
```

## Deployment Notes

There is a production-oriented `Dockerfile` using PHP 8.5 FPM on Alpine with Nginx and built frontend assets.

The image installs PostgreSQL PHP extensions, Redis, Composer dependencies, and Node assets. At runtime you should provide production environment variables for:

- `APP_KEY`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL`
- `DB_CONNECTION=pgsql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- session, queue, cache, and mail settings as needed

Run migrations as part of deployment:

```bash
php artisan migrate --force
```

Laravel Cloud is a good fit if you want the shortest Laravel-native deployment path.

## Existing Planning Docs

The repo includes earlier implementation notes under `docs/superpowers`:

- Backend spec: `docs/superpowers/specs/2026-04-23-debate-judging-system-design.md`
- Frontend handoff: `docs/superpowers/specs/2026-04-23-debate-judging-system-frontend-handoff.md`
- Backend plan: `docs/superpowers/plans/2026-04-23-debate-judging-system-backend-implementation-plan.md`
- Frontend plan: `docs/superpowers/plans/2026-04-23-debate-judging-system-frontend-implementation-plan.md`

Those files are useful for background. This README should be treated as the current entry point for running and understanding the app.

## Notes for Contributors

A few project rules that save time:

- Keep debate business logic in services, not Vue pages or controllers.
- Use Form Requests for backend validation.
- Use policies or middleware for role boundaries.
- Use Wayfinder helpers in the frontend instead of hardcoded route strings.
- Keep score and ranking math server-side.
- Add or update Pest tests for behavior changes.
- Run Pint after PHP changes.

Small project, real workflow. Treat the tournament data like it matters, because on tournament day it does.
