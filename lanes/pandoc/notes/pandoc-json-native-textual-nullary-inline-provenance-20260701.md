# Pandoc JSON/native textual nullary inline provenance

`plib-dhqwi` preserves textual Native `SoftBreak` and `LineBreak` constructor
provenance directly on shared AST nodes. `NativeReader` now attaches the same
`constructor` and `native` sidecars for these nullary inline constructors that
JSON-native packets already receive, while `Space` remains represented through
validated `nativeInlineParts` on the shared text node.

This is additive review metadata only: `PandocJsonWriter` and `NativeWriter`
continue to emit the same `Str`, `Space`, `SoftBreak`, `LineBreak`, `Str`
constructor sequence for unchanged textual Native input.

Accounting:

- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedJsonNativeTextualInlinePartCases`: `1 -> 2`

Validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php`
  passed: 1 file, 10 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php lanes/pandoc/tests/NativeReaderEscapeTest.php lanes/pandoc/tests/NativeDefinitionTermConstructorTest.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php lanes/pandoc/tests/NativeWriterNoteLabelJsonModeTest.php`
  passed: 5 files, 45 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  remains baseline-red with 1 file, 6038 assertions, 9 unrelated existing
  failures; the constructor-completeness paths exercised before this slice still
  pass.
- `php tools/run-tests.php lanes/pandoc/tests`
  remains baseline-red with 325 files, 121198 assertions, 9632 unrelated
  existing failures.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers, office
suites, TeX/PDF engines, Node tooling, zip/unzip tools, or external validators
were invoked.
