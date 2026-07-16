# real-upstream-corpus-trigger-fkey-dynamic-20260531T063032Z-0

Base accepted HEAD: `7685e747971ca86ceced872addf2e1032378bd34`

This slice adds a source-neutral real upstream trigger/FK dynamic batch for
foreign-key parent comparison semantics from the hydrated SQLite upstream
checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
  - `e_fkey-15.*`: parent/child storage class matching across integer, text,
    and blob values.
  - `e_fkey-16.*`: parent key collation controls child text comparison.
  - `e_fkey-17.*`: parent key affinity is applied to child keys before
    comparison.

Changed files:

- `lanes/libsqlite/src/SQLiteForeignKeyComparisonPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicParentComparison20260531Test.php`

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicParentComparison20260531Test.php`
  - `1 test files, 5408 assertions, 0 failures`
  - `5406` focused PASS lines from two upstream source citation cases, 200
    seeds x 5 comparison families, per-path behavior assertions, and one
    ownership-count case.

Non-overlap: this does not repeat prior trigger/FK action, deferred pragma,
missing-parent, `fkey8` deferred affinity, `fkey5` foreign_key_check, triggerC
affinity timing, rowid-variable, recursive trigger, or RETURNING batches. The
new surface is specifically parent-key comparison semantics from `e_fkey.test`
sections 15 through 17.

Dependency closure: no new support component is needed. The helper is native
PHP and lane-local, and the scenarios use generic setting/application values
only.
