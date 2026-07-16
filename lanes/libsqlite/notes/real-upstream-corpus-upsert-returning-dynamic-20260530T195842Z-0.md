# real-upstream-corpus-upsert-returning-dynamic-20260530T195842Z-0

Implemented a real upstream UPSERT/RETURNING corpus expansion from the
hydrated SQLite upstream checkout:

- Source truth:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
- Covered upstream scenarios:
  `upsert5-1.1.100` through `upsert5-1.6.505`, with emphasis on the
  catch-all `ON CONFLICT DO UPDATE` and `ON CONFLICT DO NOTHING` matrix
  (`400` through `505`) over all six rowid/int-primary-key/WITHOUT ROWID
  schema variants.
- PHP coverage added:
  `SQLiteRealUpstreamUpsertReturningDynamicCatchAllMatrixTest.php` exercises
  native `SQLiteUpsertDoUpdateWherePlan::executeConflictArms()` through
  final row images, RETURNING rows, matched conflict arm metadata, change
  counts, skipped rows, insert/update partitions, and schema variant metadata.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCatchAllMatrixTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 3193 assertions, 0 failures
```

PASS-line growth:

- New focused TestRunner PASS cases: `1369`
- Behavior assertions: `3193`
- Countability: PASS-line growth only; no mapped denominator movement.

Non-overlap:

- This does not add metadata-only admission rows or fake upstream script IDs.
- Existing accepted UPSERT/RETURNING dynamic coverage already covers
  `upsert2`, `upsert4`, `upsert5` priority basics, returning projections, and
  correlated delete sections. This slice focuses the unused
  `upsert5CatchAllPriorityCases()` builder and its six-schema catch-all /
  DO NOTHING matrix.

Dependency closure:

- No new support component is needed. The slice reuses the existing native
  UPSERT conflict-arm executor and RETURNING projection helper.
