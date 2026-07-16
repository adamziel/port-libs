# Encoding UTF-16 NOCASE LIKE RTRIM Resume Token Current Source Next170

Status: focused PHP behavior growth for UTF-16 encoded resume-token keys in a NOCASE LIKE cursor over `rtrim(option_name)`.

Behavior:
- Adds `SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextPlan`.
- Decodes saved resume-token key bytes from UTF-8, UTF-16LE, or UTF-16BE, then applies SQLite's ASCII-only NOCASE key folding and ASCII-space-only RTRIM before delegating to the accepted next165 resume planner.
- Treats UTF-16LE/UTF-16BE token byte-order changes as byte-only reprepare when the decoded token key and rowid are unchanged, so a copied `wp_options` scan can continue after the saved key instead of restarting the range.
- Still forces range restart when token text/rowid changes, source/schema invalidation is semantic, a new row enters before the token, or malformed current/next text appears.

Application smoke:
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-resume-token-current-source-next.php --self-test`

Focused verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextTest.php`

Non-overlap:
- Avoids accepted next156/160/162/163/165/166 row matching, pattern/escape byte normalization, ESCAPE RTRIM, and string-token resume behavior.
- The new surface is only the persisted resume-token key when that key is stored as UTF-16 bytes and changes byte order across current/next copied sources.

Dependency closure:
- No new support component is needed. The slice reuses native UTF-16 decode, accepted NOCASE/RTRIM LIKE resume planning, and current-source diagnostics.
