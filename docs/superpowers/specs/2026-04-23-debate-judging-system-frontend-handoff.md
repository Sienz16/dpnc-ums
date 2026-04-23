# Frontend Handoff Guide for Gemini (Backend-First Project)

Date: 2026-04-23  
Audience: Frontend implementer (Gemini)  
Purpose: Build frontend on top of backend contracts. Do not lock visual design in this guide.

## 1) Product Context

This is a debate judging system with two user roles:
- Superadmin
- Judge

Frontend objective:
- Implement complete role-based workflows for operations and scoring.
- Respect backend status logic and submission lock rules.
- Keep UI decisions open (layout/style/components are frontend-owned choices).

## 2) Role-Based App Surface

Superadmin needs:
- Manage judges
- Manage rounds
- Manage rooms (sidang)
- Manage teams and members
- Create matches
- Assign judges (manual/random)
- Monitor match progress/status
- Force-complete and reopen
- View rankings (team/speaker) and results

Judge needs:
- Login
- View assigned matches
- Check in to match
- Fill manual-like score form
- Save draft
- Submit final (locked after submit)

## 3) Required Screens/Flows (No Design Prescription)

Superadmin routes/pages:
- Auth: login/logout/reset
- Judges index/create/edit/deactivate
- Rounds index/create/edit
- Rooms index/create/edit
- Teams index/create/edit + member management
- Matches index/detail/create/edit
- Judge assignment panel (manual/random)
- Match operations panel (force-complete/reopen)
- Rankings view (overall only): teams + speakers
- Reports view: per-match details and tournament summary

Judge routes/pages:
- Auth: login/logout/reset
- Assigned matches list
- Match detail + check-in action
- Score sheet form (draft and final submit)
- Submission receipt/history

## 4) Backend Contract Expectations

Assume backend exposes:
- Auth session/token endpoints
- Role-aware profile endpoint
- CRUD endpoints for admin resources
- Assignment/check-in/submit endpoints
- Read-only rankings endpoints for superadmin
- Match result endpoints including vote split, margin, best speaker

Frontend must not infer state blindly:
- Always render from backend `status`, `completion_type`, `result_state`.
- Disable or hide actions that backend disallows.
- Handle 4xx/409 transition errors gracefully.

## 5) Important Domain Rules Frontend Must Enforce in UX

- Panel size options only: `1`, `3`, `5`, `7`.
- Match has exactly 2 teams: government and opposition.
- Judge cannot score unless assigned.
- `in_progress` only when all assigned judges checked in (server source of truth).
- After final submit, sheet is locked unless admin reopens.
- Force-complete may create provisional result.

## 6) Score Sheet Form Requirements

Fields required per judge:
- Government marks: `PM`, `TPM`, `M1`
- Opposition marks: `KP`, `TKP`, `P1`
- Penggulungan marks: government + opposition
- Best debater selection from the six speakers

Computed preview (frontend convenience, backend authoritative):
- Gov total
- Opp total
- Winner side
- Margin

Submission UX requirements:
- Save draft without final lock
- Explicit final submit confirmation
- Read-only state after submit

## 7) Match Result Display Requirements

Show on match detail once calculated:
- Winner side
- Judge vote split (e.g., `2-1`)
- Margin (1 decimal place)
- Official team scores (1 decimal)
- Best speaker (vote-based, score tie-break)
- Result badge: `final` or `provisional`
- Completion badge: `normal` or `force-completed`

## 8) Ranking Views (Superadmin Only)

Team ranking order:
1. Win count
2. Judge count
3. Margin
4. Score

Speaker ranking:
- Based on backend-provided aggregate order/metrics.

Frontend requirement:
- Consume backend ranking output directly.
- Avoid duplicating ranking business logic in frontend.

## 9) Frontend State and Data Handling Guidance

- Use server-driven status transitions.
- Keep optimistic updates minimal for critical transitions (submit/force-complete/reopen).
- Refetch or invalidate match detail after check-in and submit.
- Implement explicit loading, empty, and error states for each role-critical page.
- Provide conflict handling for stale data (if two admins edit the same match).

## 10) Acceptance Checklist for Frontend

- Role guard works for every route.
- Judge sees only assigned matches.
- Superadmin-only pages are inaccessible to judges.
- Score sheet mirrors backend fields exactly.
- Submit lock behavior is correct.
- Match status and badges reflect backend truth.
- Rankings visible only to superadmin.
- 1-decimal formatting applied consistently to official aggregates.

## 11) Open Integration Items for Backend-Frontend Sync

Frontend implementer should request/confirm from backend team:
- Exact endpoint URLs and payload shapes
- Validation error schema format
- Auth strategy (cookie session vs token)
- Pagination/filter schema for list endpoints
- Realtime strategy (polling vs websocket) for live status dashboard

This guide intentionally avoids prescribing visual design.  
Gemini should decide information architecture, component system, and interaction details while preserving all backend business rules above.
