# ATTACH Temp/WAL Schema Cookie Current Source next99

## Behavior

This slice extends the accepted ATTACH temp/WAL schema-cookie current-source
planner with `WITH` / `WITH RECURSIVE` source filtering. Prepared statements
that read from CTE names no longer treat those unqualified CTEs as attached
schema tables when deciding whether a schema-cookie change expires the
statement. Qualified real tables such as `main.recent_options` remain tracked,
and CTE-backed `INSERT` / `DELETE` statements still route to write retry
blocking when their real source or target schema cookie changes.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCookieCurrentSourceNext99Test.php`
  - `1 test files, 51 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-attach-temp-wal-schema-cookie-current-source-next99.php --self-test`
  - Application copied import smoke passed.

## Dashboard Delta

- `phpPass`: `38278 -> 38329` (`+51` focused assertions).
- `benchmarkDenominator.mapped`: `568 -> 569` for one focused ATTACH
  temp/WAL schema-cookie row covering CTE source filtering.

## Non-Overlap

Avoids accepted batch94 ATTACH temp/WAL schema-cookie bracket quoting and
schema-table alias routing. This patch covers only CTE source-name filtering
and real table preservation for current/next schema-cookie invalidation.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
native PHP ATTACH/WAL/temp schema-cookie planner and test harness.
