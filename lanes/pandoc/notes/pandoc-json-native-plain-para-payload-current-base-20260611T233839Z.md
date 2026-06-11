# Pandoc JSON/native Plain and Para payload current-base slice

- Bead: `plib-8hcvb`
- Base: current main `d504ad446889`
- Scope: Pandoc JSON/native AST constructor completeness, limited to unchanged current `Plain` and `Para` block native payload reuse in the Pandoc JSON writer.

## Handoff

`PandocJsonReader` and `NativeReader` already retain full native payloads for `Plain` and `Para` blocks. This slice lets `PandocJsonWriter` reuse those unchanged current text-block payloads under the existing reader-equivalence guard, preserving inert review fields and source key order through pass-through handoff.

The reuse gate rejects known legacy nested shapes, including two-slot `Link`/`Image` payloads and five-slot legacy `Table` payloads, so old documents still normalize through the existing writer path. Edited text blocks regenerate fresh `Plain`/`Para` constructors instead of leaking stale inert provenance.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` -> 1 test file, 1179 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 67215 assertions, 0 failures

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
