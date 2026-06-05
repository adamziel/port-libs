# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T014653Z`
- Accepted base: `1a86b009041f206dcbfd3ee76c6da99bd9edeeb9`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for reference marks:

- Maps `text:reference-mark` and `text:reference-mark-start` to empty anchor
  span nodes with stable package-local ids and `odf-reference-mark` review
  metadata.
- Skips `text:reference-mark-end` after preserving the start anchor so range
  text remains normal document content.
- Maps `text:reference-ref` to internal link nodes targeting the reference mark
  id, preserving `text:ref-name` and `text:reference-format` as review
  attributes.
- Adds `referenceMarkCount` and `referenceReferenceCount` to the ODT import
  report content summary.
- Updates the WordPress ODF smoke to prove reference-mark anchors and
  reference-ref internal links survive into rendered blocks.

Source truth: upstream Pandoc
`Text.Pandoc.Readers.ODT.ContentReader` at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` includes ODT matchers for
reference starts and reference refs in paragraph, span, link, and heading
inline content. This PHP slice ports the bounded internal-anchor/link handoff
contract without invoking Pandoc or office tooling.

## Evidence

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 216 assertions, 0 failures`
- Red-first after adding the reference-mark expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 218 assertions, 1 failures`
  - Expected failure: `text:reference-mark*` and `text:reference-ref` nodes
    were ignored.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 244 assertions, 0 failures`
- ODF/ODT compatibility check:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php`
  - `2 test files, 325 assertions, 0 failures`
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `19 test files, 5406 assertions, 0 failures`
- Syntax checks:
  - `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`: no syntax errors.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `512 -> 513`.
- `benchmarkDenominator.mapped`: `987 -> 988`.
- Focused `OdfReaderTest.php`: `9 -> 10` cases, `216 -> 244` assertions.
- `odfOpenDocumentCoreCases`: `10 -> 11`.
- `mappedOdfOpenDocumentCoreCases`: `10 -> 11`.
- `odfOpenDocumentCoreAssertions`: `217 -> 245` in the current manifest
  counter after applying this slice's `+28` focused assertion delta.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local reference mark/reference-ref parsing is not blocked by
that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/styles/meta/media/table/
list/annotation/text-box/image, footnote/endnote, bookmark-reference,
tracked-change, encrypted-manifest, and MathML object clusters. It adds only
bounded OpenDocument reference-mark anchor and reference-ref internal-link
handoff.

Remaining ODT follow-up stays separate: charts, forms, linked sections, richer
style cascades, embedded-object preview policy beyond MathML, page-style
policy, table continuation semantics, export-side ODT writing, and full Pandoc
ODT reader parity.
