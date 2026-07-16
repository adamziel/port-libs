# Real upstream corpus UPSERT RETURNING dynamic catch-all

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T054021Z-0`

Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`

Changed behavior:

- Added `SQLiteRealUpstreamCorpusUpsertReturningDynamicCatchallTest.php`.
- Source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
    `1.400` through `1.409` catch-all `ON CONFLICT DO UPDATE` arm selection.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
    `1.500` through `1.509` catch-all `ON CONFLICT DO NOTHING` suppression.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
    `4.2` and `4.5` UPSERT `RETURNING` changed-row stream behavior.
- Non-overlap: existing accepted dynamic UPSERT files cover named-arm priority,
  composite conflict targets, omitted-target `DO NOTHING`, SELECT input, and
  wide named-arm permutations. This batch focuses on catch-all fallback arms
  and selected `DO NOTHING` suppression of `RETURNING` rows.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicCatchallTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicCatchallTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicCatchallTest.php`
  - `1 test files, 25602 assertions, 0 failures`
  - `5002` focused PASS cases.

Dependency closure:

- No new support component needed; the slice reuses native
  `SQLiteUpsertDoUpdateWherePlan` conflict-arm execution, catch-all matching,
  and `RETURNING` projection.

Root harness:

- Not run; isolated micro-slice.
