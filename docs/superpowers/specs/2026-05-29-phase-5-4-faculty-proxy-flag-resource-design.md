# Design: Phase 5.4 — Faculty ProxyFlagResource

## Overview

A read-only Filament resource in the Faculty panel that shows proxy flags raised on the
faculty member's own sessions. Allow/Deny review actions are conditionally available based
on a system setting controlled by the Super Admin.

## Scope

- Faculty can only see flags generated from sessions they own
- Optional Allow/Deny review — gated on `SystemSetting::get('faculty_can_review_flags') === 'true'`
- `ViewEvidenceAction` always available (read-only modal)
- No bulk actions, no create/edit form

## Files

| File | Purpose |
|---|---|
| `app/Filament/Faculty/Resources/ProxyFlags/ProxyFlagResource.php` | Resource definition, scoped query |
| `app/Filament/Faculty/Resources/ProxyFlags/Pages/ListProxyFlags.php` | List page |
| `app/Filament/Faculty/Resources/ProxyFlags/Tables/ProxyFlagTable.php` | Table columns and actions |
| `tests/Feature/Faculty/ProxyFlagResourceTest.php` | 4 acceptance tests |

## Data Scoping

```php
getEloquentQuery(): Builder
```

Filters to flags where `attendanceRecord.session.faculty_id = auth()->user()->faculty->id`.
Uses `whereHas` to traverse the relationship chain. Eager-loads
`attendanceRecord.student.user` and `attendanceRecord.session.course`.

## Table Columns

| Column | Notes |
|---|---|
| `attendanceRecord.student.user.name` | Student name, searchable |
| `attendanceRecord.session.course.code` | Course code |
| `severity` | Badge |
| `reason_code` | Plain text |
| `risk_score` | Badge, colour: red ≥ 80, amber ≥ 50, green otherwise |
| `review_status` | Badge |

## Actions

### ViewEvidenceAction (always visible)
Modal displaying GPS coordinates, device info, and risk breakdown from `evidence_json`.
Read-only (`modalSubmitAction(false)`).

### AllowAction (conditional)
- Visible when: `SystemSetting::get('faculty_can_review_flags') === 'true'` AND `review_status === Pending`
- Sets `review_status = ReviewStatus::Approved`, records `reviewer_id`, `reviewed_at`
- Optional `reviewer_notes` textarea
- Writes `AuditLog::record('proxy_flag.allowed', ...)`

### DenyAction (conditional)
- Visible when: `SystemSetting::get('faculty_can_review_flags') === 'true'` AND `review_status === Pending`
- Sets `review_status = ReviewStatus::Rejected`, records `reviewer_id`, `reviewed_at`
- Required `reviewer_notes` textarea
- Writes `AuditLog::record('proxy_flag.denied', ...)`

## Navigation

- Icon: `Heroicon::OutlinedShieldExclamation`
- Group: `My Sessions`

## Tests

| Test | Assertion |
|---|---|
| `test_faculty_can_list_flags_for_own_sessions` | Own flags visible |
| `test_faculty_cannot_see_flags_from_other_sessions` | Other faculty's flags hidden |
| `test_allow_deny_actions_visible_when_policy_permits` | Actions appear when setting is `'true'` |
| `test_allow_deny_actions_hidden_when_policy_disallows` | Actions hidden when setting is `'false'` |
