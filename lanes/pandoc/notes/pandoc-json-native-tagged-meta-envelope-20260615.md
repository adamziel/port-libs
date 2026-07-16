# Pandoc JSON/native tagged Meta envelope

Area: Pandoc JSON/native AST constructor completeness.

This slice accepts tagged top-level `Meta` metadata envelopes in both JSON and
native readers. The readers normalize `Meta { unMeta = ... }` style payloads
into document metadata, preserve the top-level `Meta` native payload on the
document node, index root and child metadata constructor provenance, and keep
JSON/native writer output canonical as a regular metadata map.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Accounting delta after rebase onto current main `dd53ddb025`:

- `phpPass`: `3718 -> 3719`
- `phpFail`: `0`
- `mappedJsonNativeMetaConstructorCases`: `2 -> 3`
- `jsonNativeMetaConstructorAssertions`: `42 -> 81`
- `mappedJsonNativeConstructorCompletenessCases`: `53 -> 54` in lane status,
  `50 -> 51` in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: `1341 -> 1380` in lane
  status, `1234 -> 1273` in the upstream manifest
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3738 -> 3739`

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed 1 file, 5334 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed 46 files, 88147 assertions, 0 failures.
