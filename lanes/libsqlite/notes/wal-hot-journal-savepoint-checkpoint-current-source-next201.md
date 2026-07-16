# WAL hot-journal savepoint checkpoint current-source next201

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next201`.

Behavior: adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next201PublishCurrentSources()` to admit reopened reader current-source rows only after next200 durable reader admission has succeeded, the hot journal is absent, every durable reader ticket has a matching current-source row, checkpoint/WAL source digests match, savepoint generation matches, checkpoint visibility is true, and reader cache epochs have been rebased.

Application smoke: `application-wal-hot-journal-savepoint-checkpoint-current-source-next201.php` covers a copied `wp_options` import retry where checkpoint-database and next-WAL reader sources are exposed only after hot-journal recovery and savepoint checkpoint publication agree.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext201Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next201.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext201Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next201.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `97917` to `97982` from 65 newly passing focused PASS lines. Mapped upstream coverage remains `620 / 1589`; this is focused WAL/pager behavior over existing WAL hot-journal/savepoint/checkpoint inventory rather than a new manifest-backed upstream row.

Non-overlap: this avoids accepted WAL byte truncation, VFS writer/sync/savepoint application, rollback-journal apply/commit, checkpoint transaction planning, next194 reader-generation sealing, next200 durability receipt validation, master-journal reader-cache work, JSON, B-tree, SQL executor, PRAGMA, and encoding clusters. The new surface is specifically current-source reader cache publication after next200 durable admission.

Dependency closure: no new support component is needed. The slice reuses existing native PHP WAL/checkpoint/source digest receipts and admission arrays.
