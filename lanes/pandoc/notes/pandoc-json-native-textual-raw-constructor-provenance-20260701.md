# Pandoc JSON/Native Textual Raw Constructor Provenance - 2026-07-01

## Scope

- `NativeReader` textual native input now records enclosing `RawBlock` and
  `RawInline` constructor provenance on raw AST nodes.
- Textual markdown/TeX raw format helper provenance is retained, so those
  `Format` helper payloads still round-trip through `PandocJsonWriter` and
  `NativeWriter`.
- Textual HTML-family and unsupported raw formats now keep raw constructor
  provenance while normalizing their reusable Pandoc JSON payloads back to
  string formats. This prevents native-text handoffs from leaking tagged
  `Format` objects for adjacent HTML aliases or disabled diagnostics.
- The focused regression verifies unchanged raw constructor preservation and
  stale native sidecar regeneration after raw text edits.

## Boundary

This stays inside native PHP JSON/native AST reader and writer paths. It does
not invoke Pandoc, office suites, TeX/browser engines, zip/unzip, Jupyter, Node
tooling, online services, or external validators.

## Verification

- `php -l lanes/pandoc/src/NativeReader.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeRawHtmlAdjacencyBoundaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonRawTexInlineConstructorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  remains baseline-red with six pre-existing failures outside this native-text
  raw payload normalization, including the separate MarkdownWriter unsupported
  raw fallback assertion in the larger raw-alias fixture.
