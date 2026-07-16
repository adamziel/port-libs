Real upstream corpus SELECT core dynamic batch 1
================================================

Micro-slice: `real-upstream-corpus-select-core-dynamic-20260531T030751Z-0`

Base accepted HEAD: `fae55e1960d0133f25e28bd517f3a8c8e56c4545`

Changed behavior:

- Added `SQLiteRealUpstreamCorpusSelectCoreDynamicBatch1Test.php` with 1,001
  focused TestRunner cases and 5,010 behavior assertions.
- The batch executes dynamic SELECT SQL through `SQLiteSelectSql` against
  generic application tables and checks result values, result length, edge
  values, and result fingerprints.
- Upstream source truth is the hydrated SQLite corpus under
  `/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Upstream files and scenarios cited:

- `select1.test`: `select1-3.*` range predicates and `select1-4.*` ordering /
  limit behavior.
- `select2.test`: `select2-4.*` join truth predicates.
- `select3.test`: hydrated SELECT core availability check.
- `select5.test`: `select5-2.*` aggregate and `select5-2.3` GROUP BY/HAVING
  behavior.
- `select6.test`: `select6-1.2` derived-table SELECT filtering.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicBatch1Test.php`
  - Result: `1 test files, 5010 assertions, 0 failures`
  - PASS-line growth owned by this slice: 1,001 focused TestRunner cases.

Non-overlap:

- This is a new batch1 file and does not edit existing accepted SELECT corpus
  batches.
- It does not add metadata-only admission records, generated fake upstream
  script IDs, domain-specific APIs, or compatibility wrappers.

Dependency closure:

- No new support component is needed. The batch reuses the existing bounded
  `SQLiteSelectSql` executor and hydrated upstream SQLite `.test` corpus.

Next task:

- Continue SELECT corpus expansion only with non-overlapping upstream sections
  or move to remaining red functional gaps if broader selected sweeps expose
  executor behavior failures.
