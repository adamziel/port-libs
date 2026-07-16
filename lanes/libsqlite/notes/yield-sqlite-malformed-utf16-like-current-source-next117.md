# Malformed UTF-16 LIKE/GLOB Current Source Next117

## Behavior

`SQLiteMalformedLikeGlobSourceNextPlan` now reports `currentMalformedCandidateRowids` and `nextMalformedCandidateRowids`: malformed UTF-16 rows whose raw encoded bytes still fall under the literal LIKE/GLOB prefix that an index/source cursor would seek before text decoding fails.

This keeps malformed-row diagnostics broad (`currentMalformedRowids`) while giving the current/next invalidation path a narrower prefix-candidate set for Application-style `wp_options.option_name LIKE 'plugin%'` and `GLOB 'plugin_*'` scans.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteMalformedUtf16LikeCurrentSourceNext117Test.php`
- Result: `1 test files, 51 assertions, 0 failures`

Expected dashboard movement: `phpPass` +51, from accepted `44622` to `44673`. No mapped upstream denominator change claimed.

## Application Smoke

- `php lanes/libsqlite/examples/application-malformed-utf16-like-current-source-next117.php`

The smoke covers copied `wp_options` rows with malformed UTF-16LE plugin/theme option names and verifies prefix-scoped plugin candidates across current/next source cookies.

## Non-Overlap

Avoids accepted UTF-16 malformed record-serialization guard, accepted Unicode GLOB range matching, and accepted batch109-113 UTF-16 affinity LIKE/GLOB/RTRIM/NOCASE current-source predicates. This patch only adds malformed raw-prefix candidate classification inside the existing current/next malformed LIKE/GLOB plan.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP encoding source cursor, LIKE/GLOB matchers, and UTF-16 decoder diagnostics.
