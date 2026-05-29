# encoding-utf16-pattern-nocase-like-rtrim-current-source-next159

Status: focused PHP behavior growth for UTF-16 encoded LIKE pattern and ESCAPE values with NOCASE residual matching and RTRIM index-range keys.

WordPress path: `wordpress-utf16-pattern-nocase-like-rtrim-current-source-next.php` models a copied `wp_options.option_name` prefix scan where the prepared LIKE pattern and ESCAPE value move from UTF-16LE bytes in the current source to UTF-16BE bytes in the next source. The scan keeps SQLite's two-stage behavior: the RTRIM index key admits space-padded candidates, while the NOCASE LIKE residual is evaluated against the untrimmed decoded text.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-pattern-nocase-like-rtrim-current-source-next.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextTest.php`
  - `1 test files, 85 assertions, 0 failures`
  - `76` PASS lines
- `php lanes/libsqlite/examples/wordpress-utf16-pattern-nocase-like-rtrim-current-source-next.php --self-test`
  - `wordpress-utf16-pattern-nocase-like-rtrim-current-source-next self-test passed`

Expected dashboard movement: `phpPass +76`, from `70146` to `70222`. Mapped upstream coverage remains `608 / 1589`; this is current-source PHP behavior over already mapped encoding, collation, LIKE, and current-source inventory rather than a fresh upstream manifest row.

Non-overlap: avoids accepted UTF-16 NOCASE/LIKE/RTRIM row-text next156, UTF-16 NOCASE LIKE malformed-row isolation next141, UTF-16 LIKE ESCAPE next143, UTF-16 pattern LIKE/GLOB affinity next114, Unicode GLOB ranges, UTF-16 malformed guards, VFS/WAL/B-tree/JSON/SQL executor clusters, and suite-runner evidence work. The new surface is decoding bound UTF-16 pattern and ESCAPE bytes across current/next sources, tracking pattern/escape byte and encoding invalidation, while reusing the accepted row scanner.

Dependency closure: no new support component is needed. The slice reuses native PHP UTF-16 text decode, RTRIM range keys, ASCII NOCASE LIKE residual matching, and current-source invalidation metadata.

Next task: continue encoding only on a non-overlapping malformed comparison, collation, affinity, or LIKE/GLOB edge with focused tests; otherwise pivot to a higher-yield current-source closure bucket.
