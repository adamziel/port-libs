# Real Upstream Corpus: UPSERT RETURNING Excluded Alias

Micro-slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T224536Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  sections `7.1`-`7.4` and `8.1`-`8.5`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  section `4.5` for multi-row `INSERT ... ON CONFLICT DO UPDATE RETURNING`
  stream parity.

Patch:

- Added `SQLiteRealUpstreamCorpusUpsertReturningDynamicExcludedAliasTest.php`.
- Covers composite conflict targets, `WITHOUT ROWID` variants, the `excluded`
  pseudo-table, insert aliases, a target table literally named `excluded`,
  quoted composite columns, `WHERE excluded.*` update gating, NULL non-conflict
  behavior, and stable RETURNING projections.
- Uses PDO SQLite as a local oracle for row-image parity where possible and
  asserts the native `SQLiteUpsertDoUpdateWherePlan` partitions, matched arms,
  change counts, and RETURNING rows.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicExcludedAliasTest.php`
  => `1 test files, 1246 assertions, 0 failures`.

Dependency closure:

- No new support component needed. This reuses the existing native
  `SQLiteUpsertDoUpdateWherePlan` helper and PDO only as a focused local oracle
  in the test.

Non-overlap:

- Does not repeat accepted `upsert5` conflict-arm priority matrices,
  `upsert2` WHERE-gated batches, broad `returning1` stream batches, redundant
  conflict target tests, or scope/target-analysis tests already present under
  `SQLiteRealUpstreamUpsertReturning*`.
