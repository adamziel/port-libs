# real-upstream-corpus-select-core-dynamic-20260531T055220Z-0

- Base accepted HEAD: `db171f640e25dd929585c8e1b7a1c804219fdfee`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test`.
- Added focused PHP coverage to `SQLiteRealUpstreamSelectCoreDynamicTest.php` for `select9.test` compound SELECT behavior with `UNION ALL`, `UNION`, `INTERSECT`, and `EXCEPT`, final `ORDER BY` direction, and `LIMIT`/`OFFSET` slicing.
- Non-overlap: this extends the existing real select core dynamic corpus with `select9.test` compound ORDER BY/LIMIT/OFFSET cases; it does not touch accepted SELECT JOIN text, GROUP BY text, expression ORDER BY, JSON table SELECT sources, or older selectC/select8 dynamic blocks.
- Focused movement: before this slice the focused file passed at `1 test files, 17450 assertions, 0 failures` with 1,616 PASS cases. After this slice it passes at `1 test files, 41938 assertions, 0 failures` with 2,816 PASS cases. Net focused movement is `+1200` TestRunner PASS cases and `+24488` assertions.
- Dependency closure: no new support component is needed; the slice reuses the existing `SQLiteSelectSql` compound SELECT executor and focused TestRunner.
- Root harness: not run; isolated micro-slice.

Verification:

```sh
php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php
php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php
git diff --check -- lanes/libsqlite
```
