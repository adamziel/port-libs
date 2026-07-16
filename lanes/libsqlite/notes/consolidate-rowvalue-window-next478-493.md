Row-value/window numbered-method consolidation, next478-493

- Consolidated `executeNext478()` through `executeNext493()` into the existing canonical `executeWindowCurrentSourceContinuation()` helper.
- Updated the direct Application smoke to call the canonical helper instead of dynamically dispatching numbered production methods.
- Kept later `executeNext494()` behavior by seeding it from the canonical continuation at step 493.
- Dependency closure: no new support component needed; this is a production method-wrapper consolidation only.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next478-493.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext478493Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext478493Test.php`
  - `1 test files, 16 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next478-493.php --self-test`
- `git diff --check -- lanes/libsqlite`
