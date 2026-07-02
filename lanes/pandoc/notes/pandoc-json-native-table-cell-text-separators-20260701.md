# Pandoc JSON/native table cell text separators

Slice: `plib-wbijv`

NativeWriter now routes `table_cell` children through the JSON-native inline
separator expansion path before handing the document to PandocJsonWriter. This
keeps table cell text such as `Cell before` as `Str`, `Space`, `Str` in
JSON/native output instead of emitting a single `Str` payload containing a
space when JSON output is forced by native provenance or `pandocApiVersion`.

Coverage:

- Added `expands table cell text separators in json native output` in
  `NativeTextInlineConstructorCompletenessTest.php`.
- The regression builds a JSON-native table document with inline table cell
  children and verifies the cell `Plain` payload uses explicit `Space`
  constructors and round-trips through `NativeReader`.

Validation:

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/NativeTextInlineConstructorCompletenessTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeTextInlineConstructorCompletenessTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/NativeTextInlineConstructorCompletenessTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php lanes/pandoc/tests/NativeTextInlineConstructorCompletenessTest.php`

Direct-format parity remains active. This slice only closes a bounded
JSON/native AST constructor completeness gap and does not claim broader Pandoc
format parity or use external Pandoc, office, TeX/browser, zip, Jupyter, Node,
or validator tooling.
