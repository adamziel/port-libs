# encoding-utf16-nocase-like-rtrim-current-source-next191

Status: focused PHP behavior growth for UTF-16 prepared `LIKE` pattern rebinding over `rtrim(option_name) COLLATE NOCASE` current-source scans.

Application path: `application-utf16-nocase-like-rtrim-current-source-next191.php` models copied `wp_options.option_name` rows where a prepared statement is rebound from UTF-16LE `plugin!_%` to UTF-16BE `plugin!_cache%`. The old broad escaped-wildcard prefix cursor admits `plugin_config` and `plugin_other`; the next source must reprepare because the decoded pattern narrows the prefix range and residual membership, while byte-order-only rebinds can retain residual semantics.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext191Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next191.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext191Test.php`
  - `1 test files, 81 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next191.php --self-test`
  - `application-utf16-nocase-like-rtrim-current-source-next191 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +81`, from `91519` to `91600`. Mapped upstream coverage remains `617 / 1589`; this is current-source PHP behavior over already mapped UTF-16, NOCASE/RTRIM, LIKE, and current-source inventory rather than a fresh upstream manifest row.

Non-overlap: avoids accepted next141/156/158/162/163/164/165/168/174/180/183/185/186/187 UTF-16 NOCASE/RTRIM LIKE surfaces including malformed-row isolation, row-side scans, prepared-pattern byte normalization, RHS `rtrim(pattern)`, resume tokens, `case_sensitive_like` toggles, NUL behavior, non-ASCII prefixes, deleted-token replay, resume-boundary handling, and dangling ESCAPE residuals. The new surface is decoded prepared pattern or ESCAPE rebinding across UTF-16 byte orders where the next decoded pattern narrows the escaped-wildcard prefix range.

Dependency closure: no new support component is needed. The slice reuses lane-local native UTF-16 decode/encode helpers, prepared LIKE pattern normalization, ASCII NOCASE prefix range planning, RTRIM expression keys, and current-source invalidation diagnostics.

Next task: continue encoding work only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
