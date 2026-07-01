# Pandoc JSON/Native Textual Cite Provenance Sidecars - 2026-07-01

## Scope

- Extends the earlier textual native `Cite` constructor mapping by preserving
  source constructor provenance on the shared AST.
- `NativeReader` textual native input now attaches `constructor`/`native`
  sidecars for the top-level `Cite` inline and `citationConstructor`/
  `citationNative` sidecars for each textual `Citation` record.
- The reader also records `citationRecordsNative`, `citationPrefixNative`, and
  `citationSuffixNative` as Pandoc JSON-native payload arrays instead of
  normalized `AstNode` lists, so `PandocJsonWriter` and `NativeWriter` can
  reuse unchanged record payloads and regenerate canonical records after edits.

## Validation

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTextualCitationConstructorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextualCitationConstructorTest.php`
  passed with 1 file, 54 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextualCitationConstructorTest.php lanes/pandoc/tests/NativeWriterCitationGroupInlineTest.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php`
  passed with 4 files, 246 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  was attempted and remains red outside this slice with 1 file, 6,071
  assertions, 6 failures in existing raw/WordPress HTML/cite rendering
  expectations while the JSON/native citation constructor cases passed.
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php lanes/pandoc/tests/JsonReaderSingleWrappedConstructorCompatibilityTest.php`
  was attempted and remains red outside this slice with 3 files, 440
  assertions, 5 failures in existing Markdown/raw/table writer expectations
  while the JSON/native constructor wrapper cases passed.

## Boundary

No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine,
Typst, Jupyter, Node tooling, zip/unzip tools, validators, or live services were
invoked.
