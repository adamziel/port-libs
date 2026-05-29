# UTF-16 NOCASE LIKE RTRIM current-source next192

- Slice: `encoding-utf16-nocase-like-rtrim-current-source-next192`.
- Behavior: adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` prefix cursors whose yielded resume token may be a range candidate that fails the residual LIKE predicate.
- Focus: mixed UTF-16/UTF-8 `wp_options` scans must compare candidate rows before the token, matched rows before the token, and residual false positives before the token before resuming after a current-source switch.
- WordPress smoke: `examples/wordpress-utf16-nocase-like-rtrim-current-source-next192.php --self-test`.
- Non-overlap: avoids accepted next183 prefix reuse, next187 dangling ESCAPE residuals, next189 matched-peer resume, next104/105 UTF-16 affinity LIKE/GLOB, Unicode GLOB ranges, UTF-16 malformed insert guards, and B-tree/JSON/VFS/WAL/planner surfaces.
- Dependency closure: no new support component needed; this reuses native UTF-16 decode, ASCII NOCASE LIKE prefix range planning, RTRIM expression keys, and existing residual LIKE matching.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext192Test.php`: `1 test files, 74 assertions, 0 failures` with 60 PASS lines.
  - `php -l` on changed PHP source/test/example files: no syntax errors.
  - `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next192.php --self-test`: self-test passed.
  - `git diff --check -- lanes/libsqlite`: passed.
  - Root harness: not run - isolated micro-slice.
