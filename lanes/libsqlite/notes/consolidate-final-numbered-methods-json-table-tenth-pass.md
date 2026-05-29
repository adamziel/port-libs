# JSON Table Numbered Method Consolidation Tenth Pass

Consolidated the remaining JSON-table generated-path rowid cost production
helper that used the generated current-source slice suffix into the stable
descriptive `currentSourceGeneratedPathRowidCostSelectionVariant()` entry point
on `SQLiteJsonTablePlan`.

Direct tests and WordPress examples for the next261-268 and next281-284/289-292
JSON-table cost-selection slices now call the stable method name. Payload keys
and existing slice-specific assertions are preserved so this patch only removes
the numbered production method surface.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l` for the changed JSON-table focused tests and examples
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext261Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext262Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext263Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext264Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext265Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext266Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext267Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext268Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext281Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext282Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext283Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext284Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext289Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext290Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext291Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext292Test.php`
- Changed WordPress examples with `--self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the existing
native JSON-table generated-path rowid cost planner.
