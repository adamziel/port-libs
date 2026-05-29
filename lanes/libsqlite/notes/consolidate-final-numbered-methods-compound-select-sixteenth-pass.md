# Compound Select Numbered Method Consolidation Sixteenth Pass

Consolidated `SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan` into stable method names:

- `compareNext138()` is now `compare()`.
- Private helpers such as pre-order row extraction, EXCEPT arm indexing, order-term extraction, row signatures, value classes, and replan reasons now use unsuffixed descriptive names.
- The direct focused test and WordPress example were renamed to unsuffixed files and migrated to the canonical method.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundExceptOrderAffinityCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-except-order-affinity-current-source-next.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundExceptOrderAffinityCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-compound-except-order-affinity-current-source-next.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production method-name consolidation that preserves the existing compound SELECT behavior.
