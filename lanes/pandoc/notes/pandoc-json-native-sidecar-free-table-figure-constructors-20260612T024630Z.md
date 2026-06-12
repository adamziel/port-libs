# Pandoc JSON/native sidecar-free Table/Figure constructors

Implemented a bounded JSON/native AST constructor completeness slice for current
Table and Figure payload reuse in the Pandoc JSON writer.

- `PandocJsonWriter` now preserves sidecar-free current empty `Table` and `Figure`
  native payloads when their tagged helper constructors still semantically match
  the shared AST.
- The reuse stays narrow: sidecar-bearing Table/Figure provenance keeps the
  existing broad payload behavior, while legacy untagged Figure caption forms and
  image-wrapper canonicalization still regenerate through the existing writer.
- The focused regression covers JSON-reader and NativeReader inputs, tagged
  `Caption`, `Just`, `ShortCaption`, `TableHead`, and `TableFoot` helper payloads,
  plus semantic edits that force regeneration.

Verification on current main `713ba0d252`:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 test file, 1461 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 69622 assertions, 0 failures
