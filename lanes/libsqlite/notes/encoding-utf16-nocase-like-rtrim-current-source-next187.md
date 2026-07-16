# encoding-utf16-nocase-like-rtrim-current-source-next187

Status: focused PHP behavior growth for UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` scans when the prepared LIKE pattern ends with the escape character.

Behavior:

- Adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`.
- Covers SQLite's dangling-escape residual behavior: `LIKE 'plugin!' ESCAPE '!'` matches no rows, even though a NOCASE prefix cursor can still admit `plugin...` candidates.
- Reports prefix range candidates, all-candidate residual misses, malformed UTF-16 row isolation, current/next range entry, and cursor invalidation reasons.

Application path:

- `application-utf16-nocase-like-rtrim-current-source-next187.php` models copied `wp_options.option_name` UTF-16 rows where a plugin option-name scan is prepared with a dangling escape during a source handoff. The plan forces residual recheck instead of reusing stale prefix candidates as matches.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext187Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next187.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext187Test.php`
  - `1 test files, 67 assertions, 0 failures`
  - `54` PASS lines
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next187.php --self-test`
  - `application-utf16-nocase-like-rtrim-current-source-next187 self-test passed`

Expected dashboard movement: `phpPass +54`, from `88817` to `88871`. Mapped upstream coverage remains `616 / 1589`; this is focused PHP behavior over already mapped UTF-16, NOCASE/RTRIM, LIKE, and current-source inventory.

Non-overlap:

- Avoids accepted next183 ASCII prefix range reuse, next184 escaped peer replay, prepared-pattern byte normalization, Unicode GLOB ranges, malformed UTF-16 insert guards, JSON/VFS/WAL/B-tree/SQL executor clusters, and suite-runner evidence work.
- The new surface is specifically dangling `ESCAPE` residual recheck for UTF-16 NOCASE/RTRIM LIKE current-source prefix cursors.

Dependency closure:

- No new support component is needed. The slice reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM keys, and SQLite LIKE residual matching.

Next task: continue encoding work only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
