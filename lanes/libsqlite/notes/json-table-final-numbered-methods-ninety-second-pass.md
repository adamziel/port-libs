# JSON Table Final Numbered Methods Ninety-Second Pass

This consolidation pass renames a private generated-path rowid snapshot helper cluster in
`SQLiteJsonTablePlan.php` to stable descriptive method names:

- final-cost snapshot helpers
- yield-guard snapshot helpers
- current-source yield-row snapshot helpers
- pinned-source snapshot helpers
- xFilter argv snapshot helpers

Observable behavior is intentionally unchanged. The existing array keys, dependency
strings, action labels, opcodes, cost classes, and `next184` / `next187` / `next190` /
`next194` / `next200` proof labels remain as compatibility metadata for generated
tests and handoff chains.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  Result: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidFinalCostTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidYieldTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext200Test.php`
  Result: 3 test files, 158 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTable*Test.php`
  Result: 305 test files, 20187 assertions, 0 failures.

Dependency closure: no new support component is needed; this is an internal production
helper-name consolidation only.

Non-overlap: this pass avoids JSON table behavior changes and preserves the accepted
generated metadata surface that recent rejected consolidation handoffs accidentally
renamed.
