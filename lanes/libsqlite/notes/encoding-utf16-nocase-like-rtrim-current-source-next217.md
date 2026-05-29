# UTF-16 NOCASE LIKE RTRIM current-source next217

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for a
current-source `wp_options` scan using:

`rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?`

The focused behavior is a decoded UTF-16 prepared pattern with ASCII spaces
immediately before the first unescaped `%`. SQLite keeps those spaces
significant in the LIKE pattern; `rtrim(option_name)` only trims the left
expression before range/residual matching. A stale cursor planned for
`plugin!_cache %` must not be reused for `plugin!_cache%`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext217Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next217.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext217Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next217.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected focused movement: `+82` PASS lines in the new focused test file.

Dependency closure: no new support component is needed; the slice reuses native
UTF-16 decode, prepared LIKE pattern text, ASCII NOCASE prefix planning, RTRIM
expression keys, and residual matching.

Non-overlap: avoids accepted embedded-NUL next210, Unicode ESCAPE next212,
source refresh next211, ASCII-space row RTRIM next209, Unicode GLOB, and
malformed UTF-16 insert guards.
