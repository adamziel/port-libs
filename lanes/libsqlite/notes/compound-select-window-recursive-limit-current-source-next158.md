# Compound SELECT Window Recursive LIMIT Current Source Next158

## Scope

Adds a focused current-source next158 behavior slice for parser-level compound
SELECT execution where:

- a `WITH RECURSIVE` queue uses `LIMIT ... OFFSET` and skips the anchor row;
- recursive rows feed one compound arm with a window aggregate;
- `wp_options` rows feed a second windowed arm;
- final compound `ORDER BY ... LIMIT ... OFFSET` controls the displayed
  current/next boundary.

This is deliberately separate from accepted next139 recursive final-limit
coverage, next142 recursive affinity/dedup coverage, and next148 chained EXCEPT
window/limit coverage.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext158Test.php`
- Result: `1 test files, 208 assertions, 0 failures`
- PASS lines added: `65`
- Application smoke: `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next158.php`

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`SQLiteSelectSql` recursive CTE, window, compound SELECT, and LIMIT/OFFSET
executor paths; no ext/sqlite, shell-out, or upstream binary is required.

## Next

Keep compound SELECT follow-up work away from accepted recursive affinity,
EXCEPT, and final-limit variants unless it adds a distinct executor behavior
or removes a named upstream runner blocker.
