# compound-select-name-resolution-current-source-next121

Behavior slice: compound SELECT final `ORDER BY` name resolution now searches
result aliases, source column names, and exact projected expressions across
each compound arm from left to right, then maps the matched ordinal back to
the left-most output column name used by SQLite compound results.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundNameResolutionCurrentSourceNext121Test.php`
- Result: `1 test files, 59 assertions, 0 failures`
- PASS-line delta: `+59`

Additional regression evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectOrderLimitCurrentSourceNext110Test.php lanes/libsqlite/tests/SQLiteCompoundRecursiveLimitCurrentSourceNext117Test.php lanes/libsqlite/tests/SQLiteCompoundCollationSetOperatorTest.php lanes/libsqlite/tests/SQLiteCompoundExceptIntersectAffinityTest.php`
- Result: `4 test files, 150 assertions, 0 failures`

Application smoke:

- `php lanes/libsqlite/examples/application-compound-name-resolution-current-source-next121.php --self-test`
- Covers copied `wp_options` import staging where the right/staged arm names
  columns as `staged_key` / `staged_score`, while callers still receive the
  left-most `option_key` / `import_score` output names.

Non-overlap:

- Avoids accepted compound row composition, recursive compound LIMIT/OFFSET,
  compound expression ORDER BY on the left-most arm, compound collation set
  operators, JSON table SELECT-source/constraint work, VFS/WAL/B-tree current
  source clusters, and queued compound118/compound119 behavior.

Dependency closure:

- No new support component is needed. This reuses existing
  `SQLiteSelectSql`, `SQLiteSelectCompound`, `SQLiteSelectResult`, and scalar
  expression parsing.
