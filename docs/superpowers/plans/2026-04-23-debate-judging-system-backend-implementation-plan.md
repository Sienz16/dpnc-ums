# Debate Judging System Backend Implementation Plan (Laravel)

Date: 2026-04-23  
Project: `dpnc-ums` (Laravel 13 + Fortify + Inertia starter)  
Scope: Backend-first delivery. Frontend can be implemented later using provided contracts.

## 1. Delivery Strategy

Build in vertical slices so each phase is testable and demoable:
1. Foundation and access control
2. Tournament master data (rounds, rooms, teams, members)
3. Match setup and judge assignment
4. Judge scoring flow (check-in, draft, submit)
5. Result calculation and completion logic
6. Rankings and reporting

## 2. Technical Conventions

- Keep business logic out of controllers; use service classes.
- Use FormRequest for validation.
- Use Policies/Gates for role authorization.
- Use DB transactions for submit/finalize operations.
- Use enums for status/role/sides/panel sizes.
- Keep calculations idempotent in a dedicated calculator service.

Suggested folders:
- `app/Domain/Debate/Enums`
- `app/Domain/Debate/Services`
- `app/Domain/Debate/DataTransferObjects`
- `app/Http/Controllers/Admin/*`
- `app/Http/Controllers/Judge/*`
- `app/Http/Requests/Admin/*`
- `app/Http/Requests/Judge/*`

## 3. Phase Plan

### Phase 1: Foundation (Auth + Roles)

Tasks:
- Add `role` and `is_active` to `users` table.
- Role enum: `superadmin`, `judge`.
- Seed one superadmin account.
- Add role middleware/policy checks.
- Hide all debate modules from non-authorized roles.

Deliverable:
- Superadmin and judge separation working server-side.

Tests:
- Feature tests for authorization boundaries.

### Phase 2: Master Data Modules

Entities:
- `rounds`
- `rooms`
- `teams`
- `team_members`

Tasks:
- Create migrations, models, factories.
- Implement admin CRUD endpoints/controllers.
- Enforce team member speaker positions and uniqueness rules.

Deliverable:
- Superadmin can manage rounds/rooms/teams with valid rosters.

Tests:
- Validation tests for member positions.
- CRUD authorization tests.

### Phase 3: Match Setup + Assignment

Entities:
- `matches`
- `judge_assignments`

Tasks:
- Create match with:
  - `round_id`, `room_id`, `government_team_id`, `opposition_team_id`
  - `judge_panel_size` allowed: `1`, `3`, `5`, `7`
  - `status = pending`
- Implement manual assignment.
- Implement randomized assignment service with uniqueness and panel-size checks.
- Prevent duplicate judge assignment per match.

Deliverable:
- Superadmin can create match and assign judges manually or random.

Tests:
- Panel size enforcement.
- Random assignment correctness.
- Duplicate prevention.

### Phase 4: Judge Workflow (Check-in + Scoresheet)

Entities:
- `score_sheets`

Tasks:
- Judge assigned-match list endpoint.
- Check-in endpoint (`checked_in_at` on assignment).
- Auto transition `pending -> in_progress` when all assigned judges checked in.
- Draft score save endpoint.
- Submit score endpoint:
  - lock sheet after submit
  - set `submitted_at`
  - mark assignment `submitted_at`
- Validate best debater belongs to the six active members in this match.

Deliverable:
- Judges can complete paper-equivalent scoring flow.

Tests:
- Judge can only access assigned matches.
- Submit lock behavior.
- Status transition to `in_progress`.

### Phase 5: Result Calculation + Completion

Entities:
- `match_results`
- `audit_logs`

Tasks:
- Build `MatchResultCalculator` service:
  - per-sheet totals and winner
  - majority vote winner by judge count
  - vote split storage (`winner_vote_count`, `loser_vote_count`)
  - official averages to 1 decimal
  - best speaker by vote count, tie-break by average speaker mark
