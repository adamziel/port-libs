# UTF-16 NOCASE LIKE RTRIM ESCAPE current-source next182

- Added `SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext182Plan` for UTF-16 `LIKE ... ESCAPE` operand validation before replaying a saved `rtrim(option_name) COLLATE NOCASE` cursor.
- Behavior: decodes current/next pattern and escape operands, enforces single-character `ESCAPE`, records malformed escape bytes separately, and forces replay from the decoded escape range start when escape text/encoding/bytes drift across sources.
- Application smoke: `application-utf16-nocase-like-rtrim-escape-current-source-next182.php` covers copied `wp_options` escaped-wildcard option names.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext182Test.php` passed with `1 test files, 50 assertions, 0 failures` and 32 PASS lines.
- Expected dashboard movement: `phpPass +32` from the new focused PASS lines; mapped upstream coverage remains unchanged because this is current-source PHP behavior over the already mapped encoding/collation/LIKE family.
- Dependency closure: no new support component is needed; the patch reuses native UTF-16 decode, LIKE ESCAPE validation, and NOCASE/RTRIM byte-token replay diagnostics.
- Non-overlap: avoids accepted UTF-16 malformed insert guards, Unicode GLOB ranges, RHS RTRIM planning, next175 token byte fingerprint-only replay, and current WAL/B-tree/JSON/planner clusters.
