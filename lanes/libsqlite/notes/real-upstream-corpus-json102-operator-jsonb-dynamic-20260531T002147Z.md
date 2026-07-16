# real-upstream-corpus-json1-jsonb-dynamic-20260531T002147Z-0

- Base accepted HEAD: `aab498f11db56174605363e36ca7a662eb3a6384`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`
- Ported behavior cluster:
  - `json102-1600`: `->` and `->>` object member parity for SQL null, integer, real, text, array, object, and missing members.
  - `json102-1610` / `json102-1620`: integer RHS array-index parity and JSON subtype/extract parity.
  - `json102-1800` through `json102-1831`: numeric-looking string RHS remains a member lookup, while integer RHS remains an array-index lookup.
  - `jsonb01-2.0`: malformed JSONB operator source aborts before yielding a value.
- Implementation fixes:
  - `SQLiteSelectSql` no longer treats the `>` byte inside `->` as a top-level comparison operator, allowing parser-level JSON operator dispatch to handle the expression.
  - `SQLiteSelectExpression` accepts JSON subtype RHS values as scalar path text, preserving the existing null-result behavior for object-shaped subtype path operands.
  - `SQLiteSelectQuery` avoids eager projection of SELECT aliases not referenced by `WHERE`, and can apply unordered `LIMIT` before projection so later malformed JSONB rows are not evaluated when an earlier row satisfies the bounded query.
- Focused growth: `SQLiteRealUpstreamJson102OperatorJsonbDynamicTest.php` adds 1,046 real upstream TestRunner PASS cases and 8,178 behavior assertions.
- Non-overlap: this does not repeat JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window work, JSON mutation path work, or the earlier JSONB malformed current-source plan. It specifically covers parser-level JSON operator dispatch against text and JSONB sources from upstream `json102.test`.
- Dependency closure: no new support component is needed; the slice reuses existing `SQLiteSelectSql`, `SQLiteJsonPath`, `SQLiteJsonExtract`, `SQLiteJsonInspection`, and `SQLiteJsonB` components.
