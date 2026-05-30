# real-upstream-corpus-json1-jsonb-dynamic-20260530T214851Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`

Ported behavior cluster:

- `json104-100`, `json104-110`, `json104-210`, `json104-221`, `json104-222`,
  `json104-300`, `json104-301`, `json104-302`, `json104-306`,
  `json104-308`, `json104-309`, `json104-311`, `json104-312`, and
  `json104-320`.
- Adds `SQLiteRealUpstreamJson104PatchLargeCorpusTest.php`, a real-upstream
  RFC-7396 merge-patch corpus that expands the upstream patch cases across
  560 deterministic wrapped documents.
- Exercises `json_patch()` and `jsonb_patch()` parity, canonical result
  shape, object-member deletion, array/scalar replacement, duplicate-key
  last-wins behavior, SQL NULL propagation, JSON validity, and path extraction
  from patched text and JSONB values.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson104PatchLargeCorpusTest.php`
- Result: `1 test files, 15925 assertions, 0 failures`
- PASS lines: 4 focused TestRunner cases.

Non-overlap:

- This is not metadata admission, not generated fake upstream script ids, and
  not a domain-specific scenario.
- It does not repeat accepted JSON table cursor/source/hidden/visible
  constraint work, JSON501/JSON502 JSON5 behavior, JSON105 dynamic path
  mutation, JSON106/JSON108 invariants, JSON107 legacy blob-text,
  JSON109 array-insert, or JSON103 aggregate/window coverage.
- The new assertion growth comes from upstream `json104.test` RFC-7396
  merge-patch behavior and JSONB patch parity.

Dependency closure:

- No new support component is needed. The slice reuses existing native JSON,
  JSONB, JSON path extraction, canonicalization, validity, and patch helpers.
