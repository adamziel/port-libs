# real-upstream-corpus-select-core-dynamic-20260531T070044Z-0

Extended `SQLiteRealUpstreamSelect4CompoundDynamicTest.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`
- `select4-1.1c` and `select4-1.2`: `UNION ALL` compound rows and compound `IN` subquery membership.
- `select4-2.1` and `select4-2.2`: distinct `UNION` rows and compound `IN` subquery membership.
- `select4-3.1.1` and `select4-3.2`: `EXCEPT` rows and compound `IN` subquery membership.
- `select4-4.1.1` and `select4-4.2`: `INTERSECT` rows and compound `IN` subquery membership.

Focused assertion/PASS movement:

- Reworked the existing accepted `select4.test` compound dynamic file from `1,006` focused PASS cases into `1,101` focused PASS cases while preserving ordered `ASC`/`DESC` compound coverage and adding compound-subquery membership behavior.
- Added `1,100` dynamic cases plus one upstream source citation case in the final focused file.
- Honest PASS-line movement for the modified focused file is `+95` PASS cases; assertion movement is substantially larger because each case now checks seven upstream SELECT behaviors plus ordering/digest guards.
- Each dynamic case executes seven SELECT statements through `SQLiteSelectSql`: ordered `UNION ALL`, `UNION ALL` membership subquery, ordered `UNION`, ordered `EXCEPT`, `EXCEPT` membership subquery, ordered `INTERSECT`, and `INTERSECT` membership subquery.
- Focused run: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundDynamicTest.php`
- Result: `1 test files, 25305 assertions, 0 failures` with `1,101` PASS lines.

Non-overlap:

- This owns the residual `select4.test` compound set-operator and compound subquery membership cluster over the upstream log-table shape.
- It does not repeat accepted `select1` projection/wildcard batches, `select2` WHERE expression/range batches, `select3` aggregate batches, `select5` aggregate/limit batches, `select6` derived-table batches, `selectA`/`selectB` compound-collation/subquery batches, `selectC` alias/distinct-derived batches, `selectD` parenthesized/derived join batches, `selectE`/`selectF` compound collation/copy batches, `selectG` VALUES batches, `selectH` omit-unused/wide-compound batches, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because `select4.test` is already part of the hydrated upstream manifest coverage.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP `SQLiteSelectSql` compound SELECT, set-operator, ordering, and subquery membership behavior.
