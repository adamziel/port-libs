# Source-neutral encoding defaults, dynamic slice 20260601T083152Z-0

Base accepted HEAD: `e307345b68a0844266e5b42b8d4ac54edb9f105d`

## Scope

Neutralized the small UTF-16 NOCASE LIKE RTRIM dynamic source-default surface for:

- `SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan.php`
- `SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextPlan.php`

The changed source now reports `key_name` expressions instead of `option_name`, and the directly coupled resume-token path in `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php` now reads `setting_id` and `key_name_bytes` for its `nextOneSixFive` decoded RTRIM key map. Direct tests and application examples were moved to generic setting/key names where those source paths accept generic rows.

The larger `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php` still contains later legacy `option_*` variants outside this bounded branch; those remain a follow-up cleanup target and were not added to the source-neutral default guard in this slice.

## Verification

Red-first evidence: the focused set failed before the final source fix because the resume-token branch still required `option_id` while the neutralized caller/test rows supplied `setting_id`.

Passing focused command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext166Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext182Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
```

Result: `5 test files, 201 assertions, 0 failures`.

Changed examples:

```bash
php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-resume-token-current-source-next.php --self-test
php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-escape-current-source-next166.php --self-test
php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-escape-current-source-next182.php --self-test
```

Result: all self-tests passed.

PHP lint passed for all changed PHP files:

- `lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan.php`
- `lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextPlan.php`
- `lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext166Test.php`
- `lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext182Test.php`
- `lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextTest.php`
- `lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php`
- `lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-resume-token-current-source-next.php`
- `lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-escape-current-source-next166.php`
- `lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-escape-current-source-next182.php`

`git diff --check -- lanes/libsqlite` passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This cleanup reuses the existing UTF-16 encode/decode cursor, ASCII NOCASE LIKE/RTRIM matching, and current-source resume/replay diagnostics.

## Follow-up

Continue source-neutral cleanup of the later `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php` variants that still expose `option_id`, `option_name_bytes`, or `option_name` in production-source internals.
