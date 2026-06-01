# Source-neutral encoding row-key defaults

Slice: `source-neutral-src-domain-specific-option-classes-dynamic-20260601T022639Z-0`

Production source cleaned:

- `SQLiteNocaseRtrimLikeCurrentSourceNextPlan.php`
- `SQLiteUtf16LikeRtrimCurrentSourceNextPlan.php`
- `SQLiteUtf16RtrimLikeCurrentSourceNextPlan.php`

The owned encoding LIKE/RTRIM row-key helpers now consume generic
`setting_id`, `key_name_bytes`, and `load_policy` fixture keys. Direct focused
tests and self-test examples were migrated to the same generic row contract and
the current `keyValueRowKeyPlan()` method name. `SQLiteEncodingSourceNeutralDefaultsTest.php`
now guards these three source files.

Verification:

- `php -l` on all changed PHP files: pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNocaseRtrimLikeCurrentSourceNext134Test.php lanes/libsqlite/tests/SQLiteUtf16LikeRtrimCurrentSourceNext137Test.php lanes/libsqlite/tests/SQLiteUtf16RtrimLikeCurrentSourceNext121Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `5 test files, 236 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-nocase-rtrim-like-current-source-next134.php --self-test`: pass
- `php lanes/libsqlite/examples/application-utf16-like-rtrim-current-source-next137.php --self-test`: pass
- `php lanes/libsqlite/examples/application-utf16-rtrim-like-current-source-next121.php --self-test`: pass
- `git diff --check -- lanes/libsqlite`: pass

Dependency closure: no new support component is needed; this is a
behavior-preserving source-neutral API cleanup over existing UTF text decoding,
LIKE planning, and row-key comparison helpers.

Follow-up: continue the same cleanup pattern across remaining older source
plans that still expose legacy setting-row fixture names.
