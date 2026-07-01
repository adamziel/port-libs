# Pandoc JSON Null Block Constructor

The strict `JsonReader`/`JsonWriter` pair now round-trips Pandoc's nullary
`Null` block constructor as the shared `null_block` AST node.

This brings the current JSON filter reader/writer path in line with the
constructor-preserving `PandocJsonReader`, `NativeReader`, `PandocJsonWriter`,
and `NativeWriter` paths, which already handled `Null`.

Validation:

- `php -l lanes/pandoc/src/JsonReader.php`
- `php -l lanes/pandoc/src/JsonWriter.php`
- `php -l lanes/pandoc/tests/JsonReaderWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderWriterTest.php`
  (45 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php`
  (60 assertions, 0 failures after rebase)
- A broader JSON/native smoke run was attempted with
  `JsonReaderWriterTest.php`, `PandocJsonNativeAstTest.php`,
  `NativeReaderTest.php`, and `PandocNativeWriterJsonProvenanceTest.php`;
  the new strict JSON test passed, while existing unrelated Markdown,
  WordPress, and LaTeX handoff assertions failed.

No external Pandoc or validator tooling was used.
