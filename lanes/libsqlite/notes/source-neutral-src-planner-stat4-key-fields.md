## source-neutral-src-planner-stat4-key-fields

Neutralized the focused STAT4 covering-expression IN planner caller surface from
legacy option-table-shaped fields to generic application setting fields:
`app_settings`, `key_name`, `key_value`, `setting_id`, `tenant_id`, and
`load_policy`.

Also replaced the remaining owned STAT4 planner source fallback table label used
by a deferred seek diagnostic with `app_settings`.

Behavior preserved:
- The current-source STAT4 IN planner still reparses stale prepared metadata.
- Covering-index payload columns still elide table lookup.
- STAT4 matched keys, current/next row order, cursor tape, and validation
  assertions remain unchanged except for generic field names.

Dependency closure: no new support component needed; the existing generic
`SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan` already accepts
caller-provided expression columns and covering columns.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4CoveringExpressionInCurrentSourceNextTest.php`
  -> `1 test files, 66 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php lanes/libsqlite/tests/SQLitePlannerStat4CoveringExpressionInCurrentSourceNextTest.php`
  -> `135 test files, 7661 assertions, 0 failures`
