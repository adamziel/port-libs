### 2026-05-27 PRAGMA runtime module/collation current next24

This slice adds bounded native PHP runtime PRAGMA introspection for
`PRAGMA collation_list`, `PRAGMA module_list`, and `PRAGMA function_list`.
It is separate from the accepted schema PRAGMA table-valued batch21 work:
these rows describe registered runtime capabilities, not sqlite_schema
tables or indexes.

Application path: copied `wp_options` import/preflight code can check that
SQLite-compatible collations, JSON table modules, and extension-like helper
functions are available before planning JSON virtual-table scans or option-name
comparisons without requiring ext/sqlite.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaRuntimeModuleCollationCurrentNext24Test.php`
- `php -l lanes/libsqlite/src/SQLitePragmaRuntimeCatalog.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaRuntimeModuleCollationCurrentNext24Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-runtime-module-collation-current-next24.php`
- `php lanes/libsqlite/examples/application-pragma-runtime-module-collation-current-next24.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted batch21 `pragma_table_info`,
`pragma_table_xinfo`, `pragma_index_list`, `pragma_index_info`,
`pragma_index_xinfo`, and `pragma_foreign_key_list` table-valued/schema
surfaces. It also avoids accepted JSON table cursor/source/hidden/visible
constraint work, SELECT SQL text/JOIN/GROUP/ORDER/subquery surfaces, VFS
writer/lock/sync/rollback clusters, WAL byte/checkpoint clusters, Unicode GLOB,
and B-tree page move/root/overflow clusters.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP test harness and bounded libsqlite runtime metadata model.
