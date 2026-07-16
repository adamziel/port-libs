# Encoding UTF-16 NOCASE LIKE RTRIM Current Source Next162

Status: focused PHP behavior growth for UTF-16 prepared LIKE pattern normalization over a NOCASE residual and RTRIM current-source candidate range.

Behavior:
- Adds `SQLiteUtf16NoCaseLikeRtrimCurrentSourceNext162Plan`.
- Reuses the accepted next160 UTF-16 pattern decode and next156 row scanner, but separates raw prepared-pattern/escape byte-order changes from semantic cursor invalidation.
- A current/next source can reuse the cursor when UTF-16LE and UTF-16BE pattern bytes decode to the same SQL pattern/escape text and rowsets are stable.
- Source/schema, decoded pattern/escape text changes, rowset changes, RTRIM false positives, and malformed row text remain semantic invalidation reasons.

Application path:
- `application-utf16-nocase-like-rtrim-current-source-next162.php` models copied `wp_options.option_name` scans where the prepared pattern is rebound in a different UTF-16 byte order during import/rebuild, while decoded LIKE semantics and matched rows remain stable.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NoCaseLikeRtrimCurrentSourceNext162Test.php`
  - `1 test files, 78 assertions, 0 failures`
  - `58` PASS lines
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next162.php --self-test`
  - `application-utf16-nocase-like-rtrim-current-source-next162 self-test passed`
- PHP lint and `git diff --check -- lanes/libsqlite` passed in this lane.

Expected dashboard movement: `phpPass +58`, from `72664` to `72722`. Mapped upstream coverage remains `609 / 1589`; this reuses already mapped UTF-16, LIKE, RTRIM, NOCASE, and current-source inventory rather than claiming a fresh manifest row.

Non-overlap:
- Avoids accepted next160 UTF-16 prepared pattern byte provenance and row matching, next156 NOCASE/RTRIM scanner behavior, Unicode GLOB ranges, UTF-16 malformed insert guards, JSON/VFS/WAL/B-tree/SQL executor clusters, and suite-runner evidence work.
- The new surface is normalized prepared-pattern byte-order reprepare handling: byte provenance remains visible, but decoded-equivalent pattern/escape bytes do not force semantic cursor invalidation when source and rowsets are otherwise stable.

Dependency closure:
- No new support component is needed. The slice reuses native UTF-16 pattern decode, RTRIM range keys, ASCII NOCASE LIKE residual matching, and current-source diagnostics.
