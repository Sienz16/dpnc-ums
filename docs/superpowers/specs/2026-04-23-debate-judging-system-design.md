# Debate Judging System Backend Specification (Backend-First, Framework-Agnostic)

Date: 2026-04-23  
Scope: Backend only. Frontend visual/interaction design is intentionally out of scope.

## 1) Product Goal

Build a tournament judging system where:
- Superadmin manages judges, rounds, rooms (sidang), teams, and matches.
- Judges log in, view assigned matches, check in, and submit scoring forms.
- Each match result is derived from judge submissions and can support provisional completion.
- Rankings are available to superadmin only.

## 2) Roles and Access

Roles:
- `superadmin`
- `judge`

Permissions:
- `superadmin` can CRUD judges, rounds, rooms, teams, members, matches, assignments; force-complete/reopen match; view rankings/reports.
- `judge` can view only assigned matches; check in; save draft score sheet; submit final score sheet.

## 3) Core Domain Model

### User
- Fields: `id`, `name`, `email`, `password_hash`, `role`, `is_active`, timestamps
- Role enum: `superadmin`, `judge`

### Round
- Fields: `id`, `name`, `sequence` (optional), timestamps
- Dynamic naming allowed (`Pusingan 1`, `Pusingan 2`, custom names)

### Room
- Fields: `id`, `name`, `location` (optional), `is_active`, timestamps
- Reusable across rounds and matches

### Team
- Fields: `id`, `name`, `institution` (optional), `is_active`, timestamps

### TeamMember
- Fields: `id`, `team_id`, `full_name`, `speaker_position`, `is_active`, timestamps
- Speaker position enum for this system:
  - Government side positions: `PM`, `TPM`, `M1`
  - Opposition side positions: `KP`, `TKP`, `P1`

### Match (Debate Session)
- Fields:
  - `id`, `round_id`, `room_id`
  - `government_team_id`, `opposition_team_id`
  - `judge_panel_size` enum: `1`, `3`, `5`, `7`
  - `status` enum: `pending`, `in_progress`, `completed`
  - `completion_type` enum nullable: `normal`, `force_completed`
  - `result_state` enum nullable: `provisional`, `final`
  - `scheduled_at` (optional), timestamps

### JudgeAssignment
- Fields: `id`, `match_id`, `judge_id`, `assigned_mode`, `checked_in_at`, `submitted_at`, timestamps
- `assigned_mode` enum: `manual`, `random`
- Unique constraint: `(match_id, judge_id)`

### ScoreSheet (one per judge per match)
- Fields:
  - `id`, `match_id`, `judge_id`
  - Government speaker marks: `mark_pm`, `mark_tpm`, `mark_m1`
  - Opposition speaker marks: `mark_kp`, `mark_tkp`, `mark_p1`
  - Penggulungan marks: `mark_penggulungan_gov`, `mark_penggulungan_opp`
  - Derived totals: `gov_total`, `opp_total`, `margin`
  - `winner_side` enum: `government`, `opposition`
  - `best_debater_member_id` (must be one of the six members in this match)
  - `state` enum: `draft`, `submitted`
  - `submitted_at`, timestamps
- Unique constraint: `(match_id, judge_id)`

### MatchResult (materialized/frozen snapshot)
- Fields:
  - `id`, `match_id`
  - `winner_side`
  - `winner_vote_count`, `loser_vote_count`
  - `official_margin`
  - `official_team_score_government`, `official_team_score_opposition`
  - `best_speaker_member_id`
  - `is_force_completed`, `is_provisional`
  - `calculated_at`, timestamps
- Unique constraint: `(match_id)`

### AuditLog
- Fields: `id`, `actor_user_id`, `entity_type`, `entity_id`, `action`, `reason`, `metadata_json`, timestamps
- Required for force-complete and reopen actions.

## 4) Match and Status Lifecycle

Initial:
- Match starts as `pending`.

To `in_progress`:
- Transition when all assigned judges have `checked_in_at` populated.

To `completed` (normal):
- When all assigned judges submit score sheets (`state = submitted`).
- `completion_type = normal`
- `result_state = final`

To `completed` (force):
- Superadmin may force-complete before all submissions.
- Force-complete requires reason and audit log.
- `completion_type = force_completed`
- `result_state = provisional` if any assigned judge has not submitted.
- `result_state = final` if all assigned judges already submitted at action time.

