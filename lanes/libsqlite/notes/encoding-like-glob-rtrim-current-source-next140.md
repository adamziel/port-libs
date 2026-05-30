# encoding-like-glob-rtrim-current-source-next140

Status: focused PHP behavior growth for encoded `wp_options.option_name` LIKE/GLOB scans through an `rtrim(option_name)` current/next source cursor.

This slice adds `SQLiteEncodingLikeGlobRtrimCurrentSourceNextPlan::optionRowNamePlan()`. It decodes UTF-8/UTF-16LE/UTF-16BE option-name bytes, builds an RTRIM expression-index candidate range, and then applies SQLite LIKE/GLOB residual matching to the original untrimmed decoded text. The focused cases cover padded-space false positives, tab preservation, escaped LIKE prefixes, GLOB wildcard ranges, Unicode text, malformed UTF-16 source rows, source/schema invalidation, encoding/byte changes, and stable cursor reuse.

Application smoke: `application-option-name-rtrim-like-glob-current-source-next140.php` models copied `wp_options` option-name scans where an RTRIM range admits padded rows, but residual LIKE/GLOB matching keeps exact SQLite text behavior when current and next sources diverge.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingLikeGlobRtrimCurrentSourceNext140Test.php`
  - `1 test files, 76 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-option-name-rtrim-like-glob-current-source-next140.php --self-test`
  - `application-option-name-rtrim-like-glob-current-source-next140 self-test passed`

Expected dashboard movement: `phpPass +76`, from `60841` to `60917`. Mapped upstream coverage remains `606 / 1589`; this reuses already mapped encoding, RTRIM collation, LIKE/GLOB, and current-source cursor inventory rather than claiming a fresh manifest row.

Non-overlap: avoids accepted Unicode GLOB ranges, UTF-16 malformed record guards, UTF-16 RTRIM LIKE/GLOB pattern/current-source next138, RTRIM/NOCASE GLOB next136, CAST/RTRIM LIKE/GLOB next127/next131, SELECT predicate affinity/collation next109, JSON/VFS/WAL/B-tree/SQL executor clusters, and release-runner evidence work. The new surface is specifically an encoded RTRIM expression-index candidate range with untrimmed LIKE/GLOB residuals across current/next source invalidation.

Dependency closure: no new support component is needed. The patch reuses lane-local text encoding decode, LIKE/GLOB range planning, RTRIM collation keys, and current-source invalidation metadata.

Next task: continue encoding only on a non-overlapping malformed-text, affinity, or collation edge with focused tests; otherwise pivot to another current-source closure bucket.
