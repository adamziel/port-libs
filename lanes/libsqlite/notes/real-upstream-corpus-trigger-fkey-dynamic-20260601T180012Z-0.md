# real-upstream-corpus-trigger-fkey-dynamic-20260601T180012Z-0

Base accepted HEAD: `eaf4be71f1e017e55035a4ef726a86e2aab9b7cc`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/laststmtchanges.test`
- Ported sections:
  - `laststmtchanges-2.1..2.4`: `changes()` enters an AFTER INSERT trigger with the caller frame value, trigger-local DML updates only the trigger frame, and the outer statement reports one changed row.
  - `laststmtchanges-3.1..3.3`: AFTER UPDATE trigger entry preserves the previous caller-frame `changes()` value, while trigger-local DELETE updates the trigger frame.
  - `laststmtchanges-4.1..4.3`: BEFORE DELETE trigger entry preserves caller-frame `changes()`, while trigger-local INSERT updates the trigger frame.
  - `laststmtchanges-5.1..5.5`: nested temp INSTEAD OF triggers restore inner trigger `changes()` state to the caller trigger frame and then restore the connection frame.
  - `laststmtchanges-6.2..6.6`: `DELETE FROM <table>` without triggers still reports the deleted row count, including indexed delete paths, and advances `total_changes()`.

## Behavior

This slice adds `SQLiteUpstreamTriggerFkeyDynamicPlan::lastStatementChangesTriggerFrames()` plus
`SQLiteRealUpstreamTriggerFkeyDynamicLastStatementChanges20260601Test.php`.

The model keeps the `laststmtchanges.test` behavior separate from accepted
`lastinsert.test` last-rowid frames: it tracks `changes()` values at trigger
entry, after trigger-local INSERT/UPDATE/DELETE statements, after nested view
trigger exits, after the outer statement, and across triggerless DELETE paths.

## Countability

- New focused TestRunner cases: `1258`.
- New focused assertions: `22028`.
- `lane-status.json` `phpPass`: `6158762 -> 6160020` (`+1258`).
- Mapped upstream denominator: unchanged at `1589 / 1589`; this is behavior growth over an already mapped upstream script.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicLastStatementChanges20260601Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicLastStatementChanges20260601Test.php`
  - `1 test files, 22028 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 7 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed

## Non-Overlap

This covers upstream `laststmtchanges.test` trigger-frame `changes()`
restoration, nested INSTEAD OF trigger changes frames, and triggerless DELETE
counter behavior. It does not repeat accepted `lastinsert.test` rowid frames,
`trigger2` count_changes, fkey action/deferred, triggerC indexed delete,
triggerupfrom, trigger7, trigger8, temptrigger, fkey5/fkey6/fkey7/fkey8, WAL,
VFS, B-tree, JSON, PRAGMA/schema, or source-neutral cleanup batches.

## Dependency Closure

No new support component is needed. This reuses the existing lane-local
upstream trigger/FK dynamic plan surface and the hydrated SQLite upstream
checkout as source truth.
