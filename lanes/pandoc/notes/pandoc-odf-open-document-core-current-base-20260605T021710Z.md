# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T021710Z`
- Accepted base: `933851363ca6dbb278e21cd30d6a7d0da7e92a78`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for `text:sequence`
fields:

- Maps `text:sequence` in paragraph, heading, and nested inline content to an
  `odf-sequence` review span while preserving the visible sequence text.
- Preserves sequence metadata from `text:name`, `text:formula`,
  `text:ref-name`, and `text:num-format` as `data-odf-sequence-*`
  attributes for WordPress import review.
- Adds `sequenceCount` to `importReport.content`.
- Updates the WordPress ODF handoff example self-test to prove sequence fields
  survive into rendered blocks.

Source truth: upstream Pandoc
`Text.Pandoc.Readers.ODT.ContentReader` at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` includes a `read_text_seq` matcher
for `text:sequence` and includes that matcher in paragraph content. This PHP
slice ports the bounded inline content handoff and keeps extra ODF sequence
metadata for reviewer tooling.

## Evidence

- Red-first after adding the sequence expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 247 assertions, 1 failures`
  - Expected failure: sequence text was dropped from the paragraph as
    `Caption : Hero image.`
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 263 assertions, 0 failures`
- ODF/ODT compatibility check:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php`
  - `2 test files, 344 assertions, 0 failures`
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `19 test files, 5683 assertions, 0 failures`
- Syntax checks:
  - `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`: no syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `530 -> 531`.
- `benchmarkDenominator.mapped`: `1008 -> 1009`.
- Focused `OdfReaderTest.php`: accepted prior ODF note recorded
  `1 test files, 244 assertions, 0 failures`; this slice now passes
  `1 test files, 263 assertions, 0 failures`.
- `odfOpenDocumentCoreCases`: `10 -> 11`.
- `mappedOdfOpenDocumentCoreCases`: `10 -> 11`.
- `odfOpenDocumentCoreAssertions`: `217 -> 236`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local `text:sequence` parsing is not blocked by that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/styles/meta/media/table/
list/annotation/text-box/image, footnote/endnote, bookmark-reference,
reference-mark/reference-ref, tracked-change, encrypted-manifest, and MathML
object clusters. It adds only bounded OpenDocument `text:sequence` inline
field handoff and related import-report metadata.

Remaining ODT follow-up stays separate: charts, forms, linked sections, richer
style cascades, embedded-object preview policy beyond MathML, page-style
policy, table continuation semantics, export-side ODT writing, and full Pandoc
ODT reader parity.
