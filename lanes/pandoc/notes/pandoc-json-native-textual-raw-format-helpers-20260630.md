# Pandoc JSON/native textual raw Format helpers

Area: Pandoc JSON/native AST constructor completeness.

## Slice

Textual Pandoc native `RawBlock` and `RawInline` constructors now preserve the
`Format` helper parsed from `(Format "...")` as tagged native provenance on the
shared raw AST nodes. JSON/native writer handoff therefore emits tagged
`Format` helpers for textual raw block and inline constructors instead of
collapsing them to bare format strings.

The change covers:

- generic textual `RawBlock (Format "opml")`
- generic textual `RawInline (Format "opml")`
- HTML-family textual `RawInline (Format "html")`
- existing textual TeX raw inline handoff, now retaining `Format "tex"`

Generated shared-AST raw nodes without source helper provenance still emit the
existing canonical format string payloads.

## Validation

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`

Result:

- 2 files
- 6,631 assertions
- 0 failures

## Status Delta

- `phpPass`: `472 -> 473`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2313 -> 2314`
- `mappedJsonNativeTextualRawFormatHelperCases`: `1`

No Pandoc binary, Haskell/Cabal runner, TeX engine, browser, office suite,
external validator, online service, live provider, or network fetch was used.
