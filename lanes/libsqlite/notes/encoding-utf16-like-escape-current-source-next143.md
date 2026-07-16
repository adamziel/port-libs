# encoding-utf16-like-escape-current-source-next143

Status: focused PHP behavior growth for UTF-16 LIKE ESCAPE current-source handoff.

This slice adds `SQLiteUtf16LikeEscapeCurrentSourceNextPlan::optionRowNameLikeEscape()`. It models a prepared `wp_options.option_name LIKE ... ESCAPE ...` range scan over decoded UTF-16/UTF-8 text where escaped `%` and `_` are literal prefix characters, residual LIKE matching still uses decoded text, `NOCASE` folds ASCII only, `RTRIM` uses a trimmed range key but not a trimmed residual value, and a trailing escape pattern produces no residual matches while invalidating cursor reuse.

Application path: `application-utf16-like-escape-current-source-next143.php` models plugin option keys such as `plugin_100%_enabled` copied across a current/next source with mixed UTF-16LE/UTF-16BE storage and a repaired escaped-wildcard row.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16LikeEscapeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16LikeEscapeCurrentSourceNext143Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-like-escape-current-source-next143.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16LikeEscapeCurrentSourceNext143Test.php`
- `php lanes/libsqlite/examples/application-utf16-like-escape-current-source-next143.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +67` from the focused test file. `lane-status.json` `phpPass` moves from `62524` to `62591`. Mapped upstream coverage remains `606 / 1589`; this is current-source PHP behavior over already mapped LIKE, UTF-16, collation, and current-source inventory.

Non-overlap: avoids accepted Unicode GLOB ranges, UTF-16 malformed guards, UTF-16 GLOB range provenance, RTRIM/NOCASE LIKE/GLOB current-source slices through next140, option-name RTRIM LIKE/GLOB behavior from batch139, JSON/VFS/WAL/B-tree/SQL executor clusters, and release-runner evidence work. The new surface is UTF-16 LIKE ESCAPE literal wildcard prefix and dangling-escape residual behavior at the current-source to next-source boundary.

Dependency closure: no new support component is needed. The patch reuses native PHP UTF-16 encode/decode helpers, LIKE ESCAPE prefix planning, residual LIKE matching, collation keys, and current-source invalidation metadata.

Next task: continue encoding only on a non-overlapping malformed comparison or collation/affinity edge with focused tests; otherwise pivot to a higher-yield current-source closure bucket.
