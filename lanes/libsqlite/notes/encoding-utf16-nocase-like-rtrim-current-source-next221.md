# encoding-utf16-nocase-like-rtrim-current-source-next221

Status: focused PHP behavior growth for UTF-16 RTRIM/NOCASE LIKE current-source
scans when the decoded prepared SQL text is stable but the prepared pattern or
ESCAPE byte signature changes across UTF-16LE/UTF-16BE bindings.

WordPress path: `wordpress-utf16-nocase-like-rtrim-current-source-next221.php`
models copied `wp_options.option_name` prefix scans where `plugin!_cache%`
decodes to the same LIKE pattern on both sides, yet the prepared statement
metadata changes endian byte order. The row range and residual matches can stay
stable, but a current-source cursor must still reprepare rather than reuse
stale prepared bytes.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext221Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next221.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext221Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next221.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected focused movement: `+72` PASS lines / `86` assertions from the new
focused test file. Mapped upstream coverage remains unchanged; this is already
mapped UTF-16, RTRIM, NOCASE, LIKE, and ESCAPE behavior.

Dependency closure: no new support component is needed; the slice reuses native
UTF-16 decode, prepared LIKE byte metadata, ASCII NOCASE prefix planning, RTRIM
expression keys, and residual matching.

Non-overlap: avoids accepted BOM normalization next206, Unicode ESCAPE
next212, prepared pattern-space next217, ASCII RTRIM next209, escaped literal
next194/195, Unicode GLOB, malformed UTF-16 insert guards, and
storage/planner/JSON/VFS/WAL clusters. The new surface is prepared byte
signature invalidation when decoded SQL text and row matches are otherwise
stable.
