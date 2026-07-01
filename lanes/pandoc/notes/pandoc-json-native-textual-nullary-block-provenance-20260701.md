# Pandoc JSON/native textual nullary block provenance

`plib-dqcgt` preserves textual Native `HorizontalRule` and `Null` constructor
provenance directly on shared AST block nodes. `NativeReader` now attaches the
same clean `constructor` and `native` sidecars for these nullary block
constructors that JSON-native packets already receive.

This is additive review metadata only. `PandocJsonWriter` and `NativeWriter`
continue to emit the same sidecar-free current JSON/native constructors for
unchanged top-level and nested textual Native input.

Accounting:

- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedJsonNativeTextualNullaryBlockCases`: `0 -> 1`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.jsonNativeTextualNullaryBlockAssertions`: `0 -> 12`

Validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php`
  passed: 1 file, 88 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php lanes/pandoc/tests/NativeDefinitionTermConstructorTest.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php lanes/pandoc/tests/NativeWriterNoteLabelJsonModeTest.php lanes/pandoc/tests/NativeReaderTextualCitationConstructorTest.php`
  passed: 5 files, 162 assertions, 0 failures.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers, office
suites, TeX/PDF engines, Typst engines, Node tooling, zip/unzip tools, online
services, or external validators were invoked.
