# Pandoc JSON/native target inline payload reuse slice

Date: 2026-06-12
Bead: plib-yxrvx
Area: JSON/native AST constructor completeness

## Scope

This slice preserves current `Link` and `Image` inline native constructor payloads when a shared AST handoff strips only the derived `targetNative` helper sidecar. The JSON and native writers treat `targetNative` as constructor provenance during native-payload reuse comparisons, matching existing handling for `native`, `constructor`, `attrConstructor`, and `attrNative`.

Edited URL/title targets still regenerate from current shared-AST fields and drop stale reviewer sidecars.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 test file, 1577 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 70511 assertions, 0 failures

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
