# Encoding UTF-16 NOCASE LIKE RTRIM current-source next158

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for
Application `wp_options` option-name scans that use
`rtrim(option_name) COLLATE NOCASE LIKE ...` over mixed UTF-8, UTF-16LE, and
UTF-16BE text sources.

The behavior is distinct from accepted UTF-16 malformed guards, Unicode GLOB
ranges, RTRIM/GLOB/NOCASE affinity, and current-source RTRIM LIKE/GLOB slices:
the usable index range is the ASCII NOCASE key of the RTRIM expression, while
the residual LIKE is evaluated against the trimmed expression value. It records
current/next decoded text, encoded bytes, malformed UTF-16 rowids, candidate
and matched rowsets, and invalidation reasons for source/schema/encoding/key
changes.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext158Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next158.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext158Test.php`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next158.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses native
UTF-16 decode, RTRIM expression handling, ASCII NOCASE LIKE prefix planning,
and current-source invalidation metadata.
