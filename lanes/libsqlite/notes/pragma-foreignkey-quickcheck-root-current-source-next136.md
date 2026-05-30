# PRAGMA foreignkey quickcheck root current-source next136

Slice: `pragma-foreignkey-quickcheck-root-current-source-next136`.

Added `SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::currentNextPage()`
for current-vs-next repair pagination across scoped `PRAGMA quick_check(...)`
root diagnostics and `PRAGMA foreign_key_check(...)` rows. The new behavior
keeps separate current and next source hashes, validates stale cursors against
the combined source, paginates current rows before next rows, and reports
repair deltas plus `next_state` blockers.

Application smoke:

```bash
$ php lanes/libsqlite/examples/application-pragma-foreignkey-quickcheck-root-current-source-next136.php
{
    "scenario": "copied wp_options PRAGMA quick_check root and foreign_key_check current/next repair",
    "status": "ok",
    "current_root_blockers": 2,
    "current_foreign_key_blockers": 1,
    "next_ready": true,
    "cleared": true,
    "delta_total": -3
}
```

Focused verification:

```bash
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyQuickcheckRootCurrentSourceNext136Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 64 assertions, 0 failures
```

PASS-line delta: `+54` focused PASS lines.

Non-overlap: this avoids accepted PRAGMA index_xinfo/integrity root pagination,
foreign_key_check table-valued resolution, integrity/FK pointer-map pagination,
quickcheck/stat/FK current-source pagination, partial-root next128, recursive
FK catalog output, and batch133 PRAGMA index integrity cursor behavior. This
slice is specifically the current-to-next repair boundary where `quick_check`
root blockers and FK blockers clear together before the next page cursor is
accepted.

Dependency closure: no new support component is needed; the slice reuses
existing schema catalog, root integrity analysis, quick_check, and
foreign_key_check primitives.
