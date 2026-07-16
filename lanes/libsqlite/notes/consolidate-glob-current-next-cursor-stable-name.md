# Glob Cursor Stable Name Consolidation

- Consolidated the generated production GLOB cursor class/file into `SQLiteGlobCursor`.
- Updated the direct UTF-16 wrapper, focused glob tests, Application examples, and nearby notes to use the stable cursor name.
- Preserved observable cursor payloads, dependency strings, scenario labels, and current/next diagnostic keys.

Verification:

- `php -l lanes/libsqlite/src/SQLiteGlobCursor.php`
- `php -l lanes/libsqlite/src/SQLiteUtf16GlobCurrentNextCursor.php`
- `php -l lanes/libsqlite/tests/SQLiteGlobCursorTest.php`
- `php -l lanes/libsqlite/tests/SQLiteGlobMalformedUtfCursorTest.php`
- `php -l lanes/libsqlite/tests/SQLiteLikeGlobMalformedTextCursorTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRtrimCollationGlobCursorTest.php`
- `php -l lanes/libsqlite/examples/application-option-name-glob-cursor.php`
- `php -l lanes/libsqlite/examples/application-option-name-glob-malformed-utf-cursor.php`
- `php -l lanes/libsqlite/examples/application-option-name-like-glob-malformed-cursor.php`
- `php -l lanes/libsqlite/examples/application-rtrim-collation-glob-cursor.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteGlobCursorTest.php lanes/libsqlite/tests/SQLiteGlobMalformedUtfCursorTest.php lanes/libsqlite/tests/SQLiteLikeGlobMalformedTextCursorTest.php lanes/libsqlite/tests/SQLiteRtrimCollationGlobCursorTest.php lanes/libsqlite/tests/SQLiteUtf16GlobCurrentNext78Test.php lanes/libsqlite/tests/SQLiteUtf16GlobRangeCurrentSourceNext102Test.php`: `6 test files, 331 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-option-name-glob-cursor.php`
- `php lanes/libsqlite/examples/application-option-name-glob-malformed-utf-cursor.php`
- `php lanes/libsqlite/examples/application-option-name-like-glob-malformed-cursor.php`
- `php lanes/libsqlite/examples/application-rtrim-collation-glob-cursor.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; the existing bounded GLOB cursor implementation is reused under a stable production name.
