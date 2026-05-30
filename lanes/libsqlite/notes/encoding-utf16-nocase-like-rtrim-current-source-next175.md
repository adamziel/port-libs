# encoding-utf16-nocase-like-rtrim-current-source-next175

Status: focused PHP behavior growth for UTF-16 NOCASE LIKE/RTRIM current-source replay tokens.

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameTokenFingerprintPlan()`. It reuses the accepted next171 duplicate-key replay scan, then verifies the yielded token's stored byte and encoding fingerprint against the next source row before allowing replay to continue after the key/rowid token.

Application path: `application-utf16-nocase-like-rtrim-token-current-source-next175.php` models copied `wp_options.option_name` rows where the same rowid and RTRIM/NOCASE key survive a source transition, but the UTF-16 byte payload gains trailing spaces. SQLite's residual comparison still matches after `rtrim(option_name)`, but the prepared cursor must reprepare because the yielded token's byte fingerprint is stale.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext175Test.php`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-token-current-source-next175.php --self-test`

Expected dashboard movement: `phpPass +56`, from `81770` to `81826`. Mapped upstream coverage remains `613 / 1589`; this is focused current-source PHP behavior over already mapped UTF-16, NOCASE LIKE, RTRIM, and cursor replay inventory.

Non-overlap:

- Avoids accepted next171 duplicate-key replay, next173 byte-vs-semantic invalidation, next163 RHS RTRIM pattern semantics, next160 pattern-byte decoding, next156/158 UTF-16 row matching, malformed UTF-16 insert guards, Unicode GLOB ranges, JSON/VFS/WAL/B-tree/SQL executor clusters, and suite-runner evidence work.
- The new surface is specifically yielded-token byte/encoding fingerprint validation before current-source replay continues.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode, ASCII NOCASE LIKE/RTRIM matching, and current-source replay diagnostics.

Next task: continue encoding work only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text edge with focused tests; otherwise pivot to another high-yield closure bucket.
