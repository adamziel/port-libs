# UTF-16 NOCASE LIKE RTRIM current-source next186

- Slice: `encoding-utf16-nocase-like-rtrim-current-source-next186`.
- Behavior: adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for UTF-16 `wp_options` option-name range scans using `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` with resume-token boundary diagnostics across current-source refresh.
- Focus: byte-order-only UTF-16 source changes can preserve semantic RTRIM/NOCASE keys, while source changes that alter the LIKE residual rowset or resume boundary must reopen the cursor before continuing.
- Application smoke: `examples/application-utf16-nocase-like-rtrim-current-source-next186.php --self-test`.
- Non-overlap: avoids accepted next177 Unicode wildcard recheck, next180 non-ASCII prefix fallback, next183 basic ASCII prefix range, next104/105 UTF-16 affinity LIKE/GLOB, accepted Unicode GLOB ranges, and current B-tree/JSON/VFS/WAL/planner surfaces.
- Dependency closure: no new support component needed; this reuses native UTF-16 decode, ASCII NOCASE LIKE prefix range planning, RTRIM residual matching, and existing current-source cursor concepts.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext186Test.php`: `1 test files, 78 assertions, 0 failures`.
  - `php -l` on changed PHP source/test/example files: no syntax errors.
  - `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next186.php --self-test`: self-test passed.
  - `git diff --check -- lanes/libsqlite`: passed.
  - Root harness: not run - isolated micro-slice.
