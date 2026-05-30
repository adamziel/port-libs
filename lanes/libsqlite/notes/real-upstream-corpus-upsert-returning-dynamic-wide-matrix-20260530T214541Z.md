# real-upstream-corpus-upsert-returning-dynamic-wide-matrix-20260530T214541Z

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T214541Z-0`
Base accepted HEAD: `551608c47b9b5c9b4c74afdd6349b99f03720fcd`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - `upsert5.test` `1.*`: `ON CONFLICT` arm priority, target ordering, catch-all arms, and `DO NOTHING` suppression.
  - `upsert5.test` `3.*`: multi-row insert/upsert conflict stream behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1.test` `17.*`: `INSERT ... ON CONFLICT DO UPDATE RETURNING` duplicate-row rowid stream behavior.

## Added Coverage

Added `lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicWideMatrixTest.php`.

The file owns a non-overlapping wide matrix:

- 24 conflict-arm target orders from `upsert5.test`.
- 8 incoming conflict shapes covering all-conflict, subset-conflict, and no-conflict insert cases.
- 6 focused checks for each order/shape pair: selected target, rowset partitioning, `RETURNING` yield count, selected-arm row image, projection order, and mixed `DO NOTHING` suppression.
- 8 duplicate-row `RETURNING` sequences from `returning1.test` section 17 behavior, with checks for rowid stream, change count, final unique rows, duplicate refcounts, projection narrowing, and insert/update partitioning.

Focused result:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicWideMatrixTest.php
1 test files, 2369 assertions, 0 failures
1201 PASS lines
```

Expected dashboard movement if accepted: `phpPass` `782971 -> 784172` (`+1201`). Mapped denominator coverage remains `1589 / 1589`; this is PASS-line growth only, not new mapped denominator movement and not a full release/all runner parity claim.

## Non-Overlap

This does not edit production source and does not repeat the existing `SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php` or priority-matrix file verbatim. The new matrix broadens the real upstream `upsert5.test`/`returning1.test` combination space with all 24 target-order permutations, mixed `DO NOTHING` arm selection, no-conflict insert rows, and duplicate-row `RETURNING` streams in a separate focused file.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `SQLiteUpsertDoUpdateWherePlan` helper and the existing TestRunner.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicWideMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicWideMatrixTest.php`
- Pending before final handoff: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- Pending before final handoff: `git diff --check -- lanes/libsqlite`
