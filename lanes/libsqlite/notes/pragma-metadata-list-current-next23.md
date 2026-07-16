# PRAGMA Metadata List Current Next23

This slice adds bounded native PHP coverage for upstream-style PRAGMA metadata
row producers that were not part of the accepted schema table/index PRAGMA
cluster:

- `PRAGMA function_list` and `pragma_function_list()`
- `PRAGMA module_list` and `pragma_module_list()`
- `PRAGMA collation_list` and `pragma_collation_list()`

The implementation keeps the existing schema PRAGMA parser/cursor path and adds
no-target metadata rowsets with SQLite-shaped columns. It supports default
built-in function/module/collation rows plus bounded custom rows for Application
diagnostics, including custom scalar/window functions, virtual-table modules,
and site-specific collations.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaMetadataListCurrentNext23Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 71 assertions, 0 failures
```

New focused PASS lines: 61.

Additional verification:

```text
$ php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php
No syntax errors detected in lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php

$ php -l lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php
No syntax errors detected in lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php

$ php -l lanes/libsqlite/tests/SQLitePragmaMetadataListCurrentNext23Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaMetadataListCurrentNext23Test.php

$ php -l lanes/libsqlite/examples/application-pragma-metadata-list-current-next23.php
No syntax errors detected in lanes/libsqlite/examples/application-pragma-metadata-list-current-next23.php

$ php lanes/libsqlite/examples/application-pragma-metadata-list-current-next23.php
Printed copied wp_options metadata preflight JSON with function, module, and
collation rows.

$ git diff --check -- lanes/libsqlite
No output.
```

Dependency closure: no new support component is needed. The slice reuses the
lane-local schema PRAGMA catalog and row cursor primitives; it does not require
`ext/sqlite`, upstream binaries, or live-service provider tests.

Non-overlap: this avoids accepted `pragma_table_info`, `pragma_table_xinfo`,
`pragma_index_list`, `pragma_index_info`, `pragma_index_xinfo`,
`pragma_foreign_key_list`, direct schema current-source PRAGMAs, JSON table
source/cursor/constraint work, SELECT SQL text/subquery/GROUP/ORDER/LIMIT
clusters, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint
clusters, B-tree page move/root-collapse/overflow release, and Unicode GLOB
work.
