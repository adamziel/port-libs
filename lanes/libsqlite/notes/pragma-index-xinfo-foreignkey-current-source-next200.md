# pragma-index-xinfo-foreignkey-current-source-next200

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a
current-source PRAGMA helper layered on the accepted `index_xinfo` /
foreign-key cursor family. It reports child-side FK helper indexes whose
`PRAGMA index_xinfo` key prefix contains the same child columns as the FK but
in the wrong order.

Behavior covered:

- detects child indexes such as `(locale, slug)` for an FK declared on
  `(slug, locale)`;
- keeps the rows diagnostic-only because SQLite does not require child indexes
  for `PRAGMA foreign_key_check`;
- records expected child-column order, actual `index_xinfo` key order,
  unique/partial/auxiliary metadata, current/next source hashes, repaired
  deltas, table-valued `pragma_index_xinfo(...)`, pagination, and stale cursor
  guards;
- preserves the accepted parent-key, action, deferral, collation, partial, and
  `foreign_key_check` behavior from the inherited current-source chain.

Application relevance:

- Copied `wp_options` import tables often add helper indexes after the fact.
  An index on `(locale, slug)` is visible in `PRAGMA index_xinfo`, but it is not
  the useful child-key lookup prefix for an FK on `(slug, locale)`. The smoke
  keeps that wrong-order index visible as diagnostic-only evidence while the
  next source repairs it with `(slug, locale, option_id)`.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next200.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 62 assertions, 0 failures`
  - `49` focused PASS lines
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next200.php --self-test`
  - `application-pragma-index-xinfo-foreignkey-current-source-next200 self-test passed`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Avoids accepted next183 child-index prefix rows, next186 child-index
  collation, next194 partial child-index diagnostics, next193 parent UNIQUE
  order, accepted PRAGMA optimize/index_xinfo/table-info analysis, recursive
  FK catalog output, and the accepted WAL/VFS/B-tree/JSON/SELECT/encoding
  clusters. The new surface is child-side FK helper indexes with the correct
  column set but wrong prefix order.

Dependency closure:

- No new support component is needed. This slice reuses
  `SQLitePragmaSchemaCatalog`, accepted `PRAGMA index_xinfo` row metadata,
  `PRAGMA foreign_key_list` extraction, and current-source cursor primitives.
