# Real Upstream Select Core Dynamic 20260531T005230Z-0

Base accepted HEAD: `452a6f6fbb9dca50b40370a18b13b7d77ca03385`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test`

Ported behavior:

- `selectE-1.*`: `EXCEPT` comparison keeps the left SELECT collation while final `ORDER BY ... COLLATE nocase` only affects output ordering.
- `selectE-2.*`: left-projection `COLLATE nocase` changes the `EXCEPT` set comparison while `ORDER BY 1 COLLATE binary` still sorts the surviving rows with binary order.
- `selectF`: `UNION ALL` rows ordered by later result columns remain stable when the sort key includes `NULL` and text values.

Focused coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicCompoundCollationTest.php`.
- PASS-line growth: `+1001` focused TestRunner cases.
- Assertion growth: `5006` assertions in the focused file.
- Mapped denominator growth: none; selected upstream inventory is already complete at `1589 / 1589`.

Non-overlap:

- This slice avoids accepted SELECT GROUP BY/HAVING, JOIN text dispatch, subquery dispatch, expression `ORDER BY`, comma `LIMIT`, JSON table source/constraint/cursor work, WAL/VFS durability work, B-tree page move/freelist/root-collapse work, date/PRAGMA/trigger/UPSERT batches, and source-neutral API cleanup.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicCompoundCollationTest.php`
  - `1 test files, 5006 assertions, 0 failures`
  - `1001` PASS lines

Dependency closure:

- No new support component needed; this reuses existing `SQLiteSelectSql`, `SQLiteSelectCompound`, and `SQLiteSelectResult` compound SELECT/collation/order execution.
