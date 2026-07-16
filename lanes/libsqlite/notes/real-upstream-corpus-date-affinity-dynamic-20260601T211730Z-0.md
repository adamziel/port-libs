# real-upstream-corpus-date-affinity-dynamic-20260601T211730Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip4801To6000Test.php` as an additive real upstream date/affinity corpus shard.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Scenario names: `atof1-1.4801.1/.2` through `atof1-1.6000.1/.2`.

Focused behavior:

- Replays the upstream Tcl `expr srand(1)` random REAL generator through ordinal `6000`, owning only ordinals `4801..6000`.
- Uses `sqlite3` as the oracle for `typeof(CAST(text AS REAL))`, `format('%.10e', CAST(text AS REAL))`, text REAL equality, and `quote()` REAL round-trip behavior.
- Exercises the PHP port through `SQLiteSelectSql`, `SQLiteCoreScalarFunction::quote()/format()`, and `SQLiteRealExpressionAffinityCorpusPlan::cast()` / storage-class helpers.
- Adds a generic `app_numeric_metrics` rollup to keep the application-shaped path source-neutral.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip4801To6000Test.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip4801To6000Test.php` -> `1 test files, 16826 assertions, 0 failures`.

PASS growth:

- Adds 1,203 focused TestRunner PASS cases.
- Expected `phpPass` movement: `6272894 -> 6274097`.
- Mapped denominator movement: none; `atof1.test` is already in the complete `1589 / 1589` manifest.

Non-overlap:

- Owns only `atof1.test` random REAL text-to-REAL and quote round-trip ordinals `4801..6000`.
- Extends accepted random REAL shards `1..4800`.
- Avoids accepted `atof1-2` UTF16/blob rows, `atof-3.1` integer-prefix suffixes, `atof-3.2` decimal suffixes, `atof-3.3` exponent rows, `atof2` rounding, date4 rows, timediff matrices, `affinity2`, `affinity3`, and types storage-class batches.

Dependency closure:

- No new support component is needed. This reuses the hydrated upstream corpus, existing local `tclsh` / `sqlite3` oracle tooling, and existing lane-local REAL casting, formatting, quote, and SELECT SQL dispatch helpers.
