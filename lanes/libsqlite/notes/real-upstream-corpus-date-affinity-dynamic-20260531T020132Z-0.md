# real-upstream-corpus-date-affinity-dynamic-20260531T020132Z-0

Base accepted HEAD: `e1f1e0a66bff0730bf5e4118bd715c8a11c33354`.

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Owned section: `date4.test` loop rows `date4-07300` through `date4-08299`.
- Non-overlap: this follows the accepted date4 row files through `0..7299` and does not repeat existing `date.test`, `date2.test`, `date3.test`, `date5.test`, or expression-affinity corpus files.

## Behavior

The new PHP corpus file checks `strftime($FMT, $timestamp, 'unixepoch')` for the real upstream `date4.test` libc-format matrix over 1,000 distinct timestamps. Each row also checks text-affinity timestamp input, returned storage type, delimiter shape, and stable leading/trailing formatted fragments. A small generic retention rollup keeps the application path source-neutral.

Focused count:

- `1003` distinct TestRunner PASS cases.
- `6014` focused assertions.
- Mapped denominator remains `1589 / 1589`; this is countable PHP PASS-line growth over already mapped real upstream inventory.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows7300To8299Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows7300To8299Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP date/time scalar implementation and the hydrated upstream SQLite test cache as source truth.

## Next

Continue `date4.test` with rows `8300..9299` if more date-affinity throughput is desired, or pivot to another non-overlapping real upstream corpus family.
