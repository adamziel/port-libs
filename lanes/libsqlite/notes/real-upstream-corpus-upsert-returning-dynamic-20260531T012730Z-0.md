# real-upstream-corpus-upsert-returning-dynamic-20260531T012730Z-0

Base accepted HEAD: `a890092c734c05eb72a795bdc37321c497f93beb`.

Added `SQLiteRealUpstreamCorpusUpsertReturningDynamicCompositeTailTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
  - `upsert3-130`
  - `upsert3-140`
  - `upsert3-200`
  - `upsert3-210`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - `upsert4-1.1` through `upsert4-1.8`
  - `upsert4-6.1` through `upsert4-6.2`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - changed-row RETURNING stream parity

Behavior covered:

- composite `ON CONFLICT(a,b)` and reversed `ON CONFLICT(b,a)` target matching;
- repeated source rows reading the current post-update row image;
- `DO UPDATE ... WHERE` skipped rows producing no RETURNING event at their source ordinal;
- changed-row RETURNING stream, star projection, derived alias projection, and changes-count parity against an in-memory SQLite oracle;
- final composite-key uniqueness after mixed inserts, updates, and skips.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicCompositeTailTest.php`
  - `1 test files, 1338 assertions, 0 failures`
  - `1008` distinct TestRunner PASS cases

Non-overlap:

This slice does not repeat the accepted UPSERT5 RETURNING yield batch. It owns the composite target-order and repeated-source tail from `upsert3.test`, plus the `upsert4.test` target/replacement-precedence tail, and keeps the checks generic application-table only.

Dependency closure:

No new support component is needed. The slice reuses existing `SQLiteUpsertDoUpdateWherePlan` row-array execution and the local PDO SQLite oracle already used by neighboring libsqlite corpus tests.
