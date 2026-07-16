# encoding-utf16-nocase-like-rtrim-current-source-next164

Status: focused PHP behavior growth for UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE` scans across a current-source yield/resume boundary.

Application path: `application-utf16-nocase-like-rtrim-current-source-next164.php` models copied `wp_options.option_name` rows where a prepared plugin-cache scan yields after reading current UTF-16 rows, then resumes against a next source. Retained rows with identical decoded RTRIM/NOCASE keys and bytes are resume-safe; retained rows with changed RTRIM key, encoding, bytes, source token, schema cookie, collation generation, LIKE generation, or prepared statement fingerprint require reprepare and residual recheck.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext164Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next164.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext164Test.php`
  - `1 test files, 88 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next164.php --self-test`
  - `application-utf16-nocase-like-rtrim-current-source-next164 self-test passed`

Expected dashboard movement: `phpPass +88` from `73438` to `73526`; no mapped upstream denominator change claimed.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE prefix planning, accepted next161 current-source metadata, and adds lane-local yield/resume statement fingerprints.

Non-overlap: avoids accepted UTF-16 NOCASE/LIKE/RTRIM row-text next156/next158, pattern/ESCAPE next159/next160, generation invalidation next161, malformed-row isolation next141, UTF-16 LIKE ESCAPE next143, Unicode GLOB ranges, VFS/WAL/B-tree/JSON/SQL executor clusters, and suite evidence work. The narrower new surface is yield/resume safety and retained-row recheck classification for a prepared UTF-16 NOCASE/RTRIM LIKE scan.