- Auto complete (`completion_type=normal`, `result_state=final`) when all assigned judges submitted.
- Force-complete admin endpoint:
  - requires reason
  - writes audit log
  - `result_state=provisional` if missing submissions
- Optional reopen endpoint with audit.

Deliverable:
- Match has consistent final/provisional result and auditable state transitions.

Tests:
- Majority vote scenarios (1/3/5/7 panels).
- Provisional vs final logic.
- Force-complete and reopen audit tests.

### Phase 6: Rankings + Reports

Read models/services:
- Team ranking aggregate (overall tournament)
- Speaker ranking aggregate (overall tournament)

Team ranking order:
1. Win count
2. Judge count (accumulated winning vote count)
3. Average margin
4. Average team score

Speaker ranking:
- Average official speaker points per appearance
- Tie-breakers from spec

Tasks:
- Build query services for ranking endpoints.
- Add superadmin-only report endpoints:
  - match summary
  - tournament ranking summary

Deliverable:
- Superadmin-only tournament leaderboard and reports.

Tests:
- Ranking order correctness with seeded fixtures.

## 4. Database Migration Order

Apply in this order:
1. Alter `users` (role, is_active)
2. `rounds`
3. `rooms`
4. `teams`
5. `team_members`
6. `matches`
7. `judge_assignments`
8. `score_sheets`
9. `match_results`
10. `audit_logs`

Rationale:
- Avoid foreign key dependency issues and keep rollback clear.

## 5. Suggested API Surface (Backend Contract)

Admin:
- `POST /admin/judges`, `GET /admin/judges`, `PATCH /admin/judges/{id}`
- `Resource /admin/rounds`
- `Resource /admin/rooms`
- `Resource /admin/teams` (+ nested members)
- `Resource /admin/matches`
- `POST /admin/matches/{match}/assignments/manual`
- `POST /admin/matches/{match}/assignments/randomize`
- `POST /admin/matches/{match}/force-complete`
- `POST /admin/matches/{match}/reopen`
- `GET /admin/rankings/teams`
- `GET /admin/rankings/speakers`
- `GET /admin/reports/matches/{match}`

Judge:
- `GET /judge/matches`
- `POST /judge/matches/{match}/check-in`
- `PUT /judge/matches/{match}/score-sheet/draft`
- `POST /judge/matches/{match}/score-sheet/submit`
- `GET /judge/matches/{match}/score-sheet`

Note:
- Route names and prefixes can be adjusted to fit existing route organization.

## 6. Service Layer Breakdown

Create services:
- `JudgeAssignmentService`
- `MatchStatusService`
- `ScoreSheetService`
- `MatchResultCalculator`
- `RankingService`
- `AuditLogService`

Use events/listeners where useful:
- `ScoreSheetSubmitted` -> `RecalculateMatchResult` -> `TryAutoCompleteMatch`

## 7. Testing Plan (Pest)

Feature tests:
- Role access control
- Admin CRUD flows
- Judge assignment and check-in
- Score submit and locking
- Match completion transitions

Unit tests:
- Result calculator
- Ranking rules
- Status transition helper

Test data:
- Factories for round/room/team/member/match/assignment/scoresheet.
- Deterministic fixtures for vote split and tie-break scenarios.

## 8. Execution Checklist

1. Implement migrations + enums + models
2. Implement role authorization and seed superadmin
3. Implement admin master data CRUD
4. Implement match + assignment flows
5. Implement judge scoring flow
6. Implement result calculator + completion logic
7. Implement rankings/report endpoints
8. Complete tests and fix failing cases
9. Publish API contract for frontend handoff

## 9. Out of Scope (This Plan)

- Final frontend visual design
- Real-time websocket dashboards
- Multi-tournament tenancy
- Advanced bracket generation

## 10. Handoff Notes for Frontend Team

- Frontend should consume backend state as source of truth (`status`, `result_state`, `completion_type`).
- Do not duplicate ranking/calculation logic client-side.
- Use the existing frontend handoff doc:
  - `docs/superpowers/specs/2026-04-23-debate-judging-system-frontend-handoff.md`
