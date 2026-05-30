# real-upstream-corpus-upsert-returning-dynamic-20260530T175838Z-0

## Upstream Sources

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - Added non-overlapping trigger lifecycle coverage for `upsert2-300`,
    `upsert2-310`, `upsert2-320`, `upsert2-321`, `upsert2-400`,
    `upsert2-410`, `upsert2-420`, and `upsert2-421`.

## Behavior Ported

- `ON CONFLICT DO UPDATE` fires the attempted insert trigger record first, then
  before-update and after-update records, and returns only the updated row.
- `ON CONFLICT DO NOTHING` fires only the attempted insert trigger record and
  emits no RETURNING row.
- `ON CONFLICT DO UPDATE ... WHERE` with a false predicate fires only the
  attempted insert trigger record, leaves the row image unchanged, and emits no
  RETURNING row.
- Coverage is repeated across rowid and WITHOUT ROWID upstream layouts.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningDynamicCorpusPlan.php`
  passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicArmsCorpusTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicArmsCorpusTest.php`
  passed: `1 test files, 2442 assertions, 0 failures`, `483` selected PASS
  lines.
- New selected PASS-line delta in the focused file: `+433`.

## Non-Overlap

This extends the existing UPSERT dynamic arms file with `upsert2.test`
trigger-lifecycle behavior only. It does not repeat accepted `upsert5.test`
multi-arm ordering, `upsert4.test` conflict target analysis, RETURNING
projection checks, recursive trigger/view UPSERT, row-value RETURNING windows,
or the earlier `upsert2.test` SELECT-source repeated-conflict rows.

## Dependency Closure

No new support component is needed. The slice reuses
`SQLiteUpsertDoUpdateWherePlan::executeConflictArms()` and
`returningRows()` with generic row-array trigger-record modeling.
