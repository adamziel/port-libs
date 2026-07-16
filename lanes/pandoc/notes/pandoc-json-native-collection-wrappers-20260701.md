# Pandoc JSON/native collection wrapper constructors

Slice: `pandoc-json-native-collection-wrappers-20260701`

Implemented a bounded JSON/native AST constructor completeness fix for
multi-item collection payloads that carry one extra outer list wrapper.
`PandocJsonReader` now accepts those wrappers for `BulletList`,
`OrderedList` item collections, `DefinitionList` item collections, and
`LineBlock` line collections while leaving existing ambiguous single-item
wrapper sidecars unchanged.

Focused coverage is in `PandocJsonNativeCollectionWrapperTest.php` and
exercises both `PandocJsonReader` and `NativeReader` JSON-native input,
plus `PandocJsonWriter` and `NativeWriter` preservation/regeneration paths.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/JsonReaderFormatConstructorTest.php lanes/pandoc/tests/PandocJsonRawTexInlineConstructorTest.php lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php lanes/pandoc/tests/NativeReaderEscapeTest.php`

No Pandoc binary, JSON filter runner, Haskell/Cabal runner, office suite,
browser, unzip/zip shellout, or external validator was invoked.
