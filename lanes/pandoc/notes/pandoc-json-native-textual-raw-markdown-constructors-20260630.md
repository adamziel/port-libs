# Pandoc JSON/native textual raw Markdown constructors

Area: Pandoc JSON/native AST constructor completeness.

## Slice

Textual Pandoc native `RawBlock` and `RawInline` constructors with Markdown-family
formats now map to the shared `raw_markdown` AST alias, matching the existing
Pandoc JSON reader behavior. Unsupported raw inline formats remain generic
`raw_inline` nodes.

Covered format aliases:
- `markdown+pipe_tables` block payloads
- `gfm` inline payloads
- unsupported `opml` inline payloads as generic raw inline

The regression also verifies that both NativeWriter JSON output and textual
native output round-trip these aliases without shelling out to Pandoc or any
external validator.

## Validation

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - 1 file
  - 371 assertions
  - 0 failures

## Status Delta

- `phpPass`: `471 -> 472`
- `phpFail`: `0`
