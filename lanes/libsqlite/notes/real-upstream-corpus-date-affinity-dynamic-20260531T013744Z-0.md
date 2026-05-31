# real-upstream-corpus-date-affinity-dynamic-20260531T013744Z-0

Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`.

This slice ports the next non-overlapping upstream `date4.test` strftime C-library parity range:

- upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- upstream scenario: `date4-$i` loop with `SELECT strftime($::FMT,$::TS,'unixepoch')`
- owned range: `date4-06300` through `date4-07299`
- focused TestRunner growth: `1003` PASS cases in `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows6300To7299Test.php`
- behavior assertions: each dynamic row checks integer and text unixepoch timestamp parity, TEXT storage class, comma field shape, and prefix/suffix agreement with the PHP UTC libc-equivalent oracle

Non-overlap: accepted coverage already owns `date4.test` rows `0..6299` plus separate `date.test`, `date2.test`, `date3.test`, and `date5.test` date-affinity clusters. This handoff extends only `date4.test` rows `6300..7299`.

Dependency closure: no new support component is needed; this reuses the existing `SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ...)` native PHP scalar path and the hydrated upstream SQLite test checkout as source truth.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows6300To7299Test.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows6300To7299Test.php` -> `1 test files, 6014 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` -> not run; that guard file does not exist in this worktree
- `git diff --check -- lanes/libsqlite` -> passed

Root harness: not run - isolated micro-slice.
