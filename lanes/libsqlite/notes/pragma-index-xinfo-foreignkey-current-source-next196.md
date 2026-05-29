# PRAGMA index_xinfo + foreign_key current-source next196

This slice adds a bounded current-vs-next PRAGMA metadata paginator for copied
WordPress taxonomy schema reparses. It pages `PRAGMA index_xinfo` rows together
with `PRAGMA foreign_key_list` rows from the same current and next catalog
snapshots, preserving expression-index terms, auxiliary rowid terms,
composite foreign-key rows, source hashes, and resume cursor validation.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- Result: `1 test files, 75 assertions, 0 failures`
- PASS-line delta: `+62`

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next196.php --self-test`
- Result: `wordpress-pragma-index-xinfo-foreignkey-current-source-next196 self-test passed`

Non-overlap:

This does not repeat accepted PRAGMA integrity/rootpage, pointer-map,
quickcheck, recursive foreign-key catalog, or previous standalone
`index_xinfo` cursor work. It adds only the combined current/next source cursor
needed to keep expression-index metadata and composite FK metadata tied to the
same copied schema reparse snapshot.

Dependency closure:

No new support component is needed. The slice reuses existing
`SQLitePragmaSchemaCatalog`, `SQLiteSchemaRecord`, and lane test harness
components.
