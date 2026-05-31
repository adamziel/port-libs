# real-upstream-corpus-select-core-dynamic-selectD-parenthesized-20260531T075034Z

Slice: `real-upstream-corpus-select-core-dynamic-20260531T075034Z-0`

Base accepted HEAD: `9d7a6158784515939dbe96138a460121fe325c71`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test`
- Ported scenarios: `selectD-$i.1`, `selectD-$i.2.2`, `selectD-$i.2.3`, `selectD-$i.2.7`, and `selectD-$i.7`.

Behavior covered:

- Parenthesized comma `FROM` source name resolution.
- Nested parenthesized `JOIN ... ON` source resolution.
- Qualified table projection and table-star projection through nested joins.
- Table alias resolution through nested parenthesized joins.
- Parenthesized `LEFT JOIN` null-extension projection.

Focused growth:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicSelectDParenthesizedTest.php`.
- Focused run produced `1 test files, 5504 assertions, 0 failures`.
- TestRunner PASS-line growth is `1101` distinct cases.

Non-overlap:

- Existing accepted SELECT corpus coverage in this worktree includes select2/select5/select6/selectC/select8/select1/select3/select4/select7/select9/selectA/selectB/selectG/selectH and recent core/dynamic SELECT batches.
- This slice is intentionally limited to `selectD.test` parenthesized FROM/JOIN name-resolution behavior, which was not listed in `lane-status.json` and is not a repeat of accepted GROUP BY, expression ORDER BY, subquery, JSON table source, comma-LIMIT, or join-text dispatch slices.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicSelectDParenthesizedTest.php`
  - Result: `1 test files, 5504 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. The slice reuses the existing `SQLiteSelectSql` row-array executor and hydrated upstream SQLite Tcl corpus as oracle/source material.
