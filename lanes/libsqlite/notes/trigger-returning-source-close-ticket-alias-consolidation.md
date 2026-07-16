# Trigger RETURNING Source-Close Ticket Alias Consolidation

## Scope

- Consolidated the `executeCurrentSourceCursorClose()` handoff so direct callers can use stable source-ticket option names:
  - `current_source_ticket`
  - `current_view_source_ticket`
  - `current_trigger_source_ticket`
  - `auto_ack_current_source_tickets`
  - `acknowledged_current_source_tickets`
  - `require_current_source_ticket_order`
- Added stable reads for the base ticket handoff status/rows while preserving internal fallback to the already accepted ticket-handoff return shape.
- Migrated the direct focused test and Application example off the direct ticket-number option names.

## Verification

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceCloseTest.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-close.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceCloseTest.php`
  - `1 test files, 89 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-close.php --self-test`
  - `application-trigger-recursive-view-returning-current-source-source_close self-test passed`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This reuses the existing recursive view RETURNING ticket handoff and only adds stable alias plumbing for the source-close caller.
