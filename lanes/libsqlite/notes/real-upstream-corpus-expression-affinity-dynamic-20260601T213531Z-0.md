# real-upstream-corpus-expression-affinity-dynamic-20260601T213531Z-0

Base accepted HEAD: `cce109a8b51c73bd7d4f8e583cfcddf019c282e5`

## Scope

Added one real upstream SQLite corpus slice for `test/atof1.test`:

- `atof1.test` loop `atof1-1.$i.1/.2`
- owned ordinals: `6001..7200`
- source-truth checks: seeded Tcl generator, text-to-REAL equality, and `quote()` REAL round-trip preservation
- focused TestRunner PASS delta: `+1203`
- behavior assertions: `16826`

The PHP test replays the upstream Tcl seeded random sequence through `tclsh`, then cross-checks storage class, formatted REAL output, text-to-REAL equality, and `quote(CAST(... AS REAL))` round-trips against a local `sqlite3` oracle. It exercises the native PHP port through `SQLiteSelectSql`, `SQLiteCoreScalarFunction`, and `SQLiteRealExpressionAffinityCorpusPlan`.

## Non-Overlap

This slice extends the accepted `atof1.test` random REAL shards from `1..6000` to `6001..7200`.

It avoids accepted `atof1-2` UTF16/blob rows, `atof-3.1` integer-prefix suffixes, `atof-3.2` decimal suffixes, `atof-3.3` exponent rows, `atof2` rounding, `date4` rows, timediff matrices, affinity2/affinity3, and storage-class matrix batches.

## Verification

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicAtof1RandomRoundtrip6001To7200Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicAtof1RandomRoundtrip6001To7200Test.php
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicAtof1RandomRoundtrip6001To7200Test.php
1 test files, 16826 assertions, 0 failures
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the hydrated upstream SQLite checkout, local `tclsh` and `sqlite3` oracle tooling, `SQLiteSelectSql` CAST/equality/function dispatch, `SQLiteCoreScalarFunction` `quote()`/`format()`, and `SQLiteRealExpressionAffinityCorpusPlan` REAL casting.

## Next

Continue the remaining `atof1.test` random REAL ordinals beyond `7200`, or pivot to one of the current broad failure families if the supervisor prioritizes release parity over additional random REAL corpus growth.