Optional reopen:
- Superadmin can reopen `completed` match to `in_progress`.
- Reopen must be audited.

## 5) Judge Assignment Rules

- Panel sizes allowed: `1`, `3`, `5`, `7`.
- Superadmin can assign manually or randomize.
- Randomization must satisfy target panel size and avoid duplicate assignment.
- A match cannot start scoring without fully assigned panel.

## 6) Scoring and Calculation Rules

Form mirrors manual paper exactly:
- 6 speaker marks: `PM`, `TPM`, `M1`, `KP`, `TKP`, `P1`
- 2 penggulungan marks: one per side
- judge-selected best debater

Per score sheet:
- `gov_total = mark_pm + mark_tpm + mark_m1 + mark_penggulungan_gov`
- `opp_total = mark_kp + mark_tkp + mark_p1 + mark_penggulungan_opp`
- `winner_side = government if gov_total > opp_total else opposition`
- `margin = abs(gov_total - opp_total)`

Round/precision:
- Stored and displayed with `1` decimal place for official aggregates.

Match final result:
- Winner determined by majority judge votes (e.g., 2-1, 3-2, 4-3).
- `judges_count` represented by `winner_vote_count:loser_vote_count`.
- `official_margin = average(margin of submitted judge sheets)`, rounded to 1 decimal.
- Official team scores:
  - `official_team_score_government = average(gov_total of submitted sheets)`, 1 decimal
  - `official_team_score_opposition = average(opp_total of submitted sheets)`, 1 decimal
- Best speaker:
  - Primary: most `best_debater` votes from submitted sheets
  - Tie-breaker: higher average speaker score across submitted sheets

## 7) Rankings (Superadmin Only)

Rankings are overall tournament rankings (not per-round ranking screens).

Team ranking order:
1. Higher `win_count`
2. Higher `judge_count` (total winning-vote count accumulated from match vote splits)
3. Higher average margin
4. Higher average team score

Speaker ranking order:
1. Higher average official speaker points per appearance
2. Higher best-speaker wins count
3. Higher average score per appearance

Note:
- This spec uses average-based ranking to stay fair across unequal appearance counts.

## 8) Validation Rules

Mandatory:
- Match has exactly two distinct teams (`government_team_id != opposition_team_id`).
- Team member positions must be valid and unique within a team roster for the six debate slots.
- Score sheet allowed only for assigned judge of that match.
- Score sheet submit allowed once; edits blocked after submit unless match reopened.
- `best_debater_member_id` must belong to one of the six members in the match.

Panel integrity:
- Number of assignments must equal `judge_panel_size`.
- `in_progress` requires all assigned judges checked in.

## 9) API/Service Requirements (Minimum)

Superadmin endpoints/services:
- Judge management
- Round management
- Room management
- Team + member management
- Match creation/update
- Judge assignment manual/random
- Match status dashboard
- Force-complete/reopen
- Rankings and reports

Judge endpoints/services:
- Assigned match list
- Check-in action
- Save draft score sheet
- Submit final score sheet
- View own submissions/history

System services:
- Match result calculator (idempotent)
- Status transition handler
- Ranking aggregator
- Audit logger

## 10) Reporting Requirements

Per match report:
- Winner
- Vote split (judge count)
- Margin
- Best speaker
- Team totals
- Per-judge submitted sheets

Tournament report:
- Team ranking table
- Speaker ranking table
- Filter by round/date/status (optional for v1)

## 11) Non-Functional Requirements

- Authorization enforced server-side for every endpoint.
- All state transitions auditable.
- Idempotent calculations to prevent double counting.
- Transactions for submit/finalize flow to avoid race conditions.
- Clear error messages for invalid transitions and validation failures.

## 12) Backend Delivery Phases

Phase 1:
- Auth + roles
- Core entities and CRUD
- Match creation and judge assignment

Phase 2:
- Judge check-in
- Draft/submit score sheet
- Auto status transitions

Phase 3:
- Result calculation + force-complete + audit
- Rankings
- Reporting/export basics

## 13) Framework Portability Note

This spec is intentionally framework-agnostic and can be implemented in Rails or Laravel without logic changes.  
If switching to Laravel, map these domain entities to Eloquent models, policies/gates, FormRequest validation, and queued jobs/listeners for result/ranking recalculation.
