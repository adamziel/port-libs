# pragma-foreignkey-index-rootpage-current-source-next

Slice: `pragma-foreignkey-index-rootpage-current-source-next`.

This patch adds `SQLitePragmaForeignKeyIndexRootpageCurrentSourceNext`, a
current/next cursor for copied WordPress database preflight that combines a
single `PRAGMA index_xinfo(...)` stream with foreign-key violation rows enriched
with child/parent rootpage and pointer-map status. It reuses the existing
single-source next125 primitive and adds the missing current-to-next admission
contract: current and next source hashes, resumable pagination, repair deltas,
stale cursor rejection, table-valued index pragma admission, and next-state
blocker reporting.

WordPress path: copied `wp_options` import preflight can now page the
`wp_options_name` index metadata together with `foreign_key_check` rootpage
blockers, then verify that a repaired next image clears the largest-root,
pointer-map, and missing parent-key rows before continuing the import.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIndexRootpageCurrentSourceNextTest.php
# 1 test files, 66 assertions, 0 failures
# 51 PASS lines

php lanes/libsqlite/examples/wordpress-pragma-foreignkey-index-rootpage-current-source-next.php --self-test
# wordpress-pragma-foreignkey-index-rootpage-current-source-next self-test passed
```

Dependency closure: no new support component is needed. The slice reuses native
PHP schema catalog, PRAGMA `index_xinfo`, rootpage integrity, pointer-map, and
foreign-key check primitives.

Non-overlap: avoids accepted table-level index-list plus quickcheck/FK next141,
single-source FK/index rootpage next125, FK root integrity next117/120/140,
table-level index integrity cursor next133, quickcheck/index/FK next138, and
accepted PRAGMA quickcheck/FK rootpage next132/136 surfaces. The new behavior is
single-index FK/rootpage current-to-next repair admission without quickcheck or
table-level index enumeration.
