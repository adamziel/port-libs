# real-upstream-corpus-date-affinity-dynamic-20260531T043913Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows15300To16299Test.php` as an additive real upstream date/affinity corpus batch.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario: `date4-$i` loop over `SELECT strftime($::FMT,$::TS,'unixepoch')`.
- Owned non-overlapping range: `date4-15300` through `date4-16299`.

Focused behavior:

- 1,000 distinct upstream `strftime()` parity rows using the upstream timestamp formula `TS = i * 86390`.
- The format string includes `%d`, `%e`, `%F`, `%H`, `%k`, `%I`, `%l`, `%j`, `%m`, `%M`, `%u`, `%w`, `%W`, `%Y`, `%%`, `%P`, `%p`, `%U`, `%V`, `%G`, and `%g`.
- Each row asserts exact output, text storage class, comma-field count, string/numeric unixepoch argument parity, and prefix date preservation.
- Adds one generic application audit rollup over four representative retained event timestamps.

Non-overlap:

- This continues the real upstream `date4.test` corpus after the accepted date4 rows through `15299`.
- It does not repeat timezone-offset handling, localtime-chain behavior, date2 deterministic schema guards, date3 unixepoch/auto behavior, date5 calendar roundtrips, date modifier batches, expression-affinity casts, or source-neutral cleanup.

Dependency closure:

- No new support component is needed. The slice reuses the native date/time scalar implementation and existing PHP `DateTimeImmutable` oracle construction used by adjacent date4 corpus tests.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows15300To16299Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows15300To16299Test.php`
- `git diff --check -- lanes/libsqlite`
