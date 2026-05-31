# Real Upstream Corpus Trigger/FK Dynamic Batch

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T000735Z-0`
Base accepted HEAD: `88eb6ac3e2ad25d5a4756e5a167672b605fd3e97`
Worker timestamp: `2026-05-31T00:10:15Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test`
  - `fkey6-3.2.*` and `fkey6-3.3.*`: `PRAGMA defer_foreign_keys` delays `RESTRICT` to the transaction boundary and permits an AFTER DELETE trigger to repair the missing parent before commit.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test`
  - `fkey8-1.*`: foreign-key action statement-journal classification for `SET NULL`, `SET DEFAULT`, attached schemas, and conflict boundaries.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  - `trigger2-4.1` and `trigger2-4.2`: cascaded trigger programs and recursive trigger behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test`
  - `triggerG-100` and `triggerG-200`: recursive trigger subprogram SELECT behavior and OP_Once reset per trigger invocation.

## Local Movement

- Added `SQLiteRealUpstreamTriggerFkeyDynamicRestrictActionCorpusTest.php`.
- Focused assertion count: `4959`.
- Distinct TestRunner PASS rows: `4953`.
- Expected `phpPass` movement: `1292330 -> 1297283`.
- Mapped denominator movement: none; mapped inventory was already complete at `1589 / 1589`.

## Dependency Closure

No new support component is needed. The batch reuses the existing native
`SQLiteDynamicTriggerForeignKeyPlan` behavior surface and hydrates it with
real upstream trigger/FK scenarios. Release/all-runner parity remains a broad
libsqlite exit gate, but this slice does not add a new dependency blocker.

## Non-Overlap

This batch avoids accepted fkey2 deferred graph, trigger/FK recursive-once,
fkey5 foreign-key-check collation, fkey2 nocase repair, trigger/FK savepoint
deferred, trigger4/trigger5, rollback/WAL, JSON, B-tree, pragma, and SELECT
clusters. It focuses on fkey6 deferred `RESTRICT` repair, fkey8 action journal
classification, trigger2 cascaded programs, and triggerG recursive SELECT
subprogram reset behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRestrictActionCorpusTest.php`
  - `1 test files, 4959 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkey*.php`
  - `51 test files, 399128 assertions, 0 failures`
