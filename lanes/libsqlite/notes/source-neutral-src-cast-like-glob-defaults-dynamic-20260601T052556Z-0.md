# Source-Neutral CAST/LIKE/GLOB Defaults Cleanup

Slice: `source-neutral-src-cast-like-glob-defaults-dynamic-20260601T052556Z-0`

Base accepted HEAD: `f21524404044b11f3b8895597ad5fc6ac48001c6`

## Changes

- Neutralized the remaining owned UTF-16/RTRIM/NOCASE LIKE/GLOB source defaults from legacy option-shaped row keys to generic `setting_id`, `key_name_bytes`, and `key_value_bytes`.
- Updated directly coupled tests and application examples to use generic module/settings fixture data and neutral method calls.
- Extended `SQLiteEncodingSourceNeutralDefaultsTest.php` to guard the newly neutralized source files.

## Verification

- `php -l` on changed PHP source, test, and example files.
- Focused tests:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNocaseRtrimGlobAffinityCurrentSourceNext142Test.php lanes/libsqlite/tests/SQLiteRtrimGlobNocaseAffinityCurrentSourceNext149Test.php lanes/libsqlite/tests/SQLiteUtf16RtrimLikeGlobCurrentSourceNext128Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseGlobAffinityCurrentSourceNext148Test.php lanes/libsqlite/tests/SQLiteUtf16RtrimLikePatternCurrentSourceNext138Test.php lanes/libsqlite/tests/SQLiteUtf16LikeEscapeCurrentSourceNext143Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext158Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext161Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext164Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNext163Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php`
  - Result: `11 test files, 803 assertions, 0 failures`.
- API guard:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 5 assertions, 0 failures`.
- Example smokes:
  - `php lanes/libsqlite/examples/application-nocase-rtrim-glob-affinity-current-source-next142.php --self-test`
  - `php lanes/libsqlite/examples/application-rtrim-glob-nocase-affinity-current-source-next149.php --self-test`
  - `php lanes/libsqlite/examples/application-utf16-like-escape-current-source-next143.php --self-test`
  - `php lanes/libsqlite/examples/application-utf16-nocase-glob-affinity-current-source-next148.php --self-test`
  - `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-rhs-current-source-next163.php --self-test`
  - `php lanes/libsqlite/examples/application-utf16-rtrim-like-glob-current-source-next128.php --self-test`
  - `php lanes/libsqlite/examples/application-utf16-rtrim-like-pattern-current-source-next138.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The cleanup reuses the existing PHP UTF text decoder, LIKE/GLOB range planners, RTRIM/NOCASE collation helpers, and affinity coercion paths.
