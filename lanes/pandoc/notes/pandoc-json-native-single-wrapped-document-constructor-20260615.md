# Pandoc JSON/native single-wrapped document constructor payloads

Slice: `plib-qhbu4`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for top-level tagged
`Pandoc` document payloads whose `[Meta, [Block]]` product is single-wrapped.
`PandocJsonReader` and `NativeReader` now accept payloads shaped like
`{"t":"Pandoc","c":[[meta, blocks]]}` while retaining the original document
constructor sidecar. `PandocJsonWriter` and `NativeWriter` continue to emit the
canonical filter-packet object shape with unwrapped `meta` and `blocks`.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4426 assertions, 0 failures`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 86315 assertions, 0 failures`

Accounting:

- rebased current main: `5068dd8e84`
- `phpPass`: `3660 -> 3661`
- `phpFail`: `0`
- upstream mapped cases: `3695 -> 3696`
- `mappedJsonNativeSingleWrappedDocumentConstructorCases`: `1`
- `jsonNativeSingleWrappedDocumentConstructorAssertions`: `40`
- `mappedJsonNativeConstructorCompletenessCases`: `34 -> 35`
- `jsonNativeConstructorCompletenessAssertions`: `575 -> 615`
