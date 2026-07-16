# UTF-16 Supplementary Wildcard Helper Consolidation

Consolidated the numbered private production helper group inside
`SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` into descriptive
`supplementaryWildcard*` helpers. The public entry point and observable
return keys, status strings, dependency strings, action text, and direct test
expectations are unchanged.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext219Test.php`
  - `1 test files, 74 assertions, 0 failures`
- `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -name 'SQLiteUtf16*Nocase*Rtrim*Test.php' -print | sort)`
  - `65 test files, 4967 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next219.php --self-test`
  - `application-utf16-nocase-like-rtrim-current-source-next219 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure: no new support component needed; this consolidation reuses
the existing native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM
expression keys, and Unicode character splitting.

Non-overlap: this is consolidation-only for the UTF-16 supplementary wildcard
private helper names. It does not alter accepted Unicode GLOB ranges,
malformed UTF-16 insert guards, or recent pager/STAT4/compound suffix cleanup
families.
