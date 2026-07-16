# PRAGMA integrity current/next64

Slice: `pragma-integrity-current-next64`.

Adds `SQLitePragmaIntegrityCurrentNextYield`, a resumable current/next64 view
over `PRAGMA integrity_check` / `quick_check` rows. The helper preserves every
integrity message, classifies the source (`header`, `freelist`, `schema_root`,
`pointer_map`, `btree`, or `integrity`), annotates page and pointer-map page
metadata when available, and appends `foreign_key_check` rows after the
integrity stream for copied Application schema checks.

Focused verification:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS pragma integrity current next64 page0 status
PASS pragma integrity current next64 page0 offset
PASS pragma integrity current next64 page0 limit current next64
...
PASS pragma integrity current next64 rejects negative offset
PASS pragma integrity current next64 rejects zero limit
PASS pragma integrity current next64 propagates pragma parser guard

1 test files, 78 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-pragma-integrity-current-next64.php
```

Dependency closure: no new support component is needed. This reuses the
lane-local integrity checker, pointer-map metadata, and foreign-key integrity
helpers.

Non-overlap: this avoids accepted pointer-map/freelist-only pagination,
combined pointer-map/foreign-key pagination, autoindex/root/index_xinfo
integrity yields, deep integrity primitive checks, VFS writer/sync/lock/
rollback clusters, WAL checkpoint/savepoint byte truncation, B-tree page
move/root-collapse/overflow-release clusters, JSON table source/cursor/
constraint work, SELECT SQL text/JOIN/GROUP/subquery/ORDER/LIMIT clusters, and
Unicode GLOB work.
