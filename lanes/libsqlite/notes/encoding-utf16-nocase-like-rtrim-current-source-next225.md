# UTF-16 NOCASE LIKE RTRIM current-source next225

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for
current-source `wp_options` scans using:

`rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?`

The focused behavior is byte-source invalidation after UTF-16 decoding. A row can
keep the same decoded text, RTRIM key, NOCASE key, and LIKE residual result while
its stored source bytes move between UTF-16LE, UTF-16BE, and UTF-8. SQLite SQL
comparison results stay stable, but a current-source cursor must still fence
replay because the raw cell payload and text encoding changed.

WordPress smoke: `examples/wordpress-utf16-nocase-like-rtrim-current-source-next225.php`
models copied `wp_options` option names whose decoded plugin cache keys remain
matched while source bytes and endian labels change.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext225Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next225.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext225Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next225.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected focused movement: `+73` PASS lines in the new focused test file.
Mapped upstream coverage remains unchanged; this reuses already mapped UTF-16,
NOCASE, LIKE, RTRIM, and current-source inventory rather than claiming a fresh
manifest row.

Dependency closure: no new support component is needed. The slice reuses native
UTF-16 decoding, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and
current-source raw byte diagnostics.

Non-overlap: avoids accepted next219 supplementary wildcard matching, next217
pattern-space handling, next213 Unicode ESCAPE, next210 embedded NUL, Unicode
GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters. The
new surface is raw source-byte and endian-change cursor fencing when decoded
NOCASE/RTRIM LIKE results remain stable.
