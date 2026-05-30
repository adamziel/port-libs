# Compound SELECT Window Recursive Limit Source Classes

## Scope

- Adds a current-source behavior slice for parser-level compound `UNION ALL`
  plus `INTERSECT` after mixed `lag()`/`lead()` window arms.
- Preserves recursive CTE `LIMIT OFFSET` queue semantics and the final
  compound `ORDER BY ... LIMIT ... OFFSET` boundary over copied `wp_options`
  rows.
- Avoids accepted recursive window limit, offset, and current-source admission
  surfaces by requiring the derived `INTERSECT` arm and mixed window functions
  rather than another plain compound/window final-limit variant.

## Evidence

- Focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitSourceClassesTest.php`
- Application smoke:
  `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-source-classes.php --self-test`
- Dependency closure: no new support component is needed; this reuses existing
  lane-local SELECT SQL, recursive CTE, compound, derived table, window, and
  LIMIT/OFFSET helpers.
