# real-upstream-corpus-select-core-dynamic-20260531T000636Z-0

Added `SQLiteRealUpstreamSelect6DerivedAliasAggregateDynamicTest.php` as an additive real upstream SELECT core corpus batch.

Real upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test`
- Ported upstream sections: `select6-1.7`, `select6-2.7`, `select6-2.9`, `select6-3.1`, `select6-3.10`, `select6-3.14`, and `select6-4.1`.

Behavior covered:

- Derived-table bracketed aggregate result-column lookup, including `[count(*)]`, `[max(x)]`, and `[max(a)]`.
- INTEGER PRIMARY KEY copy shape from `select6-2.*` through generic `app_t2` rows.
- Alias `GROUP BY` references in derived aggregate subqueries.
- Nested derived-table projection preservation.
- Quoted result aliases such as `AS 'a'`, matching upstream `select6-3.10` and `select6-4.1`.
- Outer filtering over derived expression aliases.

Implementation delta:

- `SQLiteSelectSql::unquoteIdentifier()` now accepts SQLite-style single-quoted, backtick-quoted, and bracket-quoted identifiers in addition to double-quoted identifiers. This is required for upstream `select6.test` result aliases such as `AS 'a'`.

Focused count:

- `SQLiteRealUpstreamSelect6DerivedAliasAggregateDynamicTest.php`: `1` file / `31327` assertions / `0` failures / `1308` PASS lines.

Non-overlap:

- This slice does not repeat existing accepted SELECT core batches for `select1` through `select5`, prior `select6-1.1` through `select6-1.8` derived-count/group-join coverage, `select7`, `select8`, `select9`, `selectA`, `selectB`, `selectC`, `selectD`, `selectE`, `selectF`, `selectG`, `selectH`, expression `ORDER BY`, grouped SELECT text, JSON table source/cursor/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because `select6.test` is already part of the hydrated upstream manifest inventory.

Exclusions:

- Upstream `select6-1.9` remains a separate parser/executor gap for bracketed expression-column names such as `b.[min(x)+y]`.
- Upstream `select6-3.3`, `select6-3.6`, `select6-3.7`, and `select6-3.12` expose broader multi-aggregate implicit-group and HAVING-alias behavior and are not claimed by this handoff.

Dependency closure:

- No new support component is needed. This reuses the existing bounded `SQLiteSelectSql` SELECT text executor and the hydrated upstream SQLite test checkout.
