# UTF-16 NOCASE LIKE RTRIM current-source next184

## Behavior

Adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`, a focused escaped
LIKE residual guard for UTF-16 `rtrim(option_name) COLLATE NOCASE` current-source
peer replay.

The slice covers copied Application `wp_options` names with literal `%` and `_`
characters stored as UTF-16LE, UTF-16BE, and UTF-8 text. A yielded peer token is
allowed to continue inside the duplicate RTRIM/NOCASE key group only when its
decoded token bytes still match the escaped LIKE residual after SQLite-style
ASCII-space RTRIM. Tokens that only match because `%` or `_` were accidentally
treated as wildcards force reprepare from the range start.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext184Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 70 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next184.php
application-utf16-nocase-like-rtrim-current-source-next184 self-test passed
```

## Non-overlap

This patch avoids accepted next181 same-key peer replay, next180 non-ASCII
NOCASE full-scan behavior, next178 canonical token validation, next177 Unicode
wildcard residuals, Unicode GLOB ranges, and UTF-16 malformed insert guards. It
adds only escaped LIKE residual validation for yielded UTF-16 RTRIM/NOCASE peer
tokens.

## Dependency closure

No new support component is needed. The slice reuses native UTF-16 decode,
escaped LIKE residual matching, RTRIM expression keys, and current-source peer
replay diagnostics.
