# Compound SELECT numbered-method consolidation forty-sixth pass

Consolidated the compound SELECT recursive/window current-source dequeue fence
inside `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` away from
the generated `Next237` helper and payload names. The production entry point
remains the descriptive `compareCurrentSourceDequeue()`, with stable helper
names such as `assertSupportedCurrentSourceDequeue()`,
`currentSourceDequeueFence()`, and `validateCurrentSourceDequeueCursor()`.

Direct compound-select tests and the WordPress smoke were migrated to
unsuffixed current-source dequeue names:

- `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceDequeueTest.php`
- `wordpress-compound-select-window-recursive-limit-current-source-dequeue.php`

Downstream compound-select tests that pass the dequeue cursor into later
fences now use the stable cursor keys `currentDequeueToken`,
`requiredCurrentDequeueAcks`, `acknowledgedCurrentDequeueAcks`, and
`currentSourceDequeue`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceDequeueTest.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-dequeue.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceDequeueTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext240Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext243Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext245Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext246Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext248Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext249Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext250Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext251Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext252Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext253Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext254Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext255Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext256Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext257Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Test.php` -> `16 test files, 7819 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-dequeue.php`

Dependency closure: no new support component needed; this reuses the existing
compound SELECT recursive queue, window output, final LIMIT/OFFSET, cursor, and
test runner infrastructure.

Root harness: not run - isolated micro-slice.
