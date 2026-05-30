# Real upstream JSON105/JSON108 dynamic remainder

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260530T223228Z-0`

Accepted base: `9f789d799d368a95f9314c9ed366646dd5d17143`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test`

Behavior covered:

- `json105.test` reverse `[#-N]` extraction overflow and leading-zero cases.
- `json105.test` ordered multi-path `json_remove()` reverse-index behavior.
- `json105.test` ordered append and reverse-index `json_insert()`,
  `json_set()`, and `json_replace()` behavior.
- `json105.test` malformed reverse path rejection cases.
- `json108.test` `json_pretty()` canonical round-trip invariants for the real
  upstream indent set: SQL NULL/default, empty string, tab, and block-comment
  indent text.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson105Json108DynamicRemainderTest.php`
- Result: `1 test files, 4001 assertions, 0 failures`.
- Focused PASS-line growth: `1001` new TestRunner PASS cases in one new
  behavior test file.

Non-overlap:

This batch does not repeat accepted JSON table cursor/source/hidden/visible
constraint work, JSON109 array insert bulk coverage, JSONB remove expansion,
JSON106/108 broad invariant bulk coverage, or JSON101/102 constructor/extract
coverage. It ports previously omitted `json105.test` remainder sections and a
bounded `json108.test` pretty invariant subset directly into PHP behavior
assertions.

Dependency closure:

No new support component is needed. The batch reuses existing native PHP
JSON1/JSONB helpers: `SQLiteJsonExtract`, `SQLiteJsonRemove`,
`SQLiteJsonMutation`, `SQLiteJsonPretty`, `SQLiteJsonCanonical`, and
`SQLiteJsonB`.
