# rowvalue-update-delete-returning-window-ready-publication-metadata

This consolidation slice keeps the row-value `UPDATE`/`DELETE ... RETURNING`
window ready-publication metadata behind the stable
`prepareReadyWindowPublicationMetadata()` entrypoint. It consumes exactly four
ready candidate payloads, validates their after-ready state and retry window
rows, then emits deterministic publication receipt, ledger, handoff, and seal
hashes without numbered public method names or numbered direct artifacts.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationMetadataTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-ready-publication-metadata.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationMetadataTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-ready-publication-metadata.php --self-test`

Dependency closure: no new support component is needed; the slice reuses the
existing row-value UPDATE/DELETE RETURNING window candidate payloads.

Non-overlap: avoids suite evidence, JSON table, WAL/VFS, planner, PRAGMA,
ATTACH, B-tree, and unrelated window slices. The narrow surface is after-ready
receipt preparation for the assigned row-value RETURNING window chain.
