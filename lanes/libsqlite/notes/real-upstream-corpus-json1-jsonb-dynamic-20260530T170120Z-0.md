# Real Upstream Corpus: JSON1/JSONB Dynamic

Base accepted HEAD: `45c7c0b7038266bad342ad051199ea41c2a0cb28`

This slice extends the existing focused `SQLiteRealUpstreamJson1JsonbDynamicTest.php`
with real upstream behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`

Covered upstream scenarios:

- `json107-1.1` through `json107-1.8`: legacy UTF-8 BLOB inputs that look like
  text JSON remain accepted by JSON scalar, extract, mutation, type, canonical,
  and operator helpers while still rejecting JSONB-only validity flags.
- `json107-2.1`: `json_tree()` over a text-looking BLOB exposes scalar member
  rows.
- `json109-1.1` through `json109-1.9`: `json_array_insert()` insertion order,
  append pseudo-index, and reverse-index placement.
- `json109-2.1` through `json109-2.8`: array-insert object-path errors,
  indexed missing-path materialization, object-root no-op, and later path
  aborts in a multi-insert.

Focused assertion movement:

- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php`
  reported `1 test files, 283 assertions, 0 failures`.
- After: the same focused command reports `1 test files, 326 assertions, 0 failures`.
- Delta: `+43` focused assertions, all backed by real upstream `json107.test`
  and `json109.test` scenario names.

Dependency closure: no new support component is needed. The slice reuses the
existing JSON canonicalization, validity, mutation, extraction, table, array
insert, JSONB, and SELECT expression helpers.

Non-overlap: the accepted file already covered `json101.test`, `json102.test`,
`json104.test`, `json105.test`, and `jsonb01.test`; this slice adds `json107`
and `json109` coverage only.
