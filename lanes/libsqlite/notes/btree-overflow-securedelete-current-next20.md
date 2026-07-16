# B-tree Overflow Secure-delete Current Next20

This slice adds `SQLiteOverflowFreelistReleasePlan::fromOverflowChains()` so
delete planning can start from current overflow-chain first pages, follow the
SQLite overflow next-page pointers, and then release those obsolete pages
through the existing freelist, secure-delete, and auto-vacuum pointer-map
planner.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowSecureDeleteCurrentNext20Test.php`
- Result: `1 test files, 52 assertions, 0 failures`
- PASS-line delta for lane status: `+52`

Application smoke:

- `php lanes/libsqlite/examples/application-overflow-securedelete-current-next20.php`
- The smoke reports copied `wp_options` table/index overflow chains released
  from current next-page pointers, secure-delete-cleared freelist leaves,
  freelist allocation order, and free-page pointer-map types without ext/sqlite.

Non-overlap:

- This does not repeat accepted overflow next-pointer collection alone or
  overflow freelist release from already-computed delete results. The new
  behavior is the current-chain entry point that converts first-page plus
  payload-byte metadata into release plans before secure-delete/freelist apply.
- It avoids accepted B-tree page relocation/root collapse/interior merge,
  bulk overflow freeblocks, overflow freelist release, VFS writer/lock/sync,
  rollback/WAL apply, JSON table source/cursor/constraint pushdown, SELECT SQL
  subqueries/grouping/comma LIMIT/expression ORDER BY, and Unicode GLOB
  clusters.

Dependency closure:

- No new support component is needed. The patch reuses lane-local overflow
  page next-pointer parsing, SQLite database page readers, freelist trunk/free
  planning, secure-delete page clearing, and auto-vacuum pointer-map mutation.
