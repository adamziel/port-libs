# Outline Type Operand Boundary Current-Base Slice

Session: `port-dev-markerpdf-outline-meta-20260608T151619Z`
Base accepted HEAD: `9b7dedf8f156ee7a192d9054f47ee79347ca34c8`
Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260608T151619Z`

## Source Truth

- Upstream `sddai/markerPDF` delegates TOC extraction to the native PDF document object via `doc.get_toc(max_depth=15)` and returns title, level, and zero-based page index rows.
- This no-GPU PHP port cannot use the upstream model/OCR stack for outline recovery, so the native parser must fail closed on malformed outline dictionaries before bookmark rows, navigation review rows, or remote action rows are promoted.

## Behavior

- Added a native outline item `/Type` operand boundary.
- Before this patch, `/Type /Outline 99 0 R /Title (...)` was treated as an acceptable outline item because the first name token was not a known non-outline type. The full document metadata path imported that malformed row and its stale sibling, and the lightweight TOC/navigation path exposed both titles.
- After this patch, outline item `/Type` values with trailing top-level operands stop traversal before the malformed item. The first trusted row remains importable, and the stale remote GoToR action payloads behind the malformed boundary stay out of document metadata, TOC, navigation review, and visible WordPress text.

## Evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTypeOperandBoundaryCurrentBaseTest.php
```

Result before fix: `1 test files, 9 assertions, 2 failures`; document metadata imported `item_count=3`, and lightweight TOC included `Malformed Type Operand Appendix` plus `Untrusted Tail After Type Operand`.

Focused after fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTypeOperandBoundaryCurrentBaseTest.php
```

Result: `1 test files, 40 assertions, 0 failures`.

Related outline traversal family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTypeOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataItemTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataNextOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootTraversalOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataCountOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php
```

Result: `6 test files, 357 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-type-operand-boundary-currentbase.php
```

Result: exits `0`; reports `imported_item_count=1`, `remote_action_count=0`, `stale_outline_type_operand_excluded=true`, `stale_action_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This slice does not repeat the accepted direct `/Type /Annot` spoof boundary, malformed `/Next` operand boundary, root `/First` or `/Last` operand boundary, root/item `/Count` operand boundary, `/Prev` backlink boundary, or runtime MPS preflight slice. It covers only malformed outline item `/Type` values that contain trailing top-level operands.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF object parser and existing top-level dictionary operand-boundary helpers. GPU/model execution, OCR, external PDF tools, and live upstream model runners remain intentionally out of scope for this markerPDF lane slice.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior: outline root `/Type` operand boundaries, destination action operand boundaries, page tree/catalog metadata ownership, CMap/font edge cases, xref repair, annotations/forms, or image/filter metadata.
