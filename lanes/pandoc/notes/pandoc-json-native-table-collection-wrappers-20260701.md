# Pandoc JSON/native table collection wrappers

Slice: `plib-6wjw8`

`PandocJsonReader` now records single-wrapped current table body, row, head-row,
and cell collections as native sidecars, and `PandocJsonWriter`/`NativeWriter`
reuse those wrappers when the generated table helper payload is unchanged.

The focused regression covers JSON and native JSON readers, rebuilt table
sections with constructor/native attrs stripped, and preservation of wrapped
body, row, and cell collection payloads without invoking Pandoc, office suites,
TeX/browser engines, Node tooling, zip/unzip, or external validators.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeTableCollectionWrapperTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeTableCollectionWrapperTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeTableCollectionWrapperTest.php lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php lanes/pandoc/tests/PandocJsonSingleWrappedAttrTupleTest.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php`
