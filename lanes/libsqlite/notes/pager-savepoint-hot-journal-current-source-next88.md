# Pager Savepoint Hot-Journal Current Source Next88

## Behavior

Adds `SQLitePagerSavepointHotJournalCurrentSourceNextPlan`, a bounded pager
plan for the rollback-journal path where hot-journal recovery must refresh the
current page source before a transaction savepoint captures before-images,
rolls back the current attempt, and opens a next retry savepoint.

This is intentionally disjoint from accepted batch83 cache invalidation,
batch85 hot-journal plus WAL savepoint checkpoint, and batch86 statement
journal retry behavior. This slice does not checkpoint WAL frames and does not
model statement journals; it covers rollback-journal hot recovery feeding the
transaction savepoint current source.

## Focused Evidence

Command:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointHotJournalCurrentSourceNext88Test.php`

Result:

`1 test files, 76 assertions, 0 failures`

PASS-line delta: `+76` focused PASS lines.

WordPress smoke:

`php lanes/libsqlite/examples/wordpress-pager-savepoint-hot-journal-current-source-next88.php`

The smoke reports copied `wp_options` pages where a hot rollback journal
restores clean root/transient pages before the current savepoint captures
before-images, rolls back the current import attempt, and writes a next retry
savepoint over the refreshed current source.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local pager,
rollback-journal, savepoint page-image, and current-source planning concepts.
