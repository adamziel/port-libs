# UTF-16 NOCASE LIKE RTRIM current-source next194

- Slice: `encoding-utf16-nocase-like-rtrim-current-source-next194`.
- Behavior: adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for UTF-16 `wp_options` option-name scans using `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` where escaped `%` / `_` characters are literal bytes inside the fixed LIKE prefix.
- Focus: `LIKE 'plugin!%%' ESCAPE '!'` range-scans on the literal `plugin%` prefix under ASCII NOCASE, applies the residual after RTRIM, preserves UTF-16LE/BE decoding behavior, and reports current-source invalidation when literal-prefix matched rows change.
- Application smoke: `examples/application-utf16-nocase-like-rtrim-current-source-next194.php`.
- Non-overlap: avoids accepted dangling ESCAPE next187, peer-window next189, Unicode GLOB ranges, malformed UTF-16 insert guards, JSON/VFS/WAL/B-tree/planner clusters, and status-only evidence.
- Dependency closure: no new support component needed; this reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and LIKE residual matching.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext194Test.php`: `1 test files, 77 assertions, 0 failures`.
  - `php -l` on changed PHP source/test/example files: no syntax errors.
  - `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next194.php --self-test`: self-test passed.
  - `git diff --check -- lanes/libsqlite`: passed.
  - Root harness: not run - isolated micro-slice.
