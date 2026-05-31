# real-upstream-corpus-json1-jsonb-dynamic-20260531T041903Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- Ported upstream sections: `json501-1.*` through `json501-14.*` JSON5 lexical behavior.

Behavior covered:

- JSON5 object IdentifierName keys, including dollar, underscore, alphanumeric, and non-ASCII identifiers.
- Object and array trailing comma admission and double-comma rejection.
- Single-quoted strings, escaped line continuations, JSON5 character escapes, hex escapes, and raw control characters.
- Hexadecimal numbers, explicit plus signs, leading/trailing decimal point numbers, exponent forms, Infinity, negative Infinity, and NaN.
- Single-line and block comments plus JSON5 extended whitespace.
- Strict `json_valid()` rejection versus JSON5-flag admission, `json_error_position()` success/failure signaling, canonical `json()` output, `json_extract()` scalar extraction, and `jsonb()` strict/superficial validity round trips.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJson501Json5DynamicCorpusTest.php`
- Result: `1 test files, 710 assertions, 0 failures`
- PASS lines: `319`

Non-overlap:

- Avoids accepted JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window batches, JSON101/102 dynamic extraction/type/array-length corpus, JSON105 reverse-index mutation corpus, JSONB malformed path operator corpus, and app-specific JSON WAL/savepoint scenarios.
- This slice owns upstream `json501.test` JSON5 lexical behavior only.

Dependency closure:

- No new support component is needed. The batch reuses existing native PHP `SQLiteJson5Parser`, `SQLiteJsonCanonical`, `SQLiteJsonExtract`, `SQLiteJsonValidity`, `SQLiteJsonErrorPosition`, and `SQLiteJsonB`.
