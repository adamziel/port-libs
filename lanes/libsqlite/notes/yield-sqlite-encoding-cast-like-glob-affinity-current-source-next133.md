# SQLite Encoding CAST LIKE/GLOB Affinity Current Source Next133

- Session: `port-dev-sqlite-yield-encoding133`
- Base accepted HEAD: `c61a1af21b0674696dd37fe902f4f26e781b49e2`
- Scope: BINARY `CAST(option_value AS ...)` feeding LIKE/GLOB prefix-candidate planning and residual matching across current/next copied `wp_options` sources.
- Non-overlap: avoids accepted NOCASE CAST LIKE next129, RTRIM GLOB next127, RTRIM LIKE next131, UTF-16 malformed guard, Unicode GLOB range, and SELECT predicate affinity/collation clusters.

## Behavior

Added `SQLiteCastLikeGlobAffinityCurrentSourceNextPlan` for copied WordPress option-value scans that need SQLite-style text affinity after `CAST(...)` before evaluating LIKE/GLOB. The plan records prefix range usability, candidate rowids, residual rejections, cast storage/text/byte changes, rowset changes, and cursor invalidation reasons.

The slice covers:

- BINARY LIKE prefix ranges with escaped literal wildcards.
- BINARY GLOB prefix ranges and Unicode character-class residuals.
- CAST targets `TEXT`, `INTEGER`, `REAL`, and `NUMERIC`.
- BLOB, NULL, boolean, integer, real, Unicode, and emoji option values.
- Current-source/next-source invalidation from source names, schema cookies, cast results, text affinity, encoded bytes, candidate rowsets, and matched rowsets.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCastLikeGlobAffinityCurrentSourceNext133Test.php`
  - `1 test files, 84 assertions, 0 failures`
  - `84` PASS lines.

## WordPress Smoke

- `php lanes/libsqlite/examples/wordpress-cast-like-glob-affinity-current-source-next133.php --self-test`
  - `wordpress-cast-like-glob-affinity-current-source-next133 self-test passed`

## Dependency Closure

No new support component is needed. The slice reuses existing bounded native PHP components: `SQLiteSelectSql` CAST expression execution, `SQLiteDatabase` LIKE/GLOB prefix/residual helpers, `SQLiteBlobValue`, and lane-local test harness support.

## Expected Dashboard Movement

- `phpPass`: `+84` focused PASS lines if integrated on this accepted base.
- `benchmarkDenominator.mapped`: unchanged; this is PHP behavior coverage over already mapped encoding/LIKE/GLOB/CAST evidence, not a fresh manifest-backed upstream row.
