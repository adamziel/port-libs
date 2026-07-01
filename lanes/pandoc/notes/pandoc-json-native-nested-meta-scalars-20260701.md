# Pandoc JSON/native nested metadata scalar constructors

Slice: `plib-rlufm`

`PandocJsonReader` and `PandocJsonWriter` now treat singleton-wrapped
`MetaString` and `MetaBool` scalar payloads recursively, matching the existing
behavior for `Str`, `Format`, and numeric table helper scalar payloads. This
allows metadata scalar constructors such as `c=[[...]]` to be read, preserved
unchanged through JSON/native writer paths, and regenerated canonically after
the value is edited.

This is JSON/native AST constructor compatibility only. No direct-format parity
or lane-status counters were changed.

Validation:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNestedMetaScalarConstructorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNestedMetaScalarConstructorTest.php`
  passed: 1 file, 31 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNestedMetaScalarConstructorTest.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/JsonReaderSingleWrappedConstructorCompatibilityTest.php`
  passed: 3 files, 115 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  remains baseline-red with 1 file, 6071 assertions, 6 unrelated existing
  failures in raw HTML-family alias handling, WordPress attribute rendering,
  figure caption handoff, and CSL citation rendering.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNestedMetaScalarConstructorTest.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/JsonReaderSingleWrappedConstructorCompatibilityTest.php`
  remains baseline-red because `NativeReaderTest.php` has 5 unrelated existing
  failures.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, Node tooling, zip/unzip tools, or external
validators were invoked.
