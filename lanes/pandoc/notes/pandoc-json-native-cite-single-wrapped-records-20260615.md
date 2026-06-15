# Pandoc JSON/native Cite single-wrapped records

Slice: `plib-sv2x8`
Rebased over current main `c4c39b3196`.

Implemented one bounded JSON/native AST constructor-completeness case for
single-wrapped `Cite` citation record lists.

`PandocJsonReader` and `NativeReader` now accept citation record payloads such
as `[[record]]` and `[[record1, record2]]`, normalize them into shared
`citation` and `citation_group` nodes, and keep the original record-list
wrapper as `citationRecordsNative`. `PandocJsonWriter` and `NativeWriter`
preserve that wrapped record-list sidecar only when regenerated citation
records still match; edited citation records emit canonical unwrapped lists and
drop stale edited-record sidecars.

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
  - `1 test files, 4431 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 86142 assertions, 0 failures`

Accounting:

- `phpPass`: `3652 -> 3653`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3689 -> 3690`
- `mappedJsonNativeCitePayloadCases`: `3 -> 4`
- `jsonNativeCitePayloadAssertions`: `135 -> 183`
- `mappedJsonNativeCiteSingleWrappedRecordCases`: `0 -> 1`
- `jsonNativeCiteSingleWrappedRecordAssertions`: `0 -> 48`
