# markerpdf outline metadata selected-null boundary current-base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260608T125245Z`

Base: `7603533a398f2065a0bd727ab5d4c25c28424287`

## Behavior

This patch covers a native PDF outline/document-metadata boundary: duplicate outline root or item `/Metadata` keys where the selected last top-level value is explicit `null`.

PDFium/pypdfium-style outline extraction treats outlines as navigation metadata, while document metadata stays separate. The native PHP review path now preserves the selected-null decision as review-only provenance and does not carry stale unselected metadata stream object numbers, hashes, payload bytes, or visible text into WordPress imports.

## Red-first evidence

Before the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataSelectedNullBoundaryCurrentBaseTest.php`

Result: `1 test files, 15 assertions, 2 failures`

Failures: root and item selected-null `/Metadata` review rows were absent.

## Passing evidence

Focused:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataSelectedNullBoundaryCurrentBaseTest.php`

Result: `1 test files, 60 assertions, 0 failures`

Adjacent outline metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataSelectedNullBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataSelectedDuplicateOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataNavigationReviewCurrentBaseTest.php`

Result: `5 test files, 364 assertions, 0 failures`

Full focused outline metadata family guard:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*CurrentBaseTest.php`

Result: `69 test files, 2917 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-selected-null-boundary-currentbase.php`

Result: exits `0`; reports `selected_null_entry=true`, `stale_stream_hashes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not duplicate the prior marker app preview xref-stream `/Prev` catalog selection slice. It also avoids already-covered outline metadata behaviors for selected duplicate stream references, malformed trailing operands, duplicate key summaries, outline action chains, root stream hashes, and stale post-EOF outline roots. The new behavior is limited to explicit selected `null` outline `/Metadata` entries.

## Dependency closure

No new support component is needed. The patch reuses the existing native PDF object/dictionary parser, outline metadata review, stream-filter metadata summarizer, and navigation metadata handoff. No Python, OCR, Surya/Texify/Torch, pypdfium/PDFium runtime, raster renderer, JavaScript execution, or external PDF tools are invoked.
