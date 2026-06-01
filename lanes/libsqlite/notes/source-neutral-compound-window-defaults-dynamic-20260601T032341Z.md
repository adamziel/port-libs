# Source-Neutral Compound Window Defaults Dynamic

Slice: `source-neutral-src-compound-window-defaults-dynamic-20260601T032341Z-0`

Changed the compound/window recursive-limit source defaults away from legacy option-table wording:

- Removed hardcoded `wp_options` table checks/prose from `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`.
- Replaced dynamic `option_id` / `option_name` row fallbacks with `setting_id` / `key_name`.
- Renamed the comma-boundary load-policy diagnostic away from autoload/option wording.
- Updated the directly coupled comma-boundary and final-page window tests/examples to use `app_settings`, `setting_id`, `key_name`, and `load_policy`.
- Neutralized coupled observable reason strings from application-option wording to application-setting wording.

Dependency closure: no new support component needed; this reuses the existing native compound SELECT, recursive CTE, window-function, and final LIMIT helpers.

Verification:

- `php -l` passed for the changed source, test, and example PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCommaBoundaryTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitFinalPageWindowLimitTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitTailWindowLimitBoundaryTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitExpressionLimitBoundaryTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext194Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext200Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext209Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: 9 files, 2177 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-comma-boundary.php --self-test` passed.
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-final-page-window.php` passed.
- `rg -n "application-option|Application option|\\bwp\\b|wp_|wp_options|option_id|option_name|autoload|Autoload" lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php` returned no matches.

Additional domain-family check:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimit*Test.php` currently reports 78 files, 29410 assertions, 10 failures.
- A clean `HEAD` archive reproduced the same `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext205Test.php` tie-order failures before this patch, so the broader family failure is pre-existing executor/order instability rather than source-neutral regression in this slice.
