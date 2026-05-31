# Full-run application WAL rollback JSON dynamic parity

- Slice: `full-run-parity-application-wal-rollback-json-dynamic-20260531T071358Z-0`
- Base accepted HEAD: `96c3c12f0e388eba581b5758d55cd85f17d538ef`
- Behavior: extends `SQLiteJsonImportRollbackWalPlan` with `dynamicFullRunMaterializedWalScenarios()`, deterministic generic application fixtures that fail a JSON import batch, roll back to a preexisting WAL prefix, retry successfully with materialized WAL frames, then append a second successful materialized JSON import on the recovered WAL stream.
- Focused assertion growth: adds checks in `SQLiteApplicationWalRollbackJsonDynamicParityTest.php` for 18 full-run scenarios covering 512/1024 byte pages, JSON text and JSONB catalog rows, preexisting WAL prefixes, post-retry frame continuity, checksum chaining, final commit markers, tenant isolation, and follow-up inserted settings.
- Non-overlap: this does not repeat earlier rollback-only, retry-only, corrupt WAL tail/checksum, inserted-setting rollback, or single successful materialized WAL scenarios. The new surface is the chained full-run recovery path where a second materialized successful import appends after a recovered retry WAL stream.
- Dependency closure: no new support component is needed; the slice reuses native JSON mutation, savepoint page-image rollback, WAL byte truncation, WAL checksum parsing, and successful WAL frame materialization.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
- `git diff --check -- lanes/libsqlite`
