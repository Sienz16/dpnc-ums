# Debate Judging System Frontend Implementation Plan (Gemini)

Date: 2026-04-23  
Project: `dpnc-ums` (Laravel 13 + Inertia v3 + Vue 3 + Tailwind v4)  
Scope: Frontend implementation only (backend already completed)

## 1) Objective

Build complete role-based frontend flows for:
- `superadmin`
- `judge`

Use backend as source of truth for all domain state (`status`, `completion_type`, `result_state`).

## 2) Non-Negotiable Implementation Rule

Gemini MUST reuse existing project components/layouts first and only create new components when no suitable existing component exists.

Required reuse starting points:
- Layouts:
  - `resources/js/layouts/AppLayout.vue`
  - `resources/js/layouts/app/AppSidebarLayout.vue`
  - `resources/js/layouts/app/AppHeaderLayout.vue`
- Core shared components:
  - `resources/js/components/Heading.vue`
  - `resources/js/components/Breadcrumbs.vue`
  - `resources/js/components/InputError.vue`
  - `resources/js/components/AlertError.vue`
  - `resources/js/components/PasswordInput.vue`
- Existing UI primitives under:
  - `resources/js/components/ui/*`

Do NOT introduce a parallel design system.
Do NOT bypass existing UI primitives with ad-hoc HTML styling when equivalent primitives already exist.

## 3) Backend Contract (Use As-Is)

### Admin endpoints
- `GET /admin/judges`
- `POST /admin/judges`
- `PATCH /admin/judges/{judge}`
- `GET /admin/rounds`
- `POST /admin/rounds`
- `PATCH /admin/rounds/{round}`
- `DELETE /admin/rounds/{round}`
- `GET /admin/rooms`
- `POST /admin/rooms`
- `PATCH /admin/rooms/{room}`
- `DELETE /admin/rooms/{room}`
- `GET /admin/teams`
- `POST /admin/teams`
- `GET /admin/teams/{team}`
- `PATCH /admin/teams/{team}`
- `DELETE /admin/teams/{team}`
- `POST /admin/teams/{team}/members`
- `PATCH /admin/teams/{team}/members/{member}`
- `DELETE /admin/teams/{team}/members/{member}`
- `GET /admin/matches`
- `POST /admin/matches`
- `GET /admin/matches/{match}`
- `PATCH /admin/matches/{match}`
- `POST /admin/matches/{match}/assignments/manual`
- `POST /admin/matches/{match}/assignments/randomize`
- `POST /admin/matches/{match}/force-complete`
- `POST /admin/matches/{match}/reopen`
- `GET /admin/rankings/teams`
- `GET /admin/rankings/speakers`
- `GET /admin/reports/matches/{match}`
- `GET /admin/reports/tournament`

### Judge endpoints
- `GET /judge/matches`
- `GET /judge/matches/{match}`
- `POST /judge/matches/{match}/check-in`
- `GET /judge/matches/{match}/score-sheet`
- `PUT /judge/matches/{match}/score-sheet/draft`
- `POST /judge/matches/{match}/score-sheet/submit`

## 4) Information Architecture

Create pages under `resources/js/pages/debate/*`:

### Superadmin pages
- `debate/admin/Judges/Index.vue`
- `debate/admin/Rounds/Index.vue`
- `debate/admin/Rooms/Index.vue`
- `debate/admin/Teams/Index.vue`
- `debate/admin/Teams/Show.vue`
- `debate/admin/Matches/Index.vue`
- `debate/admin/Matches/Show.vue`
- `debate/admin/Rankings/Teams.vue`
- `debate/admin/Rankings/Speakers.vue`
- `debate/admin/Reports/Tournament.vue`

### Judge pages
- `debate/judge/Matches/Index.vue`
- `debate/judge/Matches/Show.vue`

## 5) Phase-by-Phase Frontend Build Plan

## Phase A: Foundation and Navigation

Tasks:
- Extend app navigation for role-based menu entries:
  - Superadmin: judges/rounds/rooms/teams/matches/rankings/reports
  - Judge: assigned matches only
- Add route helpers/actions usage patterns consistent with existing app style (`resources/js/actions/*`).
- Establish shared debate types in `resources/js/types/debate.ts`.

Acceptance:
- Role-appropriate nav items only.
- Unauthorized pages hidden and guarded by backend response handling.

## Phase B: Master Data CRUD (Admin)

Tasks:
- Judges CRUD page.
- Rounds CRUD page.
- Rooms CRUD page.
- Teams + members management page.
- Use existing dialog/form/input/table primitives from `components/ui`.
- Validation errors rendered through existing `InputError`/form error pattern.

