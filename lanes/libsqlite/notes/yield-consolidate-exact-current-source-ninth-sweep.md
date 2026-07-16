# Consolidate Exact Current-source Ninth Sweep

Consolidated the pager hot-journal savepoint cache numbered callable family in
`SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan` by replacing the
numbered public entrypoints and private helper names for the 83/100/149/157
variants with descriptive stable names. Direct tests and Application smokes now
call the stable entrypoints:

- `planRecoveredHotJournalSavepointRetry`
- `planRecoveredSourceReleaseReads`
- `planRecoveredSourceNextStatement`
- `planRecoveredSourceDigestFence`

The exact removed-suffix scan remains clean. No production class/file was added, no numbered compatibility shim was
introduced, and no hidden legacy loader was used.

Verification:

- `php -l` on the changed production file, four changed tests, and four changed
  examples: all reported no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext83Test.php lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext100Test.php lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext149Test.php lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext157Test.php`
  passed: `4 test files, 305 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-hot-journal-savepoint-cache-current-source-next83.php --self-test`
  passed.
- `php lanes/libsqlite/examples/application-hot-journal-savepoint-cache-current-source-next100.php --self-test`
  passed.
- `php lanes/libsqlite/examples/application-pager-hot-journal-savepoint-cache-current-source-next149.php --self-test`
  passed.
- `php lanes/libsqlite/examples/application-pager-hot-journal-savepoint-cache-current-source-next157.php --self-test`
  passed.

Dependency closure: no new support component is needed; this consolidation
reuses the existing pager cache, hot-journal recovery, and savepoint
before-image modeling.
