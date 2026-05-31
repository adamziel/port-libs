# Real Upstream Corpus UPSERT RETURNING Dynamic 20260531T045020Z-0

Status: ready for clean integration as focused PASS-line growth.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - `upsert2-300` / `upsert2-400`: conflict `DO UPDATE` fires BEFORE INSERT, BEFORE UPDATE, then AFTER UPDATE.
  - `upsert2-310` / `upsert2-410`: `DO NOTHING` conflicts fire only BEFORE INSERT.
  - `upsert2-320` / `upsert2-420`: failed `DO UPDATE WHERE` conflicts fire only BEFORE INSERT.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - changed-row `RETURNING` streams include inserted and updated rows and omit skipped conflict rows.

## Change

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerStreamDynamicTest.php`.
- The file adds 1000 deterministic mixed `DO UPDATE` trigger-stream variants and 1000 deterministic `DO NOTHING` variants over generic application setting rows.
- Focused result: `1 test files / 16002 assertions / 0 failures / 2002 PASS lines`.
- Expected dashboard movement if accepted: `phpPass` `2125874 -> 2127876`; mapped coverage remains `1589 / 1589`.

## Non-overlap

This slice does not repeat accepted UPSERT3 composite returning, upsert4 target matching, upsert5 arm-priority matrices, excluded-alias SQL, target-first, broad yield, redundant-conflict, or select-source batches. It owns long mixed trigger-stream composition where one statement interleaves successful updates, inserts, failed `WHERE` conflicts, and `DO NOTHING` conflicts while preserving changed-row `RETURNING` cardinality.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerStreamDynamicTest.php`
  - `1 test files, 16002 assertions, 0 failures`

Dependency closure: no new support component is needed; this reuses the native UPSERT trigger-trace and RETURNING row-stream helpers.
