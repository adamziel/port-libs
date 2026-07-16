# Outline SE Action Boundary Current Base

## Slice

- Session: `port-dev-markerpdf-outline-meta-20260605T083557Z`
- Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260605T083557Z`
- Accepted base: `ae3acffe941d5742b599198b01a92e39e8c7ced6`
- Lane scope: native no-GPU markerPDF parser/converter behavior under `lanes/markerpdf/**`

## Source Truth

PDF action dictionaries and structure-element dictionaries both use `/S`, but
with different meanings. For an action dictionary `/S` is the action subtype
such as `/GoTo`, `/URI`, or `/JavaScript`; for a structure element it is the
structure type/role and is normally paired with `/Type /StructElem`. The port
must not let an outline item `/SE` reference convert action operands into
review-only structure metadata.

This slice keeps the existing native, no-model outline and tagged-structure
metadata path. It narrows only the untyped `/SE` dictionary bridge by rejecting
action-shaped dictionaries whose `/S` value is an action subtype and whose
top-level keys are action operands. Explicit `/Type /StructElem` dictionaries
remain accepted.

## Red-First Evidence

Before the patch, a focused inline fixture with:

- outline item `6 0 R` containing `/SE 12 0 R` and `/A 13 0 R`
- object `12 0 R` containing `<< /S /JavaScript /JS (...) /P 50 0 R /K 0 >>`
- action chain `13 0 R` `/S /GoTo` with `/Next` URI action

reported the action dictionary as outline structure metadata:

- `document_outline.items[0].structure_element_role = JavaScript`
- navigation outline `structure_element_role = JavaScript`

That was a false tagged-structure review row; the JavaScript object is an
action-shaped dictionary, not a structure element.

## Implementation

- `PdfMetadataExtractor::collectStructureDictionaryReview()` now preserves
  typed `/Type /StructElem` rows, rejects non-`StructElem` `/Type`
  dictionaries, and skips untyped action-shaped dictionaries when `/S` is an
  action subtype with action operands.
- The guard is intentionally local to untyped dictionaries. The adjacent typed
  `/Type /StructElem` outline `/SE` tests still pass and continue to carry
  role, MCID, language, title, alt text, and associated-file review metadata.

## Focused Evidence

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/tests/PdfOutlineStructureElementActionBoundaryCurrentBaseTest.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/examples/wordpress-pdf-outline-se-action-boundary-currentbase.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineStructureElementActionBoundaryCurrentBaseTest.php`
  - `1 test files, 48 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineStructureElementActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineStructureElementMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStructureNavigationCurrentBaseTest.php`
  - `3 test files, 156 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-outline-se-action-boundary-currentbase.php`
  - Emits one visible WordPress paragraph, one navigation item, `action_chain_types=["GoTo","URI"]`, `structure_element_rejected=true`, `navigation_structure_element_rejected=true`, `action_rows_omit_structure_context=true`, and no model/external-tool execution.

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted positive outline structure-element metadata,
outline action-chain review, structure navigation propagation, root type,
missing-parent, xref-owner, sibling traversal, named-destination, or titleless
outline metadata boundary slices. It adds only the negative bridge boundary
where `/SE` points at an action-shaped dictionary.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
dictionary tokenizer, outline extractor, named-destination resolver, and
tagged-structure metadata review path. GPU/OCR/model execution, PDFium, PIL,
and external PDF tools remain intentionally out of scope for this no-GPU
markerPDF lane.
