# encoding-utf16-cast-glob-current-source-next135

Status: focused PHP behavior growth for UTF-16 `CAST(... AS TEXT) GLOB` current-source scans.

This slice adds `SQLiteUtf16CastGlobCurrentSourceNextPlan`, which decodes UTF-16LE/UTF-16BE/UTF-8 option-value bytes before applying a binary `GLOB` prefix range and residual match for `CAST(option_value AS TEXT) GLOB ...`. It records malformed UTF-16 rows, encoded-byte and encoding changes, candidate/match rowset changes, and current/next invalidation reasons for copied `wp_options` sources.

WordPress smoke: `php lanes/libsqlite/examples/wordpress-utf16-cast-glob-current-source-next135.php --self-test`

Non-overlap: avoids accepted Unicode GLOB range handling, malformed UTF-16 insert guards, UTF-16 RTRIM LIKE/GLOB current-source behavior, CAST/RTRIM GLOB next127, CAST/RTRIM LIKE next131, CAST/NOCASE next129, UTF-16 RTRIM NOCASE next132, JSON/VFS/WAL/B-tree/SQL executor current-source clusters, and release-runner evidence work. The new surface is specifically UTF-16 option-value `CAST(... AS TEXT) GLOB` with BINARY collation and malformed-row current/next invalidation.

Dependency closure: no new support component is needed. The patch reuses native PHP UTF-16 encode/decode helpers, GLOB prefix range/matcher behavior, and current-source row-array diagnostics.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16CastGlobCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUtf16CastGlobCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16CastGlobCurrentSourceNext135Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteUtf16CastGlobCurrentSourceNext135Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-cast-glob-current-source-next135.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-utf16-cast-glob-current-source-next135.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16CastGlobCurrentSourceNext135Test.php`
  - `1 test files, 85 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-utf16-cast-glob-current-source-next135.php --self-test`
  - `wordpress-utf16-cast-glob-current-source-next135 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Expected dashboard delta: `phpPass` +85, from `56681` to `56766`. Mapped upstream coverage remains `606 / 1589`; this is focused PHP behavior over already mapped encoding/collation/CAST/GLOB inventory, not a newly hydrated upstream manifest row.
