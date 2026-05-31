# real-upstream-corpus-select-core-dynamic-20260531T051949Z-0

Added `SQLiteRealUpstreamSelectDParenthesizedJoinDynamicCorpusTest.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test`
- Ported scenario family: `selectD-1.1`, `selectD-1.2.1`, `selectD-1.2.2`, `selectD-1.2.3`, `selectD-1.2.4`, `selectD-1.2.5`, `selectD-1.2.7`, `selectD-1.5`, `selectD-1.6`, and `selectD-1.7` parenthesized SELECT FROM name-resolution behavior.

Focused behavior:

- 240 dynamic seeds for parenthesized comma FROM clauses with qualified `WHERE` name resolution.
- 240 dynamic seeds for nested parenthesized `JOIN ... ON` chains.
- 240 dynamic seeds for qualified table-star projection through nested joins.
- 240 dynamic seeds for schema-qualified alias joins over `main.t4` and `aux1.t4` style table names.
- 240 dynamic seeds for parenthesized left-join null-extension projection.
- One source/follow-up note case.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDParenthesizedJoinDynamicCorpusTest.php`
- Result: `1 test files, 4804 assertions, 0 failures`
- PASS-line growth: `+1201` focused TestRunner cases.

Non-overlap:

- This ports `selectD.test` parenthesized join/name-resolution behavior.
- It does not repeat accepted `selectC.test` alias visibility, `select1` through `select9` projection/grouping/compound batches, `selectA`/`selectB` large dynamic batches, `selectE`/`selectF` compound collation/copy batches, expression `ORDER BY`, grouped SELECT text, SELECT subqueries, JSON table source/cursor/constraint work, or metadata-only runner rows.
- The upstream `USING` sub-branch in `selectD.test` remains a follow-up behavior gap; this handoff intentionally owns only the ON/qualified-projection branch that passes on the current native executor.

Dependency closure:

- No new support component is needed. The test reuses the existing `SQLiteSelectSql` native executor and the hydrated upstream SQLite test corpus as source truth.
- Mapped denominator remains unchanged because the upstream manifest is already complete; this should count as focused PHP PASS-line/assertion growth only.
