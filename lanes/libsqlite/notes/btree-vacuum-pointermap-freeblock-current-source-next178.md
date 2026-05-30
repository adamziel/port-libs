# B-tree Vacuum Pointer-Map Freeblock Current Source Next178

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext178Plan`, a final publication-receipt layer over the accepted next175 current-source admission rows.

Behavior covered:

- publishes only materialized leaf/freeblock and replacement overflow pages to the next current source;
- keeps the truncated auto-vacuum tail page fenced with a concrete block reason;
- records required freeblock, pointer-map, and overflow next-pointer receipts for the handoff;
- preserves the next175 admission error path for undersized replacement payloads.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext178Test.php` -> `1 test files, 591 assertions, 0 failures` with 91 PASS lines.
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next178.php --self-test` -> self-test passed.

Dashboard delta: expected `phpPass` movement is `83183 -> 83274` after clean integration. Mapped upstream coverage remains conservative at `613 / 1589`.

Dependency closure: no new support component needed. The slice reuses native next175 current-source admission rows, secure-delete leaf freeblock receipts, overflow next-pointer fencing, and auto-vacuum pointer-map metadata.

Non-overlap: this adds final publication receipts after next175 admission. It does not repeat next175 admission fencing, next173 transition rows, next166 write admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization.
