# Pandoc JSON/native single-wrapped Str payloads

Slice: `plib-abxnl`

Area: Pandoc JSON/native AST constructor completeness.

This bounded slice accepts `Str` inline constructors whose text payload is
single-wrapped, for example `{"t":"Str","c":["Alpha"]}`. `PandocJsonReader`
and `NativeReader` normalize the wrapped text while preserving the original
constructor payload as native sidecar provenance. `PandocJsonWriter` and
`NativeWriter` reuse unchanged wrapped `Str` sidecars and regenerate canonical
direct string payloads after text edits, so stale reviewer sidecars do not
survive semantic changes.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 5295 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 88073 assertions, 0 failures`

Accounting:

- rebased current main: `968695228a`
- `phpPass`: `3716 -> 3717`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3737 -> 3738`
- `mappedJsonNativeConstructorCompletenessCases`: `52 -> 53` in lane status,
  `49 -> 50` in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: `1313 -> 1341` in lane
  status, `1206 -> 1234` in the upstream manifest
- `mappedJsonNativeSingleWrappedStrPayloadCases`: `0 -> 1`
- `jsonNativeSingleWrappedStrPayloadAssertions`: `0 -> 28`
