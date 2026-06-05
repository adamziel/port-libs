# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T011736Z`
- Accepted base: `c6112ce2e1611534e43d39ec57fc44e1f843be3a`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for embedded MathML
formula objects:

- Maps `draw:frame/draw:object` references such as `./Object 1` to the
  package-local `Object 1/content.xml` part.
- Finds the first MathML `<math>` element in the object content and preserves
  raw MathML plus plain fallback text on a display `math` AST node.
- Reports ODT math nodes in `importReport.content.mathCount`.
- Normalizes leading `./` package part references used by ODF object links.
- Keeps formula object container manifest entries out of media inventory while
  still exposing real image/media resources.
- Lets `WordPressBlockWriter` render math nodes that already carry MathML
  without invoking MathML, TeX, browser, or office renderers.
- Updates the WordPress ODF handoff example self-test to cover embedded MathML
  and source annotations.

Source truth: upstream Pandoc `Text.Pandoc.Readers.ODT.ContentReader` at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` reads `draw:object` frame children
by loading the linked object `content.xml` and converting MathML to display math.
This PHP slice ports the package/content handoff contract but preserves MathML
directly instead of calling TeXMath or external converters.

This is bounded to ODF package/content XML mapping. It does not invoke Pandoc,
LibreOffice, Word, zip/unzip, browser renderers, external MathML/TeX
converters, TeX/PDF engines, Haskell runners, or online services.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 193 assertions, 0 failures`
- Red-first after adding the MathML object test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 194 assertions, 1 failures`
  - Expected failure: `draw:object` MathML frames were ignored.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 216 assertions, 0 failures`
- Compatibility check:
  `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - `1 test files, 81 assertions, 0 failures`
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `19 test files, 5094 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `493 -> 494`.
- `benchmarkDenominator.mapped`: `967 -> 968`.
- Focused `OdfReaderTest.php`: `8 -> 9` cases, `193 -> 216`
  assertions.
- `odfOpenDocumentCoreCases`: `10 -> 11`.
- `mappedOdfOpenDocumentCoreCases`: `10 -> 11`.
- `odfOpenDocumentCoreAssertions`: `217 -> 240`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local MathML object parsing is not blocked by that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/styles/meta/media/table/
list/annotation/text-box/image, footnote/endnote, bookmark-reference,
tracked-change, and encrypted-manifest clusters. It adds only bounded
OpenDocument `draw:object` MathML formula handoff and related media-inventory
classification.

Remaining ODT follow-up stays separate: charts, forms, linked sections, richer
style cascades, embedded-object preview policy beyond MathML, page-style
policy, table continuation semantics, export-side ODT writing, and full Pandoc
ODT reader parity.
