# Pager Master-Journal Reader-Cache Recovery Receipt

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMasterJournalRecoveryReceipt()`, a current-source reader-cache fence for completed master-journal recovery receipts.

After `next251` has admitted the active reader snapshot, this slice requires both cached pages and next read tickets to carry the current `master_journal_recovery_receipt_token`. A stale receipt invalidates only the otherwise-admitted cache pages, forces affected reader ids to reopen, and preserves inherited invalidation reasons from snapshot, pager-generation, and current-source provenance fences.

## Focused evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterJournalRecoveryReceiptTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 68 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-recovery-receipt.php
{
    "status": "pager-master-journal-reader-cache-current-source-next254",
    "invalidated_cache_page_numbers": [
        2
    ],
    "reopen_reader_ids": [
        "read-2"
    ],
    "read_cache_hits": {
        "read-1": true,
        "read-2": false,
        "read-3": true
    }
}
```

## Non-overlap

This slice does not repeat `next251` reader snapshots, `next247` pager cache generation, `next243` current-source provenance, statement-root/schema/read-transaction tokens, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.

## Dependency closure

No new support component is needed. The patch reuses the existing pager master-journal reader-cache plan chain and adds only the current recovery-receipt admission token.
