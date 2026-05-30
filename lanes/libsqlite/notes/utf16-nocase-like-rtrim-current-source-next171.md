# UTF-16 NOCASE LIKE RTRIM Current Source Next171

## Scope

This slice adds duplicate-key replay diagnostics for
`rtrim(option_name) COLLATE NOCASE LIKE ...` over mixed UTF-8/UTF-16LE/UTF-16BE
Application option names. It focuses on the upstream behavior that index scan
resume tokens need the collated key plus rowid tie-breaker, and current-source
switches need byte/encoding fingerprints when multiple rows collapse to the
same ASCII NOCASE RTRIM key.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext171Test.php`
- Example smoke: `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next171.php --self-test`
- Expected dashboard movement: focused PHP PASS-line growth only; no mapped
  upstream denominator change claimed.

## Non-Overlap

This does not repeat accepted Unicode GLOB ranges, malformed UTF-16 insert
guards, RHS pattern trimming, generic LIKE prefix planning, or next167
non-ASCII full-scan fallback behavior. It adds duplicate RTRIM/NOCASE key
replay and current-source byte-fingerprint invalidation.

## Dependency Closure

No new support component is needed. The slice reuses native UTF-16 decode,
ASCII NOCASE LIKE matching, RTRIM expression keys, and lane-local
current-source replay diagnostics.
