# encoding-nocase-rtrim-glob-affinity-current-source-next142

Behavior slice: current-source `wp_options.option_name` GLOB planning when the source cursor has UTF text bytes, TEXT/NUMERIC affinity coercion, and a NOCASE or RTRIM index collation.

Non-overlap:

- Avoids accepted Unicode GLOB range behavior by focusing on NOCASE/RTRIM collation fallback, not Unicode character-class range matching.
- Avoids accepted RTRIM LIKE/GLOB prefix-range materialization by proving that GLOB residual matching stays bytewise and NOCASE/RTRIM keys are only ordering keys for this cursor.
- Avoids accepted UTF-16 malformed guard by reusing the existing decoder and only recording malformed current-source invalidation evidence.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNocaseRtrimGlobAffinityCurrentSourceNext142Test.php`
- `php lanes/libsqlite/examples/wordpress-nocase-rtrim-glob-affinity-current-source-next142.php`
- `php -l lanes/libsqlite/src/SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteNocaseRtrimGlobAffinityCurrentSourceNext142Test.php`
- `php -l lanes/libsqlite/examples/wordpress-nocase-rtrim-glob-affinity-current-source-next142.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this reuses native UTF text decoding, affinity comparison, BINARY/NOCASE/RTRIM collation keys, and existing bytewise GLOB residual matching.
