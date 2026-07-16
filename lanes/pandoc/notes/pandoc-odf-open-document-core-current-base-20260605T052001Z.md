# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T052001Z`
- Accepted base: `689a1d63f07b4ac9ee6dd4da0f28692001c18354`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for bounded inline
text fields:

- Maps `text:variable-set`, `text:variable-get`, `text:user-field-get`,
  `text:page-number`, `text:page-count`, `text:date`, `text:time`, and
  `text:expression` to visible `odf-field` review spans.
- Preserves field source metadata including `text:name`, `text:formula`,
  `office:value-type`, `office:value`, `office:string-value`,
  `text:date-value`, `text:time-value`, `text:select-page`,
  `text:page-adjust`, `text:fixed`, and `style:data-style-name`.
- Uses visible field body text first, then bounded field value attributes as a
  fallback, so WordPress import review does not silently drop source field
  values.
- Adds recursive `importReport.content.fieldCount` evidence.
- Updates the WordPress ODF handoff example so variable, user, and page-number
  fields survive into rendered block HTML.

This ports the bounded inline-content contract for OpenDocument text fields
without evaluating fields or resolving declarations. It keeps calculation,
field-declaration lookup, and full Pandoc ODT parity as follow-up work.

## Evidence

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 402 assertions, 0 failures`
- Red-first after adding the ODF field expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 405 assertions, 1 failures`
  - Expected failure: variable/user/page/date fields were dropped from
    paragraph text and no field AST spans or `fieldCount` existed.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 433 assertions, 0 failures`
- ODF/ODT compatibility check:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php`
  - `2 test files, 528 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `649 -> 650`.
- `benchmarkDenominator.mapped`: `1125 -> 1126`.
- Focused `OdfReaderTest.php`: `16 -> 17` cases, `402 -> 433` assertions.
- ODF manifest subcounters now reflect the current focused ODF file:
  `17` mapped cases / `433` assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local field preservation is not blocked by that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/manifest/media/table/list
restart/list-continuation/annotation/text-box/image, footnote/endnote,
bookmark-reference, reference-mark/reference-ref, sequence, bibliography mark,
tracked-change, encrypted-manifest, MathML object, linked/protected section,
page-layout/master-page, and image-dimension clusters. It adds only bounded
OpenDocument inline text-field handoff.

Remaining ODT follow-up stays separate: text field declaration resolution,
field calculation, charts, forms, richer style cascades, embedded-object
preview policy beyond MathML, table continuation semantics, export-side ODT
writing, and full Pandoc ODT reader parity.
