# encoding-collation-numeric-affinity-current-source-next107

## Behavior

Adds a bounded current/next planner for Application `wp_options` numeric-affinity comparisons. The slice covers numeric text coercion, integer/text/real storage swaps, UTF-16LE/UTF-16BE byte changes, source/schema-cookie invalidation, and text fallback comparison under `NOCASE`/`RTRIM`.

This intentionally avoids the accepted UTF-16 malformed record guard, Unicode GLOB ranges, LIKE/GLOB source switching, and batch104/105 affinity LIKE/GLOB range surfaces.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingNumericAffinityCurrentSourceNext107Test.php`
- Result: `1 test files, 58 assertions, 0 failures` with 58 PASS lines.
- Expected dashboard movement: `phpPass` +58, from 41873 to 41931.

## Application Smoke

- `php lanes/libsqlite/examples/application-encoding-numeric-affinity-current-source-next107.php`
- Reports numeric-affinity cursor invalidation for copied `wp_options` values across current/next schema sources without requiring ext/sqlite.

## Dependency Closure

No new support component is needed. The planner reuses existing storage classification and UTF-16 byte encoding primitives already present in the libsqlite lane.
