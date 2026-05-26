# Libsqlite Rework Closure Notes

## 2026-05-26 JSON Dispatch Rework Markers

This isolated closure slice checked the outstanding handoff rework markers:

- `port-libsqlite-20260525T071150Z.needs-lane-rework.md`
- `port-libsqlite-20260525T100407Z.needs-lane-rework.md`
- `port-libsqlite-current-rebase-20260525T054020Z-02383337bcf4.needs-lane-rework.md`
- `port-libsqlite-finisher-20260525T092629Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T082910Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T083258Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T093834Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T100451Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T105622Z.needs-lane-rework.md`

Current accepted lane files already contain the requested rebased behavior:

- `SQLiteJsonCanonical::jsonSqlFunction()` and `jsonSqlFunctionArguments()` cover case-insensitive `json`/`jsonb` dispatch, SQL NULL propagation, JSON5 text, text BLOB fallback, JSONB passthrough, and malformed input rejection.
- `SQLiteJsonPretty::jsonPrettySqlFunction()` and `jsonPrettySqlFunctionArguments()` cover case-insensitive `json_pretty` dispatch, one-or-two argument SQL arity, scalar SQL coercion including booleans and whole REAL values, JSON subtype input, text/JSONB BLOB input, custom indentation, SQL NULL propagation, and invalid function-name rejection.
- `SQLiteJsonExtract::extractSqlFunction()` and `extractJsonArgumentSqlFunction()` preserve the accepted `json_extract`/`jsonb_extract` SQL result typing and constructor-argument subtype propagation.
- `examples/wordpress-json-canonical-option-preflight.php`, `examples/wordpress-json-pretty-option-review.php`, and `examples/wordpress-json-extract-subtype-option-diagnostics.php` retain the WordPress-visible smoke paths referenced by the stale rework markers.

The only additive behavior in this closure patch is a focused assertion that
direct `JSON_PRETTY` dispatch and argument-vector `json_pretty` dispatch remain
equivalent for a JSONB BLOB input with a BLOB custom indent. That guards the
conflict-prone rework boundary without changing manifest denominators.

Focused verification for this closure slice:

```sh
php -l lanes/libsqlite/src/SQLiteJsonPretty.php
php -l lanes/libsqlite/src/SQLiteJsonCanonical.php
php -l lanes/libsqlite/src/SQLiteJsonExtract.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-json-pretty-option-review.php
php -l lanes/libsqlite/examples/wordpress-json-canonical-option-preflight.php
php -l lanes/libsqlite/examples/wordpress-json-extract-subtype-option-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-json-pretty-option-review.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
JSON canonicalization, JSON5 parsing, JSONB encoding/decoding, JSON subtype
wrappers, BLOB value wrappers, and SQL scalar coercion helpers without
activating shared support-library work.
