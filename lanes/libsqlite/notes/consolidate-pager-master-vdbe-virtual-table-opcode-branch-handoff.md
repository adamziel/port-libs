# Pager Master Reader Cache VDBE Virtual Table Opcode Branch Handoff Consolidation

This pass removes the numbered VDBE virtual-table opcode branch production helper block from `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`.

The canonical replacement is `currentSourceVdbeVirtualTableOpcodeBranchHandoff()`, which applies the same ordered reader-cache fences through a descriptive spec list and restamps the public status, operation labels, read source labels, and dependency marker away from the numbered helper surface.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheVdbeVirtualTableOpcodeBranchHandoffTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-vdbe-virtual-table-opcode-branch-handoff.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheVdbeVirtualTableOpcodeBranchHandoffTest.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-vdbe-virtual-table-opcode-branch-handoff.php`

Dependency closure: no new support component is needed; this is production helper consolidation only.
