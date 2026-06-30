# JSON/native metadata constructor rendering - plib-ahmeu

## Scope

- Completed a bounded JSON/native constructor-completeness slice for textual native metadata output.
- `NativeWriter` now renders canonical Pandoc meta AST containers (`map`, `list`, `inlines`, `blocks`) as native `MetaMap`, `MetaList`, `MetaInlines`, and `MetaBlocks` constructors when writing standalone native text.
- Tagged native metadata payloads (`MetaMap`, `MetaList`, `MetaString`, `MetaBool`, `MetaInlines`, `MetaBlocks`) are rendered as native constructors instead of falling through to string/map fallback output.
- Raw ambiguous `raw_markdown` and `raw_tex` nodes sourced from `RawInline` constructors are classified as inline nodes for textual native metadata and mixed inline containers, while `RawBlock`-sourced nodes remain block-like.

## Validation

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- Isolated NativeReaderTest closure `writes canonical and tagged metadata constructors in textual native output`: 19 assertions, 0 failures.
- Full `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php` remains baseline-red with 6 unrelated existing failures; the new test passed in that full-file run.

No external Pandoc, office, TeX/browser, zip, Jupyter, Node, or validator tooling was used.
