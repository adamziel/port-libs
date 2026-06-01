# real-upstream-corpus-date-affinity-dynamic-20260601T131447Z-0

Status: ready for integration from accepted base `a93e599b8ba28b765620aaefefa98a3cad05be92`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Ported scenarios: `date-14.1` and `date-14.2`
- Source-truth behavior: the upstream test writes raw bytes
  `4142ba32bffffff9` into a REAL cell, then mutates the final byte across
  `0x00..0xff`; `datetime(x)` must return either
  `2008-06-12 00:00:00` or `2008-06-11 23:59:59`, never `24:00:00`.

## Ported Behavior

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicHexioRealBoundary20260601Test.php`
to replay the complete 256-byte hexio boundary matrix through the native PHP
date/time scalar path. Each generated case unpacks the big-endian IEEE-754 REAL
payload, checks `datetime`, `strftime`, `date`, `time`, `unixepoch`,
`julianday`, and storage-class behavior, and asserts the upstream no-24-hour
boundary invariant.

The file also includes a generic application rollup using neutral `key_name`
labels to prove the same scalar behavior in a small ordered settings-like
projection without introducing domain-specific API names.

## Delta

- Focused PASS delta: `+259` TestRunner PASS cases.
- Focused behavior assertions: `6423`.
- `phpPass`: `5895014 -> 5895273`.
- Mapped denominator delta: `0`; this is real upstream behavior coverage within
  already mapped upstream inventory.

## Non-Overlap

This slice does not repeat accepted `date.test` parser/modifier/null/UTC
coverage, `date2.test` deterministic modifier coverage, `date3.test` auto and
unixepoch coverage, `date4.test` libc strftime loop ranges, `date5.test`
Gregorian-cycle coverage, or the earlier boundary modifier oracle matrix. It
owns only `date.test` section 14's hexio REAL boundary rows.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicHexioRealBoundary20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicHexioRealBoundary20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicHexioRealBoundary20260601Test.php`
  - `1 test files, 6423 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 6 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Dependency closure: no new support component is needed. The slice reuses the
existing native `SQLiteCoreScalarFunction` date/time scalar implementation and
the hydrated upstream SQLite test file as source truth.
