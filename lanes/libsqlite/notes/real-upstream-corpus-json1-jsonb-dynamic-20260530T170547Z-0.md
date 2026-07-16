# Real Upstream JSON1/JSONB Dynamic Corpus Slice

Session: `port-dev-sqlite-yield-dyn-real-json-20260530T170547Z`

Base: `45c7c0b7038266bad342ad051199ea41c2a0cb28`

Upstream source files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`

Ported upstream behavior:

- `json101-9.1` through `json101-9.4` and `json101-20.1`: `json_quote()` scalar, NULL, and infinity output.
- `json101-10.1` through `json101-10.95` plus `json101-10.86.0` through `json101-10.86.6`: strict `json_valid()` backslash escape acceptance and rejection.
- `json101-11.0` through `json101-11.3`: JSON nesting depth validity boundaries.
- `json101-12.110`, `12.110b`, `12.120`, and `12.120b`: quoted object-member paths containing dots for `json_remove()` and `json_extract()`.
- `json101-14.100` through `14.170`: scalar `json_each()` and `json_tree()` fullkey root behavior.
- `json101-15.100` through `15.130`: `json_each()` object rows through direct and argument-vector dispatch.
- `json101-18.2` through `18.5`: empty quoted object-member paths and malformed bare-dot path rejection.
- `json101-21.2` through `21.25`: SQL NULL behavior for JSON scalar, mutation, patch, table, and operator paths.
- Existing `jsonb01.test` remove/operator coverage remains in the same focused corpus file and is rerun with this slice.

Focused assertion delta:

- Before this patch: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php` => `1 test files, 529 assertions, 0 failures`.
- After this patch: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php` => `1 test files, 1035 assertions, 0 failures`.
- Honest focused growth: `+506` assertions/PASS cases.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php` passed.
- The older domain-specific API guard path named by the worker prompt was not present in this isolated worktree.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php` passed.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:

No new support component is required. The slice reuses existing native PHP JSON1/JSONB helpers, JSON table helpers, `SQLiteSelectExpression`, and the hydrated upstream SQLite test corpus.
