# encoding-collation-affinity-like-current-source-next232

Status: focused PHP behavior growth for malformed-byte `LIKE` comparison after text affinity.

This slice adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext232Plan::optionRowValueMalformedByteLikePlan()`. It models SQLite `LIKE` over copied `wp_options.option_value` rows where legacy plugin payloads contain malformed UTF-8 bytes. Valid UTF-8 codepoints remain single pattern characters, malformed bytes are consumed one byte at a time, ASCII-only `NOCASE` still folds `Plugin_`/`plugin_`, and text affinity admits integers/floats/bools while BLOB/NULL operands remain SQL NULL for `LIKE`.

Application path: `application-encoding-collation-affinity-like-current-source-next232.php` covers a migration/import preview where a legacy plugin option value containing malformed UTF-8 is scanned with `CAST(option_value AS TEXT) COLLATE NOCASE LIKE ? ESCAPE ?`, and the next source truncates a formerly valid UTF-8 sequence into a malformed-byte match.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext232Test.php`
- Result: `1 test files, 65 assertions, 0 failures` with 65 PASS lines.
- `php lanes/libsqlite/examples/application-encoding-collation-affinity-like-current-source-next232.php --self-test`
- Result: `application-encoding-collation-affinity-like-current-source-next232 self-test passed`

PASS delta: `+65` focused PASS lines. `lane-status.json` `phpPass` moves from `113830` to `113895`. Mapped upstream coverage is unchanged because this reuses already mapped LIKE tokenization, text affinity, and current-source invalidation surfaces rather than claiming a fresh upstream inventory row.

Non-overlap: this avoids accepted UTF-16 malformed insert guards, Unicode GLOB ranges, UTF-16 NOCASE/RTRIM LIKE cursor fences through next228, dynamic LIKE affinity next99, and the current WAL/B-tree/JSON/VFS/SQL executor clusters. The new surface is malformed UTF-8 byte `LIKE` comparison and current-source invalidation after text affinity.

Dependency closure: no new support component is needed. The slice reuses native `LIKE` pattern tokenization, text affinity coercion, ASCII NOCASE folding, and current-source cursor diagnostics.

Next task: continue encoding work only on a non-overlapping collation/affinity predicate edge with focused tests; otherwise pivot to another current-source closure bucket.
