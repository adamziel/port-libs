# Pandoc JSON/native sidecar-free Table/Figure payloads

Bead: `plib-iki6d`
Base: current main `a3b5ce7b50`

## Scope

`PandocJsonWriter` now reuses current-shape `Table` and `Figure` native
constructor payloads whenever the existing reader-equivalence guard confirms the
shared AST value is unchanged. The old guard only reused these payloads when the
top-level native object carried extra sidecar keys.

This preserves sidecar-free current payload details that were previously
regenerated away, including tagged empty `TableHead`/`TableFoot` wrappers and
source `Para` image wrappers inside `Figure` bodies. Edited shared AST values
still regenerate canonical payloads and drop stale native wrappers.

Direct-format parity accounting is not affected; this is JSON/native AST
constructor-provenance output coverage only.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1533 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69804 assertions, 0 failures`

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
