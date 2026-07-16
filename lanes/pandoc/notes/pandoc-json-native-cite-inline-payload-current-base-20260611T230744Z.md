# Pandoc JSON/native Cite inline payload current-base slice

- Bead: `plib-hc1s5`
- Base: current main `9e53a22c9b4c`
- Scope: Pandoc JSON/native AST constructor completeness, limited to unchanged current `Cite` inline native payload reuse in the Pandoc JSON writer.

## Handoff

`PandocJsonReader` and `NativeReader` already preserve current `Cite` inline payloads plus the original citation record payload on AST nodes. This slice lets `PandocJsonWriter` reuse unchanged current `Cite` payloads under the existing reader-equivalence guard, so inert citation record provenance and source key order survive pass-through handoff.

Edited citation suffix/text still regenerates a fresh `Cite` record from semantic AST fields, preventing stale inert fields from leaking into changed output.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` -> 1 test file, 1159 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 67154 assertions, 0 failures

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
