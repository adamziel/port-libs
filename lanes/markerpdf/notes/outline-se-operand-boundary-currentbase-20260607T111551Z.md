# Outline SE Operand Boundary Current Base

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260607T111551Z`

Base accepted HEAD: `f0ab63b0aec4070b72a5ad36f42b8b417227d7b2`

## Source Truth

- Upstream markerPDF is pinned in the lane manifest at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` treats PDF outline rows as navigation metadata, separate from extracted page text.
- PDF outline item `/SE` is a structure-element association for bookmark review metadata. Like catalog `/Metadata` and outline `/Metadata`, it is a metadata trust boundary: extra top-level operands before the next dictionary key make the association ambiguous.
- Under the current no-GPU markerPDF scope, this patch only changes native PHP searchable-PDF parsing and review metadata. It does not run PDF actions, OCR, pypdfium/PDFium, PIL rendering, Surya, Texify, Torch, or external PDF tools.

## Red Evidence

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineStructureElementOperandBoundaryCurrentBaseTest.php
```

failed because malformed `/SE 60 0 R 61 0 R /A ...` produced no `structure_element_boundary_review` and still allowed the first structure element to be treated as the outline-owned structure metadata:

```text
1 test files, 17 assertions, 2 failures
```

## Implementation

- `PdfMetadataExtractor` now detects outline item `/SE` values with extra top-level operands before the next dictionary key.
- Malformed `/SE` values emit a payload-free `structure_element_boundary_review` row with:
  - selected object/generation,
  - trailing reference object numbers,
  - operand shapes,
  - `structure_element_promoted=false`.
- Document-outline summaries now include `structure_element_boundary_review_count` and selected/trailing object summaries when malformed `/SE` operands are present.
- `PdfOutlineExtractor` now carries `structure_element_boundary_review` from document-outline metadata into navigation and action review rows, using the same review-only handoff path as outline `/Metadata`.
- Valid `/SE` structure elements and action-shaped `/SE` rejection behavior remain unchanged.
- Added `wordpress-pdf-outline-se-operand-boundary-currentbase.php` to prove the WordPress import path preserves visible page text and navigation while keeping selected/trailing StructElem titles and attachment payloads out of outline-owned metadata and paragraphs.

## Verification

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineStructureElementOperandBoundaryCurrentBaseTest.php
```

Result before fix:

```text
1 test files, 17 assertions, 2 failures
```

Focused after fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineStructureElementOperandBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 71 assertions, 0 failures
```

Adjacent outline structure/navigation gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineStructureElementOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineStructureElementMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineStructureElementActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStructureNavigationCurrentBaseTest.php
```

Result:

```text
4 test files, 227 assertions, 0 failures
```

Adjacent navigation enrichment gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataNavigationReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineStructureDestinationPageContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionPageLabelStructureCurrentBaseTest.php
```

Result:

```text
3 test files, 186 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-se-operand-boundary-currentbase.php
```

The smoke exits 0 and emits `boundary_status=rejected_malformed_outline_item_structure_element_operand`, `selected_structure_reference=60`, `trailing_structure_references=[61]`, `structure_element_promoted=false`, `action_rows_carry_boundary_review=true`, `owned_review_payload_omitted=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Behavior tests move `2845 -> 2847` pass / `0` fail in lane status.
- WordPress scenarios move `2386 -> 2387`.
- New focused assertion file: `71` assertions.

## Non-Overlap

This does not repeat accepted outline `/Metadata` operand boundaries, outline `/Metadata` stream generation review, root stream rejection, duplicate key summaries, valid `/SE` structure-element metadata, action-shaped `/SE` rejection, destination/action context, named destination review, or target-page tagged-content propagation. The bounded behavior here is only malformed outline item `/SE` operands with hidden extra top-level structure references.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, top-level dictionary operand scanner, structure-element review collector, outline metadata summary, navigation review handoff, and visible-text extractor. Full upstream markerPDF runner parity remains gated by pdftext, pypdfium/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were run for this bounded PHP slice.
