real-upstream-corpus-json1-jsonb-dynamic-20260531T070157Z-0
================================================================

Base accepted HEAD: b596d6a43afd4ccaf50904f879de33fed9b5b7f3.

Scope:
- Added `SQLiteRealUpstreamJsonbJson109DynamicCorpusTest.php`.
- Source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`
    `jsonb01-1.2.1` through `jsonb01-1.2.18` JSONB remove path matrix and
    `jsonb01-2.0` malformed JSONB operator boundary.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`
    `json107-1.1` through `json107-1.8` legacy text-looking BLOB JSON behavior.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`
    `json109-1.1` through `json109-1.9` array insertion placement and
    `json109-2.1` through `json109-2.8` object-path insertion/error
    boundaries.

Behavior:
- `SQLiteJsonB` now maps malformed `json_array_insert()` mutation paths through
  the array-insert path error class, matching SQLite's json109 path-boundary
  behavior instead of leaking a generic unterminated-index parser diagnostic.
- New focused coverage checks JSONB remove text/JSONB parity, legacy text-BLOB
  compatibility, `json_array_insert()` text/JSONB parity, path error
  boundaries, and 120 dynamic insertion variants.

Focused evidence:
- Red-first command before the source fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonbJson109DynamicCorpusTest.php`
  -> `1 test files, 753 assertions, 1 failures`.
- After fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonbJson109DynamicCorpusTest.php`
  -> `1 test files, 756 assertions, 0 failures`.
- Related guard:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonDynamicPathCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php`
  -> `2 test files, 1438 assertions, 0 failures`.

Dependency closure:
- No new support component is needed. The slice reuses the existing native
  `SQLiteJsonB`, `SQLiteJsonArrayInsert`, `SQLiteJsonRemove`,
  `SQLiteJsonValidity`, `SQLiteJsonTree`, and `SQLiteSelectExpression` helpers.

Non-overlap:
- This does not touch accepted JSON table cursor/source/hidden/visible
  constraint work, JSON host joins, JSON102 multipath, JSON106 invariant
  corpus, JSON aggregate/window behavior, or JSONB generated-index planning.
