# markerpdf outline metadata operand boundary current-base

- Session: `port-dev-markerpdf-outline-meta-20260606T035012Z`
- Base accepted HEAD: `e6e270a95e14f4f7d39cb5ce4b34b7a26d8a52c6`
- Slice: `markerpdf-outline-metadata-boundary-current-base-20260606T035012Z`

## Source truth

Upstream markerPDF receives outline/bookmark and document metadata through PDF parser layers rather than treating bookmark dictionaries as page text. PDF outline item `/Metadata` is only valid as metadata-stream review context when it points to a single indirect metadata stream. Array-valued operands and direct dictionaries are malformed for this boundary, so the PHP native parser must fail closed: keep the outline row reviewable, reject the operand as bookmark metadata, and avoid promoting any stream or inline dictionary payload into document metadata, TOC/navigation review text, or WordPress paragraphs.

## Implementation

- `PdfMetadataExtractor::documentOutlineItemMetadataStreamReview()` now records `operand_shape` and `indirect_reference_required` for rejected non-indirect outline item `/Metadata` operands.
- Array and direct-dictionary operands keep the existing `rejected_non_indirect_metadata_reference` status and remain review-only with `payload_included=false`, `visible_text_source=false`, and `accepted_as_document_xmp=false`.
- Valid outline rows still resolve as TOC/navigation metadata; only malformed bookmark-local `/Metadata` operands are rejected.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects non-scalar outline Metadata operands before document metadata promotion
Values are not identical
Expected: 'array'
Actual: NULL
PASS keeps rejected outline Metadata operands out of TOC navigation and visible WordPress text

1 test files, 27 assertions, 1 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects non-scalar outline Metadata operands before document metadata promotion
PASS keeps rejected outline Metadata operands out of TOC navigation and visible WordPress text

1 test files, 47 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-operand-boundary-currentbase.php
```

The smoke emits `operand_shapes=[array,dictionary]`, `review_statuses=[rejected_non_indirect_metadata_reference,rejected_non_indirect_metadata_reference]`, `indirect_reference_required=true`, `payloads_excluded_from_metadata=true`, `payloads_excluded_from_navigation=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted outline title/color/xref/root/last/prev/stream-type coverage. It narrows the existing outline item `/Metadata` boundary to malformed operand shapes that are neither one indirect metadata stream reference nor duplicate `/Metadata` keys.

## Dependency closure

No new support component is needed. The slice reuses the existing native PDF dictionary, outline, metadata, stream-filter, TOC, and text extraction helpers. GPU/model OCR, Surya, Texify, pypdfium, Python, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
