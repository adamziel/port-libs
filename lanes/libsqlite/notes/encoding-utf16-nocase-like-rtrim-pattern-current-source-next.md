# SQLite UTF-16 NOCASE LIKE RTRIM Pattern Current Source Next160

Status: focused PHP behavior growth for prepared UTF-16 LIKE pattern bytes feeding a NOCASE residual over an RTRIM current-source candidate range.

Behavior:

- Adds `SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan`.
- Decodes current and next prepared LIKE pattern/ESCAPE bytes from UTF-8, UTF-16LE, or UTF-16BE before delegating row matching to the accepted next156 UTF-16 NOCASE/RTRIM scanner.
- Reports pattern text, pattern encoding, raw pattern bytes, escape bytes, candidate rowset, matched rowset, RTRIM false-positive rowset, malformed-row, source, and schema-cookie reasons for cursor invalidation.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextTest.php`
  - `51` PASS lines
  - `1 test files, 58 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-pattern-current-source-next.php --self-test`
  - `wordpress-utf16-nocase-like-rtrim-pattern-current-source-next self-test passed`
- `php -l lanes/libsqlite/src/SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-pattern-current-source-next.php`

Expected dashboard movement: `phpPass +51`, from `70891` to `70942`. Mapped upstream coverage remains `608 / 1589`; this reuses already mapped encoding/collation/LIKE current-source inventory rather than claiming a fresh upstream manifest row.

Non-overlap:

- Avoids accepted next156 row matching, next141 malformed-row isolation, next143 LIKE ESCAPE residual behavior, next140/next145 RTRIM candidate range behavior, Unicode GLOB ranges, UTF-16 malformed record guards, and VFS/WAL/B-tree/JSON/SQL executor clusters.
- The new surface is prepared statement pattern/escape byte provenance across a current/next source transition.

Dependency closure:

- No new support component is needed. The slice reuses native UTF-16 decode, RTRIM range keys, ASCII NOCASE LIKE residual matching, and current-source diagnostics.
