# Source-Neutral JSONB CHECK Current-Source Dynamic

Slice: `source-neutral-src-jsonb-check-current-source-dynamic-20260601T145908Z-0`
Base accepted HEAD: `230af65eea9aebb1e5494b80a95d24a010885d55`

## Change

- Extended the JSONB current-source source-neutral guard to scan the full adjacent JSONB source group, including JSONB generated partial UPSERT, generated index operator, patch generated index, path-operator malformed handling, and the core JSONB codec.
- Added the same JSONB source group to the lane-wide no-domain guard so future source-neutral slices catch regressions in these files.
- Removed two remaining option-shaped production-source strings from planner comments/metadata while preserving planner behavior and direct test assertions.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteSelectExpressionIndexPlan.php` - no syntax errors.
- `php -l lanes/libsqlite/src/SQLiteExpressionCoveringOrderCurrentSourceNextPlan.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteExpressionCoveringOrderCurrentSourceNext103Test.php` - no syntax errors.
- Focused source scan for the owned JSONB current-source files plus the two touched planner files reported no `wp_`, `option_*`, `autoload`, `blog_id`, or WordPress text.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteExpressionCoveringOrderCurrentSourceNext103Test.php` - 3 files / 102 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` - 5 files / 350 assertions / 0 failures.
- `php lanes/libsqlite/examples/application-jsonb-check-current-next69.php` - emitted generic `app_settings` JSONB CHECK admission with 2 accepted and 2 rejected changes.
- `git diff --check -- lanes/libsqlite` - passed.

## Dependency Closure

No new support component is needed. This is source-neutral guard hardening and production-source wording cleanup over existing JSONB, CHECK, expression-index, and current-source planner behavior.

## Non-Overlap

This patch does not add upstream runner metadata, dashboard/root edits, pass-counter movement, or new JSONB CHECK admission behavior. It avoids accepted JSON table, WAL, pager, B-tree, and upstream-corpus coverage.

Root harness: not run - isolated micro-slice.
