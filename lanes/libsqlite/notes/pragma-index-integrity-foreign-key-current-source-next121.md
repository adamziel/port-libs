# PRAGMA Index Integrity Foreign-Key Current Source Next121

This slice adds a current-source cursor that pages a copied Application schema
preflight stream made from three existing native PHP behaviors:

- `PRAGMA index_xinfo(...)` rows for an index involved in an import lookup.
- root-page integrity diagnostics from `PRAGMA integrity_check`.
- `PRAGMA foreign_key_check(...)` rows for orphaned copied rows.

The combined source id hashes the database bytes, schema/catalog snapshots,
`index_xinfo` SQL, integrity SQL, foreign-key SQL, and table-valued mode. Resume
requests are rejected when any of those inputs or the expected next offset
changes, preventing stale import diagnostics after schema rebuilds.

Verification for this handoff:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexIntegrityForeignKeyCurrentSourceNext121Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-integrity-foreign-key-current-source-next121.php`
- PHP lint for changed PHP files.
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is required. The slice reuses
existing lane-local catalog, PRAGMA, integrity, record, page, and FK primitives.
