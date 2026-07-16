# PRAGMA index_list rootpage integrity current-source next139

This slice adds table-level `PRAGMA index_list(...)` current-source pagination
for Application repair/import preflights. It combines index-list metadata with
sqlite_schema rootpage integrity rows for the target table and every listed
index, so callers can reject stale or corrupt index metadata before relying on
unique, partial, or autoindex catalog rows.

Focused coverage:

- direct and table-valued `PRAGMA index_list` forms;
- temp/main/archive schema resolution and catalog-source hashing;
- unique, partial, and `sqlite_autoindex_*` origin metadata;
- table root plus every listed index rootpage;
- pointer-map mismatch, wrong b-tree page type, and beyond-image rootpage
  blockers;
- source cursor pagination and stale database/catalog/SQL/integrity/offset
  rejection.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexListRootpageIntegrityCurrentSourceNextTest.php
```

Result: `1 test files, 80 assertions, 0 failures` with `74` PASS lines.

Application smoke:

```sh
php lanes/libsqlite/examples/application-pragma-index-list-rootpage-integrity-current-source-next.php --self-test
```

Result: `application-pragma-index-list-rootpage-integrity-current-source-next self-test passed`.

Non-overlap: avoids accepted next124 single-index `index_xinfo` plus
`integrity_check`, next135 single-index `quick_check` escalation, next136
foreign-key quickcheck/root behavior, PRAGMA optimize/table info/index_xinfo
analysis, and accepted B-tree/WAL/VFS/JSON/encoding clusters. The new surface
is table-level index-list metadata gated by all listed index rootpages under a
current-source cursor.

Dependency closure: no new support component is needed; this reuses existing
native PHP schema catalog, SQLite database image, pointer-map, and PRAGMA
rootpage integrity helpers already present under `lanes/libsqlite/src`.
