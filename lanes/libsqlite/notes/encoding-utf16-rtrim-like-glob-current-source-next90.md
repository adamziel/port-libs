# Encoding UTF-16 RTRIM LIKE/GLOB current-source next90

Status: focused PHP behavior growth for UTF-16 option-name cursor scans using SQLite RTRIM collation with GLOB residual matching.

Behavior:
- `SQLiteUtf16LikeGlobCurrentNextCursor::currentNextPlan()` now exposes the collation comparison key used for cursor ordering and lower/upper range checks.
- RTRIM comparison keys trim only trailing U+0020 spaces for cursor ordering. The residual GLOB matcher still receives the original decoded text, so exact `GLOB 'plugin-cache'` matches `plugin-cache` but rejects `plugin-cache ` while `GLOB 'plugin-cache*'` includes the padded peer.
- The focused test covers UTF-16LE and UTF-16BE option-name bytes, padded peers, non-breaking space and tab suffixes that must not be RTRIM-trimmed, uppercase case-sensitive GLOB behavior, range upper-bound exclusion, malformed UTF-16 guards, and the Application scan helper.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16RtrimLikeGlobCurrentSourceNext90Test.php`
- `php -l lanes/libsqlite/src/SQLiteUtf16LikeGlobCurrentNextCursor.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16RtrimLikeGlobCurrentSourceNext90Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-rtrim-like-glob-current-source-next90.php`
- `php lanes/libsqlite/examples/application-utf16-rtrim-like-glob-current-source-next90.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement:
- `phpPass`: `35300 -> 35352` from the 52 newly passing focused PASS lines in this lane test (`53` assertions total).
- `benchmarkDenominator.mapped`: unchanged at `517 / 1589`; this is current-source PHP coverage over an already mapped encoding/collation family, not a fresh upstream denominator unit.

Dependency closure:
- No new support component is needed. This reuses the existing native UTF-16 decoder, RTRIM/BINARY/NOCASE collation comparison, GLOB matcher, and Application option-name scan helper.

Non-overlap:
- Avoids accepted UTF-16 malformed record guards, Unicode GLOB character-class ranges, LIKE/GLOB current-source reprepare, UTF-16 affinity cursors, malformed text LIKE/GLOB guards, and batch88 LIKE/GLOB current-source collation planning. The new surface is RTRIM collation comparison-key observability and residual-vs-cursor behavior for UTF-16 option-name GLOB scans.
