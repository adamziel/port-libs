# Pager savepoint rollback journal lifecycle consolidation

This consolidation removes the generated numeric production helper name from the pager savepoint rollback-journal lifecycle entry point and promotes the same rollback-journal SAVEPOINT behavior to `SQLitePagerSavepointCurrentNextPlan::rollbackJournalLifecycle()`. It composes the accepted savepoint stack transition with rollback-journal byte accounting, statement-journal page sets, restore/merge page sets, final journal disposition, reserved-lock state, and sync sequencing for DELETE/TRUNCATE/PERSIST/MEMORY/OFF modes.

WordPress relevance: copied `wp_options` imports can keep an outer transaction open while rolling back a failed plugin batch to an outer SAVEPOINT. The new smoke records which dirty pages stay in the current transaction, which pages must be restored from the statement journal, and whether the rollback journal remains open, truncates, deletes, or zeroes its header.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointRollbackJournalLifecycleTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 65 assertions, 0 failures
```

Additional verification:

```text
$ php -l lanes/libsqlite/src/SQLitePagerSavepointCurrentNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLitePagerSavepointCurrentNextPlan.php

$ php -l lanes/libsqlite/tests/SQLitePagerSavepointRollbackJournalLifecycleTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePagerSavepointRollbackJournalLifecycleTest.php

$ php -l lanes/libsqlite/examples/wordpress-pager-savepoint-rollback-journal-lifecycle.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-pager-savepoint-rollback-journal-lifecycle.php

$ php lanes/libsqlite/examples/wordpress-pager-savepoint-rollback-journal-lifecycle.php
{
    "scenario": "wordpress-pager-savepoint-rollback-journal-lifecycle",
    "status": "rolled_back_to_savepoint",
    "finalDisposition": "keep_open"
}

$ git diff --check -- lanes/libsqlite
```

Expected dashboard movement: no `phpPass` delta; this is a consolidation-only replay of the existing 65 independent PASS lines under a descriptive test filename and unsuffixed production method.

Non-overlap: avoids accepted savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback application, rollback-journal commit apply, super-journal commits, VFS sync plan/apply, and batch65 pager savepoint stack transition assertions. This slice is specifically rollback-journal lifecycle current/next planning for nested SAVEPOINT operations.

Dependency closure: no new support component is needed; the slice reuses `SQLiteSavepointStack` and the existing bounded pager planner.
