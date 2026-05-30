# JSON table final-cost consolidation

Consolidated the generated-path rowid final-cost JSON table wrapper into the
stable `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidFinalCost()` entry.
The direct test and Application smoke were renamed to descriptive unsuffixed
filenames, and the production helper/output keys now use final-cost names rather
than a worker-numbered method surface.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidFinalCostTest.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-final-cost.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidFinalCostTest.php`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-final-cost.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: none. This is consolidation only; it preserves the
existing final-cost scenario coverage without claiming new upstream behavior or
additional mapped PHP PASS lines.

Dependency closure: no new support component is needed. This reuses native PHP
JSON table row generation, generated-path rowid xColumn cache materialization,
rowid alias projection/order planning, JSON path validation, and planner
metadata helpers.
