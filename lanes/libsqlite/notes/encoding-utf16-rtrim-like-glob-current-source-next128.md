# encoding-utf16-rtrim-like-glob-current-source-next128

Status: focused PHP behavior growth for UTF-16 RTRIM current-source scans when a prepared WordPress option-name cursor changes from residual `LIKE` to prefix-ranged `GLOB`.

This slice adds `SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan`, which decodes UTF-8/UTF-16LE/UTF-16BE source rows, sorts with SQLite `RTRIM` collation keys that trim only ASCII space, keeps residual `LIKE`/`GLOB` matching on the original decoded text, and reports current/next invalidation when the operator, pattern range, source, malformed text, encoded bytes, candidates, or matched rowset changes.

WordPress path: `wordpress-utf16-rtrim-like-glob-current-source-next128.php` models a copied `wp_options.option_name` scan whose prepared predicate switches from escaped `LIKE 'plugin!_cache%' ESCAPE '!'` to `GLOB 'plugin_cache*'` across a current-source transition.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16RtrimLikeGlobCurrentSourceNext128Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-rtrim-like-glob-current-source-next128.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16RtrimLikeGlobCurrentSourceNext128Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-rtrim-like-glob-current-source-next128.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dashboard delta: expected `phpPass` +66 from focused PASS lines. Mapped coverage remains `606 / 1589`; this reuses already mapped UTF-16 decoding, RTRIM collation, LIKE/GLOB residual matching, and current-source invalidation inventory rather than claiming a fresh upstream row.

Non-overlap: avoids accepted UTF-16 NOCASE LIKE next126, RTRIM GLOB next125, UTF-16 GLOB literal bracket next122, RTRIM LIKE next121, RTRIM/NOCASE GLOB next119, UTF-16 pattern decoding next114, Unicode GLOB ranges, malformed UTF-16 guards, JSON/VFS/WAL/B-tree/SQL executor current-source clusters, and release-runner evidence work. The new surface is the operator-switch composition between residual `LIKE` and ranged `GLOB` for UTF-16 RTRIM current-source cursors.

Dependency closure: no new support component is needed. The patch reuses native PHP UTF-16 encode/decode, RTRIM collation keys, SQLite LIKE/GLOB matchers, and current-source diagnostics.
