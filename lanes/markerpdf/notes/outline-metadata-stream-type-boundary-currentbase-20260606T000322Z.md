# Outline Metadata Stream Type Boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T235653Z`
Base accepted HEAD: `8274a083130b4e14806ca5a49cc61e2394be5e70`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` receives PDF outline and metadata through pdftext/PDFium boundaries before model/OCR handoff. Under the current no-GPU lane scope, native PHP markerPDF must keep outline item metadata as review metadata only and must not promote arbitrary stream payloads, malformed stream tails, or action tokens into document XMP, navigation text, or visible WordPress paragraphs.

PDF catalog metadata handling in this lane already requires a single-token `/Type /Metadata /Subtype /XML` stream object before document XMP promotion. This slice applies the same trust boundary to bookmark-local outline item `/Metadata` review.

## Behavior

`PdfMetadataExtractor::documentOutlineItemMetadataStreamReview()` now rejects outline item `/Metadata` references when the target stream is not a PDF metadata XML stream. It also rejects metadata streams whose object body contains extra top-level tokens after `endstream`.

Rejected streams remain payload-free review rows:

- non-metadata XML streams use `rejected_non_metadata_outline_item_stream`;
- malformed metadata stream objects use `rejected_malformed_outline_item_metadata_stream`;
- decoded payload text is redacted from metadata, TOC/navigation review, and visible WordPress text;
- outline destination rows remain importable.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium runtime, browser renderer, or external PDF tool was executed.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects non-metadata and malformed outline Metadata streams as review-only boundary rows
Expected: 'rejected_non_metadata_outline_item_stream'
Actual: 'reviewed_outline_item_metadata_stream'
PASS keeps rejected outline Metadata stream payloads out of TOC navigation and visible WordPress text

1 test files, 32 assertions, 1 failures
```

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamTypeBoundaryCurrentBaseTest.php
=> 1 test files, 55 assertions, 0 failures
```

Adjacent outline metadata family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfOutlineMetadata.*Test\.php$' | sort)
=> 35 test files, 1407 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-stream-type-boundary-currentbase.php
=> non_metadata_status=rejected_non_metadata_outline_item_stream; malformed_status=rejected_malformed_outline_item_metadata_stream; metadata_payload_included=[false,false]; visible_text_excludes_rejected_metadata_payloads=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2285 -> 2287`.
- `wordpressScenarios`: `1963 -> 1964`.
- New focused file: `PdfOutlineMetadataStreamTypeBoundaryCurrentBaseTest.php` adds 2 PASS cases and 55 assertions.

## Non-Overlap

This does not repeat accepted outline trailer-root selection, generation-exact item references, `/Prev`, `/Last`, parent/missing-parent, zero-count, title scalar, item type, direct/indirect outline root, XRef stream root, duplicate `/Metadata` key, valid outline `/Metadata` stream review, structure-element metadata, named-destination, page-label, action-chain, or visible text leakage boundaries.

The bounded behavior is only outline item `/Metadata` target stream trust: reject non-metadata stream targets and malformed metadata stream objects while preserving review-only status metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, current xref-selected object map, stream decoder, metadata stream dictionary labeling, XMP packet summary redaction, outline metadata walker, TOC/navigation review extractors, text extractor, and WordPress smoke path. Full upstream model parity remains intentionally out of scope for this no-GPU markerPDF lane.
