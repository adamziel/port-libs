# SQLite Encoding LIKE/GLOB Malformed Text Current Source Next84

## Behavior

- Adds malformed UTF-8 diagnostics to `SQLiteLikeCurrentNextCursor::currentNextPlan()` and `matchedRows()`, matching the existing GLOB cursor diagnostics.
- Covers case-sensitive `BINARY` LIKE byte-prefix range scans over malformed WordPress option names while preserving safe range rejection for default `NOCASE` LIKE when the fixed prefix is not ASCII.
- Keeps the slice disjoint from accepted Unicode GLOB range handling and UTF-16 malformed record guards: this patch is UTF-8 malformed LIKE current/next cursor behavior with GLOB parity checks only.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteLikeGlobMalformedTextCursorTest.php`: `1 test files, 65 assertions, 0 failures`, `51` PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteLikeCurrentNextCursorCurrentNext68Test.php lanes/libsqlite/tests/SQLiteLikeGlobMalformedTextCursorTest.php`: `2 test files, 121 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-option-name-like-glob-malformed-cursor.php`: emits valid JSON with `likeBinaryRowids` `[2,3]`, GLOB parity rowids `[2,3]`, and default NOCASE rejection `nocase_like_prefix_must_be_ascii_for_range`.
- `php -l lanes/libsqlite/src/SQLiteLikeCurrentNextCursor.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteLikeGlobMalformedTextCursorTest.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/wordpress-option-name-like-glob-malformed-cursor.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status valid JSON\n";'`: valid JSON.
- `git diff --check -- lanes/libsqlite`: clean.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP LIKE/GLOB pattern splitting and cursor planning helpers.
