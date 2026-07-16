# real-upstream-corpus-date-affinity-dynamic-20260601T201040Z-0

Status: ready focused PHP behavior growth for the date/affinity dynamic corpus.

## Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Upstream loop: `for {set i 1} {$i<20000} {incr i}`.
- Owned upstream range: `atof1-1.3601.1/.2` through `atof1-1.4800.1/.2`.
- Behavior: text-to-REAL conversion must match the Tcl-generated bound double value, and `CAST(quote(real) AS real)` must round-trip the exact IEEE-754 bits.

## Patch

- Added `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip3601To4800Test.php`.
- Adds 1,203 focused TestRunner PASS cases: 1 source-truth case, 1,200 upstream ordinal cases, 1 generic metric rollup, and 1 non-overlap/dependency case.
- Adds 16,826 behavior assertions using the hydrated upstream Tcl generator and sqlite3 oracle, then runs the native PHP `SQLiteSelectSql`, `SQLiteCoreScalarFunction`, and `SQLiteRealExpressionAffinityCorpusPlan` paths.

## Non-Overlap

This shard continues the accepted random REAL sequence after the existing `atof1-1.1..3600` coverage. It does not repeat `date4.test` strftime rows, `date.test`/`date2.test`/`date3.test`/`date5.test` modifier coverage, `timediff` matrices, `affinity2`, `affinity3`, `types2`, `types3`, `atof1-2` UTF-16/blob rows, `atof-3.1` integer-prefix suffixes, `atof-3.2` decimal suffixes, `atof-3.3` exponent rows, or `atof2` rounding/format rows.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip3601To4800Test.php`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip3601To4800Test.php`: `1 test files, 16826 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The batch reuses the hydrated upstream SQLite checkout, local `tclsh`/`sqlite3` oracle tooling, and existing native PHP SELECT, CAST, `quote()`, `format()`, equality, and REAL storage-class behavior.
