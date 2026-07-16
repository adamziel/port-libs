# UTF-16 NOCASE LIKE RTRIM Current-Source Next215

Slice: `encoding-utf16-nocase-like-rtrim-current-source-next215`

Behavior:
- Adds a focused current-source replay plan for `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` over copied Application `wp_options` rows when decoded UTF-16 text contains embedded NUL bytes.
- Preserves embedded NUL bytes in SQLite text keys and residual LIKE checks instead of treating NUL as a C-string terminator.
- Fences resume tokens when current/next source changes, malformed UTF-16 is present, candidate or matched rows before the token changed, or multiple embedded-NUL keys collide after C-string truncation.

Evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext215Test.php`
- Result: `1 test files, 76 assertions, 0 failures`, `66` PASS lines.
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next215.php`
- Result: `application-utf16-nocase-like-rtrim-current-source-next215 self-test passed`

Non-overlap:
- Avoids accepted Unicode GLOB range handling, malformed UTF-16 insert guards, ESCAPE/rtrim rebind slices, JSON/VFS/WAL/B-tree clusters, and storage durability work.

Dependency closure:
- No new support component needed. The slice reuses native UTF-16 decode, NOCASE LIKE range planning, RTRIM expression keys, and current-source token replay diagnostics.
