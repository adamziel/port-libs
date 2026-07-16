# UTF-16 NOCASE LIKE RTRIM Current Source Next178

## Behavior

Adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`, a current-source
replay diagnostic for yielded Application `wp_options.option_name` cursors using:

- UTF-8 / UTF-16LE / UTF-16BE option-name bytes.
- `rtrim(option_name) COLLATE NOCASE LIKE ?` index keys.
- raw yielded-token bytes decoded back into the canonical RTRIM/ASCII-NOCASE
  key before replay.
- byte and encoding fingerprint validation inherited from next175.

This closes the edge where a yielded token persisted as raw UTF-16 text such as
`Plugin_Cache  ` would compare against sorted RTRIM/NOCASE keys without first
normalizing to `plugin_cache`, making resume-after-token decisions unsafe.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext178Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 77 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next178.php
application-utf16-nocase-like-rtrim-current-source-next178 self-test passed
```

## Non-Overlap

This is additive after accepted next175 byte/encoding token fingerprints,
next174 embedded-NUL residual checks, next171 duplicate RTRIM/NOCASE key
replay, Unicode GLOB ranges, and the UTF-16 malformed insert guard. It does not
change mapped upstream coverage; expected dashboard movement is `+77` focused
PHP PASS lines only.

## Dependency Closure

No new support component is needed. The slice reuses native UTF-16 decode,
RTRIM key construction, ASCII NOCASE folding, and the existing LIKE plan and
token-fingerprint replay diagnostics.
