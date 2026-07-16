# Trigger/FK interaction corpus next9

This slice adds a bounded upstream-style trigger and foreign-key interaction executor for parent `DELETE` statements. It covers the ordering surface not covered by the existing standalone DML trigger conflict and deferred FK cascade corpora:

- BEFORE DELETE triggers may rewrite or delete child rows before `ON DELETE` action selection.
- `CASCADE`, `SET NULL`, `SET DEFAULT`, `NO ACTION`, and `RESTRICT` actions apply after BEFORE triggers and before AFTER triggers.
- AFTER DELETE audit triggers observe the post-FK child rowset.
- Multiple parent deletes are applied sequentially, so children moved by an earlier BEFORE trigger may be cascaded by a later parent delete.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerForeignKeyInteractionCorpusTest.php`
- `php lanes/libsqlite/examples/application-trigger-fk-interaction.php`

Dependency closure: no new support component is needed. This reuses the lane's existing bounded row-array executor pattern and does not require `ext/sqlite`, hydrated upstream caches, or live services.

Non-overlap: this avoids the accepted DML trigger conflict-inheritance corpus, deferred FK cascade corpus, recursive trigger recursion corpus, VFS/WAL/B-tree accepted clusters, and the latest accepted SELECT/JSON/VFS/B-tree slices. It specifically targets trigger/FK action ordering.
