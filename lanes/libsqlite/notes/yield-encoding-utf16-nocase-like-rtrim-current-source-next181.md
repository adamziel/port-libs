# UTF-16 NOCASE LIKE RTRIM Current-Source next181

- Slice: `encoding-utf16-nocase-like-rtrim-current-source-next181`.
- Behavior: adds stable same-key peer replay for `rtrim(option_name) COLLATE NOCASE LIKE ...` over UTF-8/UTF-16 current-source rows. A canonical yielded token with matching byte/encoding fingerprint can continue inside a duplicate RTRIM/NOCASE peer group using `(key,rowid)` ordering, while source/schema, token, malformed text, or peer-rowset changes force a reprepare.
- Application path: copied `wp_options` scans with plugin option names stored in mixed UTF-8/UTF-16 encodings can resume after yielding `plugin_cache` without duplicating the yielded row or skipping equal RTRIM/NOCASE peers.
- Non-overlap: avoids accepted next178 canonical token validation, next177 Unicode wildcard residuals, next171 duplicate-key invalidation, Unicode GLOB ranges, UTF-16 malformed insert guards, VFS/WAL/B-tree/JSON/planner surfaces.
- Dependency closure: no new support component needed; reuses native UTF-16 decode, SQLite LIKE residual matching, RTRIM expression keying, ASCII-only NOCASE folding, and next178 byte-token validation.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext181Test.php` -> `1 test files, 74 assertions, 0 failures` with 56 PASS lines.
  - `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next181.php` -> `application-utf16-nocase-like-rtrim-current-source-next181 self-test passed`.
  - Expected dashboard movement on acceptance: `phpPass` `85432 -> 85488`; mapped coverage unchanged at `614 / 1589`.
