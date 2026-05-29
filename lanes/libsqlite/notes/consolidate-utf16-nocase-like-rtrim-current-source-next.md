# UTF-16 NOCASE LIKE RTRIM Current-Source Consolidation

Date: 2026-05-29

Slice: `consolidate-numbered-encoding-utf16-rtrim-big`

Consolidated the numbered `SQLiteUtf16NocaseLikeRtrimCurrentSourceNext*Plan`
production family into the canonical
`SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` class. Numbered production
files were removed; direct tests, examples, and exact dependent source
references now call the canonical class.

Variant entry methods with colliding old names were preserved with explicit
canonical names:

- `wordpressOptionNameSourceDeltaPlan`
- `wordpressOptionNameGenerationPlan`
- `wordpressOptionNameYieldReplayPlan`
- `wordpressOptionNameNonAsciiFullScanPlan`

Verification:

- `php tools/run-tests.php $(ls lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext*Test.php | sort)`:
  `57 test files, 4389 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNext163Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNext170Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext166Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext182Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNext174Test.php`:
  `5 test files, 349 assertions, 0 failures`
- `git diff --name-only --diff-filter=ACM -- 'lanes/libsqlite/*.php' 'lanes/libsqlite/src/*.php' 'lanes/libsqlite/tests/*.php' 'lanes/libsqlite/examples/*.php' | xargs -r -n1 php -l`:
  `121` changed PHP files linted with no syntax errors
- `for f in $(git diff --name-only --diff-filter=ACM -- lanes/libsqlite/examples | rg '\.php$' | sort); do php "$f" >/dev/null || exit 1; done`:
  example smokes passed
- `git diff --check -- lanes/libsqlite`: passed

Dependency closure: no new support component needed; this is a production
class-family consolidation that preserves the existing native UTF-16 decode,
RTRIM, NOCASE LIKE, escape, resume-token, and cursor invalidation behavior.

Non-overlap: this only consolidates the
`SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` numbered duplicate family and
does not consolidate the separate `Escape`, `Rhs`, `Nul`, or `ResumeToken`
production families.
