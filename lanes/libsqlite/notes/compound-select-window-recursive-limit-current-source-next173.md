# Compound SELECT Window Recursive Limit Current Source Next173

## Scope

- Adds a current-source behavior slice for parser-level compound `UNION`
  distinct followed by a derived `EXCEPT` anti-arm after mixed
  `row_number()`/`dense_rank()` window arms.
- Preserves recursive CTE `LIMIT OFFSET` queue semantics and final compound
  `ORDER BY ... LIMIT ... OFFSET` boundaries over copied `wp_options` rows.
- Avoids accepted next160/164/167 surfaces by requiring a distinct-union
  prelude plus derived EXCEPT removal instead of another `INTERSECT` or
  `UNION ALL` compound/window variant.

## Evidence

- Focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext173Test.php`
- Application smoke:
  `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next173.php`
- Dependency closure: no new support component is needed; this reuses existing
  lane-local SELECT SQL, recursive CTE, compound, derived table, window, and
  LIMIT/OFFSET helpers.
