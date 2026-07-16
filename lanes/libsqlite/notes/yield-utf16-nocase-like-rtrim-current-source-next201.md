# UTF-16 NOCASE LIKE RTRIM current-source next201

- Slice: `encoding-utf16-nocase-like-rtrim-current-source-next201`.
- Behavior: adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for UTF-16 `wp_options` scans using `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` when a prepared LIKE pattern is rebound to SQL `NULL`.
- Focus: SQL `NULL` LIKE patterns match no rows, disable prefix range reuse, and force current-source cursor invalidation when a prior non-NULL UTF-16 range scan had candidates. Decoding still reports malformed UTF-16 rows separately.
- Application smoke: `examples/application-utf16-nocase-like-rtrim-current-source-next201.php`.
- Non-overlap: avoids accepted next200 ESCAPE rebind, next194 escaped wildcard literal-prefix ranges, next191 prepared byte-order rebind, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters.
- Dependency closure: no new support component needed; this reuses native UTF-16 decode, LIKE NULL-result semantics, RTRIM expression keys, and current-source cursor invalidation diagnostics.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext201Test.php`: `1 test files, 81 assertions, 0 failures`.
  - `php -l` on changed PHP source/test/example files: no syntax errors.
  - `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next201.php --self-test`: self-test passed.
  - `git diff --check -- lanes/libsqlite`: passed.
  - Root harness: not run - isolated micro-slice.
