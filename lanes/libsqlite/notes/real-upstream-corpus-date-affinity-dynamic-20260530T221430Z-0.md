# real-upstream-corpus-date-affinity-dynamic-20260530T221430Z-0

Added `SQLiteRealUpstreamDateAffinityDynamicFollowupCorpusTest.php` as a
non-overlapping follow-up to the existing date/affinity dynamic corpus.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test`

Coverage shape:

- 500 distinct `date4.test` strftime matrix cases over the upstream
  `i * 86390` timestamp walk.
- 300 distinct `date5.test` Gregorian/Julian 400-year cycle cases.
- 200 distinct manifest-type affinity cast cases from `types.test`,
  `types2.test`, and `types3.test`.
- 1 source-citation guard case.

Focused assertion/PASS result: 1001 distinct TestRunner cases, each checking
real upstream behavior through the local libsqlite port against a sqlite3
oracle. This is PASS-line growth only; mapped denominator coverage is already
complete at 1589 / 1589.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicFollowupCorpusTest.php`
  passed with `1 test files, 3607 assertions, 0 failures`.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicFollowupCorpusTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component is needed. The existing
`SQLiteCoreScalarFunction`, `SQLiteSelectExpression`, and local `sqlite3`
oracle path are reused for bounded upstream parity evidence.
