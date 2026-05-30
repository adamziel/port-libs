# JSON Scalar Regression Corpus Next9

This slice adds a focused JSON1 scalar regression corpus for upstream-style
`json()`, `jsonb()`, `json_quote()`, `json_patch()`, `jsonb_patch()`, and
`json_pretty()` behavior. It avoids the accepted JSON table hidden/visible
constraint, cursor, SELECT-source, host-join, and malformed planner clusters.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonScalarRegressionCorpusTest.php`
  - Result: `1 test files, 55 assertions, 0 failures`
  - PASS-line delta: `+55`
- `php lanes/libsqlite/examples/application-json-scalar-regression.php`
  - Result: emitted `plugin_settings` JSON with canonical payload, patched
    payload, quoted option name, and `pretty_lines: 11`.
- `php -l lanes/libsqlite/tests/SQLiteJsonScalarRegressionCorpusTest.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/examples/application-json-scalar-regression.php`
  - Result: no syntax errors.
- `git diff --check -- lanes/libsqlite`
  - Result: no whitespace errors.

Dependency closure: no new support component is needed; the corpus reuses the
existing native PHP JSON5 parser, JSONB codec, JSON subtype wrapper, and JSON
scalar helper classes.

Next task: continue JSON work only on non-overlapping planner/VDBE behavior,
dynamic JSON table joins, or malformed JSONB planner edges.
