# real-upstream-corpus-select-core-dynamic-20260601T041541Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260601T041541Z-0`

Added `SQLiteRealUpstreamSelectEOrderCollationDynamic20260601T041541ZTest.php`, a focused real upstream SELECT-core dynamic corpus batch backed by:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test`
- `selectE-1.1`: `EXCEPT` membership remains binary while `ORDER BY a COLLATE nocase` only controls final row order.
- `selectE-1.2`: `ORDER BY a COLLATE binary` preserves the binary final sort order.
- `selectE-1.3`: bare `ORDER BY a` matches the binary final sort order for the same `EXCEPT` result.

The new file adds 1,002 focused TestRunner PASS cases and 14,010 behavior assertions. Each dynamic case verifies all three upstream order-collation variants, including the key regression guard that a lowercase right-side row such as `def_0001` does not remove a binary-distinct uppercase left-side row such as `DEF_0001` just because the final `ORDER BY` uses `COLLATE nocase`.

Non-overlap:

- Existing selectE coverage owns `selectE-1.0`, `selectE-2.1/2.2`, and `selectE-3.1`.
- This slice owns only `selectE-1.1` through `selectE-1.3`.
- It avoids grouped SELECT text, expression `ORDER BY`, JSON table SELECT sources, WAL/VFS/B-tree clusters, source-neutral cleanup, and denominator metadata movement.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectEOrderCollationDynamic20260601T041541ZTest.php`
  - `1 test files, 14010 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectEOrderCollationDynamic20260601T041541ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectESelectFDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectECompoundOrderErrorDynamicTest.php`
  - `3 test files, 22018 assertions, 0 failures`

Dependency closure: no new support component is needed; the batch reuses the existing native `SQLiteSelectSql` compound SELECT executor and hydrated upstream SQLite `selectE.test` source truth.

Root harness: not run - isolated micro-slice.
