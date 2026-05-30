# Pager Master-Journal Reader Cache Current Source Next200

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext200Plan`.
It fences pager reader-cache reuse after master-journal recovery with
member-journal generation tickets and a transaction-wide member-generation
token. A multi-page reader that spans the main database and an attached
Application users database must reopen if any member rollback journal advances,
even when one page image is byte-identical.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext200Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext200Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next200.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext200Test.php`
  - `1 test files, 81 assertions, 0 failures`
  - 81 focused PASS lines.
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next200.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- This does not repeat next194 transaction snapshot digests, next193 stable
  repeated master reads, next191 delete-directory-sync fencing, VFS
  rollback/commit/sync application, WAL checkpoint/savepoint byte truncation,
  or B-tree/SQL/JSON/encoding surfaces.
- The new behavior is member-journal generation cohesion across a current
  reader transaction after master-journal recovery.

Dependency closure:

- No new support component is needed. The slice reuses lane-local
  master-journal member parsing, current page images, and reader-cache ticket
  hashing.

Next task: continue pager work only on a non-overlapping durable transaction,
checkpoint, or reader-cache admission edge with focused tests.
