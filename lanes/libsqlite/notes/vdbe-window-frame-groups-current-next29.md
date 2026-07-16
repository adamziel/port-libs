# VDBE Window Frame GROUPS Current Next 29

This slice adds composite peer-key handling for bounded VDBE-style aggregate window frames whose SQL text uses:

`GROUPS BETWEEN CURRENT ROW AND N FOLLOWING`

The previous bounded executor keyed GROUPS peers from only the first window `ORDER BY` term. SQLite peer groups use all window `ORDER BY` terms, so copied Application option previews such as `ORDER BY bytes, option_name` must split rows that share `bytes` but differ by `option_name`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowFrameGroupsCurrentNext29Test.php`
  - `1 test files, 46 assertions, 0 failures`
  - 40 PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php lanes/libsqlite/tests/SQLiteSelectWindowFilterAggregateCurrentNext21Test.php lanes/libsqlite/tests/SQLiteVdbeWindowFrameGroupsCurrentNext29Test.php`
  - `3 test files, 147 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-vdbe-window-frame-groups-current-next29.php`
  - Reports `peer_bytes` values `30, 30, 50, 70, 70, 30` for copied `wp_options` rows ordered by `bytes, option_name`.
- `php -l` passed for changed PHP files.
- `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

- Avoids accepted broad single-term GROUPS/RANGE current-next coverage, filter aggregate ROWS/RANGE/GROUPS cases, expression ORDER BY dispatch, grouped SELECT SQL text, and VDBE aggregate ORDER BY cursor slices.
- This is specifically multi-term GROUPS peer identity for current-to-following aggregate window frames.

Dependency closure:

- No new support component is needed; the slice reuses `SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteWindowFunction`.
