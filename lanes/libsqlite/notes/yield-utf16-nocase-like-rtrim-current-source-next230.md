# UTF-16 NOCASE LIKE RTRIM current-source next230

This slice adds focused coverage for `rtrim(option_name) COLLATE NOCASE LIKE ?`
over copied Application `wp_options` rows when UTF-16 text ends with CR, LF, or
form-feed bytes. SQLite `RTRIM` only removes ASCII space for this expression, so
line-break/control suffixes stay part of the residual `LIKE` comparison and
must invalidate stale current-source cursors when a next source rewrites them
to exact or ASCII-space-padded option names.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext230Test.php`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next230.php --self-test`
- PHP lint on the changed PHP files
- `git diff --check -- lanes/libsqlite`

Focused result: `1 test files, 76 assertions, 0 failures`, with `71` PASS
lines.

Dependency closure: no new support component is needed. The slice reuses native
UTF-16 decode, ASCII `NOCASE` LIKE prefix planning, RTRIM expression keys, and
binary-safe residual matching.

Non-overlap: next230 covers CR/LF/form-feed suffixes that remain significant
after RTRIM for UTF-16 NOCASE LIKE current-source cursors. It avoids accepted
next227 tab/NBSP boundary, next226 combining-mark normalization, next225
source-byte fencing, Unicode GLOB ranges, UTF-16 malformed insert guards, and
storage/planner clusters.
