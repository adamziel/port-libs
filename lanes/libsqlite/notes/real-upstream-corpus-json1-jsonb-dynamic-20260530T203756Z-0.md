# real-upstream-corpus-json1-jsonb-dynamic-20260530T203756Z-0

Base accepted HEAD: `80c609b1de0bbfd42f2c3e021c00d868ce6dbc14`.

Added `SQLiteRealUpstreamJson107BlobTextDynamicTest.php` as a real upstream
JSON1/JSONB corpus slice.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`

Ported scenario family:

- `json107-1.1` through `json107-1.1.8`: legacy BLOB input that contains UTF-8
  JSON text is valid for strict/JSON5 text flags but not for JSONB-only flags.
- `json107-1.2.3` through `json107-1.8`: BLOB-text JSON inputs work through
  `json_extract`, `json_insert`, `json_remove`, `json_set`, `json_replace`,
  `json_type`, and `json()`.
- `json107-2.1`: `json_tree()` reads BLOB-text JSON inputs and exposes scalar
  atom rows.

Focused evidence:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson107BlobTextDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson107BlobTextDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson107BlobTextDynamicTest.php
1 test files, 7524 assertions, 0 failures
```

PASS-line movement:

- Focused selected PASS lines: `641`.
- Focused behavior assertions: `7524`.
- Mapped coverage: unchanged; `json107.test` is an already hydrated upstream
  JSON script and this patch adds behavior assertions rather than new
  denominator rows.

Non-overlap:

- This targets the legacy BLOB-as-text JSON behavior from upstream
  `json107.test`.
- It does not repeat accepted JSON table cursor/source/hidden/visible
  constraint work, JSON105 reverse-index mutation, JSON106/108 invariant
  checks, JSON109 array insertion, JSON501/JSON502 path corpus, JSONB01 remove
  corpus, or JSON aggregate/window behavior.
- The assertions use distinct JSON documents and exercise JSON validity flags,
  extraction, canonicalization, inspection, mutation, removal, and tree-row
  traversal against BLOB text inputs.

Dependency closure:

- No new support component is needed. This slice reuses existing native JSON
  helpers and the lane-local PHP TestRunner.

Root harness:

- Not run - isolated micro-slice.
