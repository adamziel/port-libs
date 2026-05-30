# real-upstream-corpus-json1-jsonb-dynamic-20260530T221741Z-0

- Base accepted HEAD: `661e026d244a8143c42a9b42e699177ff26e29f3`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`.
- Ported behavior cluster: `json101.test` sections `3.1` through `3.5`, covering JSON value-argument propagation through `json_set()`, `json_replace()`, `json_insert()`, and the `jsonb_*` equivalents.
- New focused PHP test: `lanes/libsqlite/tests/SQLiteRealUpstreamJson101ExpressionValuePropagationDynamicTest.php`.
- Focused coverage: 96 dynamic documents x 4 JSON paths x 4 replacement value classes x 3 mutation functions x 2 source/function families = 9,216 behavior PASS cases, plus one source ownership PASS case. The test run produced 9,217 PASS lines and 27,651 assertions.
- Exercised behavior: SQL expression dispatch preserves the upstream distinction between plain SQL text that looks like JSON, JSON subtype arguments, and JSONB blob arguments; `json_*` expression results retain subtype payloads and `jsonb_*` expression results round-trip as JSONB blobs; existing and missing paths follow insert/replace/set rules.
- Non-overlap: this does not repeat JSON table cursor/source wiring, hidden/visible constraint pushdown, JSON aggregate/window behavior, JSON105 reverse/append path batches, JSON107 legacy BLOB text, JSON109 array insert, JSON501/502 JSON5 lexical coverage, or metadata-only runner rows. It specifically widens `json101` value-propagation coverage through the expression evaluator rather than direct helper-only calls.
- Dependency closure: no new support component is needed; this reuses existing native JSON text, JSON subtype, JSONB, path, mutation, inspection, extract, and `SQLiteSelectExpression` dispatch components.
- Verification:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101ExpressionValuePropagationDynamicTest.php` => no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101ExpressionValuePropagationDynamicTest.php` => `1 test files, 27651 assertions, 0 failures`.
