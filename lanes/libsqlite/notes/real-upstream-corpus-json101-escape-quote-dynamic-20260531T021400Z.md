# real-upstream-corpus-json101-escape-quote-dynamic-20260531T021400Z

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T021400Z-0`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported sections: `json101-7.1` through `json101-7.7`, `json101-8.1`
  through `json101-8.4`, `json101-9.1` through `json101-9.7`,
  `json101-10.1` through `json101-10.95`, and `json101-16.10` through
  `json101-16.30`.

## Behavior

- Fixed strict JSON text validation so non-standard backslash escapes such as
  `\!`, `\0`, `\A`, and `\~` are rejected before PHP's permissive
  `json_decode()` can accept them.
- Added a dynamic upstream corpus around JSON whitespace, string escape,
  control-character quoting, `json_quote()` SQL value rendering, BLOB rejection,
  JSONB array constructor parity, and surrogate-pair extraction behavior.
- Focused growth: `2769` distinct TestRunner PASS cases and `3551` assertions
  in the new focused file.

## Non-Overlap

This does not repeat accepted JSON table cursor/source/hidden/visible
constraint work, JSON101 constructor/no-edit/tree-invariant/edit-cache
coverage, JSON102 path/mutation/operator coverage, JSON103 aggregate/window
coverage, JSON104/105/106/107/108/109 dynamic coverage, JSON501/502 escaped
path/JSON5 coverage, or JSONB remove coverage. The new source behavior is the
strict JSON string escape check needed for upstream `json101-10.*` parity.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101EscapeQuoteDynamicMegaTest.php`
  - `1 test files, 3551 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicNoEditCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101EscapeValidityDynamicTest.php`
  - `3 test files, 19464 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteJsonValidity.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101EscapeQuoteDynamicMegaTest.php`
  - no syntax errors

## Dependency Closure

No new support component is needed. This reuses the existing native JSON
validity, JSONB, constructor, quote, extract, and canonicalization helpers.
