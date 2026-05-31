# real-upstream-corpus-date-affinity-dynamic-20260531T060853Z-0

Status: blocked for a ready implementation handoff on accepted base
`cd24ba2f7b741bb89ced6cb6c27264084794565b`.

This slice was assigned to find a fresh real upstream SQLite date/affinity
cluster. I checked the hydrated upstream sources under
`/home/claude/port-libs/.upstream-cache/libsqlite/test` and the current
lane-local PHP corpus under `lanes/libsqlite/tests`.

Upstream source checked:

- `date.test`
- `date2.test`
- `date3.test`
- `date4.test`
- `date5.test`
- `affinity2.test`
- `affinity3.test`
- `types2.test`
- `types3.test`

Overlap / blocker:

- The high-yield `date4.test` `date4-$i` strftime loop is already represented
  through the full upstream range by existing focused date4 row shards,
  including the broad `20400..24858` tail shard.
- The prior blocker note for this family named `date.test` deterministic
  `localtime`, localtime failure, and statement-stable `now` as the next
  meaningful unblocker. Those are now implemented in
  `SQLiteCoreScalarFunction` and covered by existing focused tests:
  `SQLiteRealUpstreamDateLocaltimeDynamicCorpusTest.php`,
  `SQLiteRealUpstreamDateLocaltimeFailureDynamicCorpusTest.php`, and
  `SQLiteRealUpstreamDateStatementNowDynamicCorpusTest.php`.
- Current focused verification for those existing unblocker tests passed with
  `3 test files, 16060 assertions, 0 failures`, so adding another implementation
  file for the same upstream sections would be duplicate PASS inflation.
- Remaining obvious date/affinity sections are already represented by accepted
  timezone, UTC suffix, component validation, date2/date3 modifier placement,
  date5 Gregorian-cycle, floor/ceiling, boundary, affinity2/affinity3, and
  types2/types3 matrix tests, or are below the hard real-corpus floor unless
  they are part of a broader non-overlapping behavior fix.

Verification performed:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateLocaltimeDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateLocaltimeFailureDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateStatementNowDynamicCorpusTest.php`
  -> `3 test files, 16060 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` should be run after this note is
  written.

Dependency closure:

- No new support component is needed. The previously identified missing
  lane-local PHP date/time dispatcher state is already present: deterministic
  localtime rules, unavailable-localtime fault handling, and per-step stable
  `now` snapshots.

Next larger batch to try:

- Pivot out of this saturated date-affinity family unless a broad diagnostic
  sweep identifies a real date/affinity regression. The best adjacent
  non-overlapping candidates are known-red expression-affinity cast/comparison
  behavior or a root/full-runner blocker with accepted-base evidence.
