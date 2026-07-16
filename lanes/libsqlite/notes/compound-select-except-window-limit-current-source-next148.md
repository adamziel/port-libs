# compound-select-except-window-limit-current-source-next148

## Scope

Adds a current-source compound SELECT diagnostic for chained `EXCEPT` arms whose left arm computes a window rank before compound set subtraction, then applies a final `ORDER BY` and comma-form `LIMIT offset,count`.

This deliberately avoids the accepted single-`EXCEPT` next141 limit slice, the next143 final-order slice, accepted recursive/window compound slices, and accepted comma-LIMIT single SELECT behavior. The added behavior focuses on the chained `EXCEPT` removal trace and the final compound comma-LIMIT boundary over copied `wp_options` import rows.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectExceptWindowLimitCurrentSourceNext148Test.php`
- Example smoke: `php lanes/libsqlite/examples/application-compound-select-except-window-limit-current-source-next148.php`
- PHP lint: changed PHP files only
- Diff check: `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is required. The slice reuses existing native PHP `SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteSelectCompound` primitives; no ext/sqlite, upstream binary, or live service is needed.
