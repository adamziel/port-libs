# Real Upstream Corpus Date/Affinity Dynamic 20260531T113329Z

Slice: `real-upstream-corpus-date-affinity-dynamic-20260531T113329Z-0`

Source truth:

- Hydrated SQLite upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Upstream scenario: `atof1.test` `atof-3.2`
- Owned range: decimal suffixes `0000..0999` from `format('18.44674407370955%04d', i)`

Behavior added:

- `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalReal20260531T113329ZTest.php` ports 1000 distinct upstream decimal REAL conversion rows.
- Each row verifies native `SQLiteSelectSql` `CAST(vtxt AS REAL)` over TEXT affinity projects REAL storage and satisfies the upstream guard `CAST(vtxt AS REAL) GLOB '18.446744073709*'`.
- The same rows are checked against a local `sqlite3` oracle for storage class and GLOB result, and against `SQLiteRealExpressionAffinityCorpusPlan` / scalar `glob()` helper dispatch for executor/helper parity.
- A generic `app_decimal_metrics` rollup keeps the smoke path source-neutral.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalReal20260531T113329ZTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalReal20260531T113329ZTest.php`
  - `1 test files, 11017 assertions, 0 failures`
  - PASS cases: 1003

Non-overlap:

- Avoids accepted `atof1.test` `atof-3.1` suffixes `0592..1609` from `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RealConversion20260531T102903ZTest.php`.
- Avoids accepted `atof2.test` alternate-form REAL formatting and existing `date*`, `types3`, and `affinity3` date-affinity dynamic corpus files.
- Leaves remaining `atof-3.2` suffixes `1000..9999` and `atof-3.3` exponent rows for future, non-overlapping batches.

Dependency closure:

- No new support component needed. The slice reuses `SQLiteSelectSql`, `SQLiteCoreScalarFunction`, `SQLiteRealExpressionAffinityCorpusPlan`, hydrated upstream source text, and a local `sqlite3` oracle.
