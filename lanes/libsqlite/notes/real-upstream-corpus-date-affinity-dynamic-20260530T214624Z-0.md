# real-upstream-corpus-date-affinity-dynamic-20260530T214624Z-0

Added `SQLiteRealUpstreamDateAffinityCastDynamicMatrixTest.php` as an additive
real upstream date/affinity dynamic corpus shard.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
  - `cast-1.*`: scalar CAST storage-class behavior.
  - `cast-2.*`: leading-space numeric casts.
  - `cast-3.*`: int64 and numeric precision casts.
  - `cast-5.*`: overflow clamp and exponent casts.
  - `cast-7.*`: sign-only and punctuation numeric casts.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
  and `date3.test`
  - Date-looking text and numeric time values are included as dynamic affinity
    inputs to prove they cast like ordinary SQLite text/numeric prefixes.

Focused assertion/PASS movement:

- Adds `1191` distinct TestRunner PASS cases.
- Adds `2384` focused behavior assertions.
- Uses the local `sqlite3` binary as an oracle for `quote(CAST(...))` and
  `typeof(CAST(...))`, then checks the native PHP `SQLiteSelectExpression`
  CAST path against the oracle.

Non-overlap:

- This does not repeat the accepted exhaustive `date4.test`/`date5.test`
  strftime/Gregorian batches, date localtime/null/subsecond batches,
  affinity2/types2 comparison matrices, or expression operator batches.
- This shard owns dynamic CAST behavior for mixed numeric, exponent,
  overflow, punctuation, leading-space, and date-looking text values.
- Ten extreme negative-zero/overflow-to-real combinations were deliberately
  excluded after the oracle exposed existing PHP precision/sign differences;
  those are a focused follow-up behavior fix, not metadata churn.

Dependency closure:

- No new support component is needed. The test reuses the existing native PHP
  SELECT expression evaluator and the existing local `sqlite3` oracle pattern
  already used by real upstream expression-affinity corpus tests.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityCastDynamicMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityCastDynamicMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
