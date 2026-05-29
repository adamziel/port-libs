# pager-master-journal-reader-cache-current-source-next196

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`, a current-source pager admission fence layered above next192. After master-journal recovery, cached reader pages may only be reused when each attached rollback-journal member still has the current journal-header digest. This catches a reused file-token case where the member path metadata is unchanged but the rollback-journal header/salt belongs to a different recovery source.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext196Test.php`
  - `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next196.php --self-test`
  - WordPress smoke passed

## Non-Overlap

This slice does not repeat next192 attached member journal token fencing, next191 master-journal delete/directory-sync fencing, next186 recovered-page-set sequencing, accepted rollback-journal apply/commit, or accepted super-journal commit/apply paths. It adds only the extra member rollback-journal header digest fence for same-token current-source reuse.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded pager/master-journal reader-cache planning and current-source digest evidence.
