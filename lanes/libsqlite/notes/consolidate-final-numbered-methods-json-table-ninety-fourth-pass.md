# JSON Table Numbered Method Consolidation Ninety-fourth Pass

Consolidated the private `Next94` JSON-table rowid residual helpers in
`SQLiteJsonTablePlan` into stable descriptive helper names:

- `hiddenRowidResidualConstraints()`
- `sourceRowidResidualConstraints()`
- `rowidsFromRows()`
- `sourceRowidSummary()`
- `sourceRowTransitions()`
- `sourceRowTransitionReason()`

Observable result keys, opcodes, reader policies, replan reasons, and accepted
numbered dependency strings remain preserved. This pass also repaired the stale
next219 direct test caller to use the canonical limit-admission entry point and
kept old dependency strings as aliases for the canonical hidden-rowid,
rowid-hidden, lateral-hidden, and generated-hidden-rowid implementations.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext219Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext219Test.php`
  - `1 test files, 52 assertions, 0 failures`
- `php tools/run-tests.php $(rg --files lanes/libsqlite/tests | rg 'SQLiteJsonTable.*Test\.php$' | sort)`
  - `305 test files, 20187 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a production helper
name consolidation and direct-caller cleanup only.
