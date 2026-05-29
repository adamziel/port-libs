# encoding-utf16-nocase-like-rtrim-current-source-next209

Adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for UTF-16
`rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` current-source cursor
reuse where SQLite must trim only ASCII space before NOCASE LIKE range checks.
Tabs and NBSP suffixes remain part of the expression key, and Unicode case
variants that a Unicode fold would match are reported as residual-only hazards
because SQLite NOCASE folds ASCII only.

WordPress relevance: copied `wp_options` imports commonly rebuild option-name
indexes while plugin rows carry mixed UTF-8/UTF-16 text, trailing spaces, tabs,
or NBSP from import data. A stale range cursor must not collapse those suffixes
or fold Unicode case while replaying a prepared prefix scan.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext209Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next209.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext209Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next209.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses native
UTF-16 decode, ASCII NOCASE LIKE range planning, RTRIM expression keys, and
residual LIKE matching.

Non-overlap: avoids accepted BOM normalization next206, escape rebind next200,
escaped literal/dangling ESCAPE slices, Unicode GLOB ranges, malformed UTF-16
insert guards, and storage/planner clusters.
