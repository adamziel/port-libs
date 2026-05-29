# UTF-16 NOCASE LIKE RTRIM current-source next231

Status: focused PHP behavior growth for `encoding-utf16-nocase-like-rtrim-current-source-next231`.

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for UTF-16 `wp_options.option_name` scans using:

`rtrim(option_name) COLLATE NOCASE LIKE ?`

The focused behavior is SQLite's ASCII-only NOCASE boundary. ASCII letters in plugin option prefixes fold before the LIKE residual runs, but non-ASCII case pairs such as `é` / `É` and `μ` / `Μ` remain distinct after UTF-16 decoding and RTRIM. A current-source cursor is invalidated when rows cross that non-ASCII case boundary even if the ASCII prefix range is stable.

WordPress smoke: `examples/wordpress-utf16-nocase-like-rtrim-current-source-next231.php` models copied plugin cache option rows where a lowercase accented option name becomes uppercase-accented across the current/next source boundary.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext231Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next231.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext231Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next231.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected focused movement: `+61` PASS lines / `69` assertions from the new test file. Mapped upstream coverage remains unchanged; this reuses already mapped UTF-16, RTRIM, NOCASE, LIKE, and current-source inventory rather than claiming a fresh manifest row.

Non-overlap: avoids accepted next227 ASCII-space RTRIM boundary, next226 combining-mark normalization, next225 raw source bytes, next219 supplementary-plane wildcard matching, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner/status-only clusters. The new surface is non-ASCII case variants remaining distinct under UTF-16 NOCASE LIKE after RTRIM.

Dependency closure: no new support component is needed. The slice reuses lane-local UTF-16 decoding, ASCII NOCASE LIKE prefix planning, RTRIM expression-key behavior, and residual LIKE matching.
