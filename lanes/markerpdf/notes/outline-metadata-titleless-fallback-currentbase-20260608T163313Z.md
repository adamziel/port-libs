# markerpdf outline metadata titleless fallback current-base

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream receives searchable text and outline metadata from the PDF parser layer before OCR/model stages. In the native no-GPU PHP lane, outline-local `/Metadata` streams are review-only bookmark payloads and must not become fallback WordPress paragraph text.
- This slice covers a malformed but common boundary: an outline row without `/Title` is skipped for TOC/navigation promotion, but its `/Parent` still places it inside the outline tree. Its local `/Metadata` stream must remain excluded from lightweight fallback stream scanning.

## Implementation

- `PdfTextExtractor::outlineMetadataObjectGenerationSet()` now recognizes titleless outline rows when their `/Parent` resolves to an outline root or another outline item.
- The existing titled outline-item behavior is preserved, while titleless rows with outline linkage can still mark local `/Metadata` streams as fallback-excluded.
- Added a focused fixture where a titleless outline row references a Flate metadata stream and a valid titled sibling follows it. Before the fix, fallback text decoded the metadata payload before the visible stream.
- Added a WordPress smoke that emits only the safe fallback paragraph and records that document metadata, lightweight metadata, and visible text all exclude the titleless outline metadata payload.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTitlelessFallbackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL excludes titleless outline item Metadata streams from lightweight fallback text (lanes/markerpdf/tests/PdfOutlineMetadataTitlelessFallbackBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'Titleless outline metadata fallback visible body'
Actual: 'Titleless outline metadata fallback payload should stay hidden
Titleless outline metadata fallback visible body'

1 test files, 16 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTitlelessFallbackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS excludes titleless outline item Metadata streams from lightweight fallback text

1 test files, 20 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTitlelessFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTitleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataIndirectTitleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataUnreadableStreamTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataReferenceTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRootMetadataNavigationBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
...
7 test files, 296 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfOutlineMetadata.*Test\.php$' | sort)
Focused test run: 74 selected test files (root lock skipped)
...
74 test files, 3177 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-titleless-fallback-currentbase.php
exits 0 with metadata_payload_excluded_from_document_metadata=true, metadata_payload_excluded_from_lightweight_metadata=true, metadata_payload_excluded_from_visible_text=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfOutlineMetadataTitlelessFallbackBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfOutlineMetadataTitlelessFallbackBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-outline-metadata-titleless-fallback-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-outline-metadata-titleless-fallback-currentbase.php

git diff --check -- lanes/markerpdf
exits 0
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted outline title operand rejection, untitled outline child traversal boundaries, root/item metadata stream review, malformed metadata stream tail rejection, root metadata navigation propagation, selected duplicate metadata handling, catalog `/Outlines` operand boundaries, xref outline root selection, OCR/model execution, or pypdfium/PDFium behavior. The bounded change is only fallback stream exclusion for `/Metadata` streams referenced by titleless outline rows that are still linked to an outline parent.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, outline dictionary classifier, stream decoder, metadata extractor, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI workers, JavaScript/PDF action execution, decryption/password validation, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
