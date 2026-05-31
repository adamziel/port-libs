# real-upstream-corpus-json1-jsonb-dynamic-20260531T073858Z-0

Added `SQLiteRealUpstreamJsonb01OrderedRemoveYieldDynamicTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`
- Ported sections: `jsonb01-1.2.1` through `jsonb01-1.2.18` JSONB
  `jsonb_remove()` object-member, array-index, append-token no-op, reverse
  index, missing-path no-op, and ordered path deletion behavior.

Focused behavior:

- 360 distinct TestRunner PASS cases over deterministic application documents.
- 9364 focused assertions.
- Each case checks text JSON, legacy text-looking BLOB, JSONB input, `json_remove`,
  `jsonb_remove`, `SQLiteSelectExpression` dispatch, strict JSONB validity, JSON
  canonical parity, preserved extraction values, and `json_tree()` scalar row
  parity after left-to-right ordered deletion.

Non-overlap:

- This does not repeat accepted JSON table cursor/source wiring, hidden/visible
  constraint pushdown, JSON aggregate/window behavior, JSON104 merge-patch,
  JSON105 append-only dynamic coverage, JSON106/108 invariants, JSON109 array
  insert, JSONB malformed rejection, or the existing smaller JSONB remove files.
  It extends the real `jsonb01.test` removal corpus with ordered multi-path
  behavior and SELECT JSON function dispatch over larger dynamic documents.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP JSONB,
  JSON canonicalization, JSON remove, JSON tree, JSON validity, and SELECT
  expression dispatch helpers.

Verification:

- Initial red check: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonb01OrderedRemoveYieldDynamicTest.php`
  failed because the new test expected raw text from `SQLiteSelectExpression`
  while the existing native dispatcher correctly returned `SQLiteJsonSubtypeValue`
  for `json_remove()`.
- Fixed the test to unwrap the subtype and reran:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonb01OrderedRemoveYieldDynamicTest.php`
  -> `1 test files, 9364 assertions, 0 failures`.