Acceptance:
- Full create/update/delete loop works for each module.
- Loading/empty/error states present.

## Phase C: Match Setup + Assignment (Admin)

Tasks:
- Matches list/detail/create/edit.
- Assignment panel in match detail:
  - Manual assignment (exact panel size)
  - Randomize assignment
- Enforce panel options in UI: `1`, `3`, `5`, `7` only.
- Show status badges from backend values.

Acceptance:
- Cannot submit invalid panel-size assignment.
- Assignment updates reflected immediately after response.

## Phase D: Judge Workflow

Tasks:
- Assigned matches list.
- Match detail:
  - Check-in action
  - Score sheet draft form
  - Final submit action (with confirmation)
- Score form fields must exactly match backend:
  - `mark_pm`, `mark_tpm`, `mark_m1`
  - `mark_kp`, `mark_tkp`, `mark_p1`
  - `mark_penggulungan_gov`, `mark_penggulungan_opp`
  - `best_debater_member_id`
- Show read-only/locked state based on backend response.

Acceptance:
- Judge can only operate on assigned matches.
- Submit lock behavior follows backend state exactly.

## Phase E: Match Operations + Result Display (Admin + Judge)

Tasks:
- On match detail show:
  - Winner side
  - Vote split
  - Official margin (1 decimal)
  - Official team scores (1 decimal)
  - Best speaker
  - `result_state` badge (`final`/`provisional`)
  - `completion_type` badge (`normal`/`force_completed`)
- Admin controls:
  - Force-complete modal requiring reason
  - Reopen modal requiring reason

Acceptance:
- Force-complete and reopen flows work with clear error handling.
- Reopened match behavior is reflected correctly (including preserved existing data).

## Phase F: Rankings + Reports (Admin)

Tasks:
- Team ranking page consuming backend-ordered output directly.
- Speaker ranking page consuming backend-ordered output directly.
- Tournament report page combining rankings and summary.
- Match report section linked from match detail.

Acceptance:
- No client-side reimplementation of ranking math.
- Data matches backend contract exactly.

## 6) Component Reuse Matrix (Mandatory)

Reuse this mapping before creating new components:
- Page headers and section headings: `Heading.vue`
- Breadcrumb trail: `Breadcrumbs.vue`
- Error alerts: `AlertError.vue`
- Field-level validation messages: `InputError.vue`
- Modal/sheet/dialog patterns: `components/ui/dialog/*`, `components/ui/sheet/*`
- Buttons/inputs/labels/selects/cards/tables/badges:
  - `components/ui/button/*`
  - `components/ui/input/*`
  - `components/ui/label/*`
  - `components/ui/select/*`
  - `components/ui/card/*`
  - `components/ui/badge/*`
- Sidebar + shell composition:
  - `AppSidebar.vue`, `AppShell.vue`, `AppHeader.vue`

New components are allowed only when there is no equivalent in the above.

## 7) State/Data Handling Rules

- Backend is authoritative for match transitions.
- After mutating actions (check-in, draft save, submit, assign, force-complete, reopen), refetch detail/list.
- Keep optimistic UI minimal for critical actions.
- Show backend validation error messages directly.

## 8) UX Guardrails

- Disable actions that backend clearly disallows based on returned state.
- Confirm destructive/irreversible actions:
  - final submit
  - force-complete
- Apply 1-decimal formatting for official aggregates.
- Use explicit badges for `status`, `completion_type`, `result_state`.

## 9) Testing/QA Checklist For Gemini

- Superadmin can access all admin debate pages.
- Judge cannot access admin debate pages.
- Judge only sees assigned matches.
- Score sheet form mirrors backend fields exactly.
- Submit lock and reopen behavior match backend logic.
- Force-complete requires reason and surfaces errors properly.
- Rankings pages render backend order without local reshuffle.
- All pages have loading, empty, and error states.
- Responsive behavior verified on mobile + desktop.

## 10) Suggested Execution Order (Fastest Path)

1. Navigation + shared types
2. Admin master data CRUD (judges/rounds/rooms/teams)
3. Match setup + assignment
4. Judge match + score flow
5. Force-complete/reopen + result badges
6. Rankings + reports
7. QA pass and polish

## 11) Handoff Note To Gemini

Build with existing Vue/Inertia patterns already present in this repository. Reuse existing components/layouts first. Avoid introducing a second UI style system. Focus on backend-contract correctness and complete role workflows.
