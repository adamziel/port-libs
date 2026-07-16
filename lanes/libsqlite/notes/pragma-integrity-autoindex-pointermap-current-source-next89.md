# pragma-integrity-autoindex-pointermap-current-source-next89

This slice adds `SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield`,
a current-source bridge for `PRAGMA integrity_check` autoindex repair
preflights. It composes the accepted autoindex integrity collector with live
auto-vacuum pointer-map entries for each autoindex root page, then exposes
current/next source IDs, blocker buckets, pagination, and root pointer-map
type/parent annotations.

Application relevance: copied `wp_options` repair/import diagnostics can now keep
autoindex schema mismatches, orphan autoindexes, and autoindex root pointer-map
corruption in one resumable current-source stream before applying the next
repair step.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceNext89Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceNext89Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-integrity-autoindex-pointermap-current-source-next89.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pragma-integrity-autoindex-pointermap-current-source-next89.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceNext89Test.php`
  - `1 test files, 76 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-integrity-autoindex-pointermap-current-source-next89.php --self-test`
  - `application-pragma-integrity-autoindex-pointermap-current-source-next89 self-test passed`

Non-overlap: this avoids accepted PRAGMA integrity/FK/index pagination,
pointer-map/freelist pagination, table-scoped integrity, b-tree order
integrity, autoindex root pagination, and batch87 planner/pager/VFS/WAL
surfaces. The new behavior is specifically the current-source autoindex
integrity stream annotated with root pointer-map type/parent state and next
blocker buckets.

Dependency closure: no new support component is needed. The patch reuses the
native schema parser, autoindex integrity collector, SQLite database image
reader, and pointer-map primitives already present in the libsqlite lane.
