# PRAGMA Integrity Foreign Key Index Xinfo Current Source Next118

## Behavior

`SQLiteAttachedSchemaCatalog` now resolves schema-qualified table-valued
`pragma_index_xinfo()` targets whose schema and index names contain dots, such
as a Application archive database attached as `wp.archive` with an index named
`wp.archive.option_names.name.u`. The resolved local index name is passed to the
owning schema catalog before `index_xinfo` rows are materialized, and the FK /
index / integrity current-source cursor keeps its source hash stable across
paged reads.

The same quoting fix also protects FK parent UNIQUE-index admission when an
index name contains dots: internal `PRAGMA index_xinfo(...)` calls now quote the
index name instead of interpolating a bare dotted identifier.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityForeignKeyIndexXinfoCurrentSourceNext118Test.php`
  - `1 test files, 49 assertions, 0 failures`
  - `44` new PASS lines
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-current-source-next118.php --self-test`
  - `application-pragma-index-xinfo-current-source-next118 self-test passed`

## Non-Overlap

This does not repeat accepted PRAGMA integrity/FK pagination, index admission,
foreign-key current-source resolution, `index_xinfo` expression metadata, or
table-valued PRAGMA cursor coverage. The new surface is quoted dotted schema /
index name resolution before existing `index_xinfo` and FK/index/integrity
current-source streams are built.

## Dependency Closure

No new support component is needed. The slice reuses the existing attached
schema catalog, schema PRAGMA catalog, FK/index integrity checker, and
current-source cursor hashing.
