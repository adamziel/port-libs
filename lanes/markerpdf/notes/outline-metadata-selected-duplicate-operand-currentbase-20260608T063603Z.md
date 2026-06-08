# markerPDF outline metadata selected duplicate operand boundary

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260608T063603Z`
Session: `port-dev-markerpdf-outline-meta-20260608T063603Z`
Base accepted HEAD: `1e71f3c93d1cbd08cf1009ae6a57b995bf2b94fc`

## Source truth

Upstream markerPDF receives PDF outline/bookmark data as navigation and review
metadata through the PDF parser boundary. It does not treat outline dictionaries
or bookmark-local metadata streams as page body text. In this no-GPU native PHP
lane, duplicate PDF dictionary keys use the selected top-level value already
used by the dictionary reader, while malformed stale operands must stay
payload-free review state.

## Implementation

`PdfMetadataExtractor::documentOutlineMetadataMalformedOperandReview()` now
applies the malformed-operand check to the selected top-level `/Metadata` entry
instead of returning the first malformed duplicate it sees.

This preserves fail-closed behavior when the selected `/Metadata` entry is
malformed, but allows a later valid duplicate `/Metadata` stream to provide the
current outline-root or outline-item review metadata after a stale malformed
operand.

## Red-first evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataSelectedDuplicateOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses selected duplicate outline Metadata entries after stale malformed operands
Values are not identical
Expected: 'reviewed_outline_root_metadata_stream'
Actual: 'rejected_malformed_outline_root_metadata_operand'
FAIL keeps selected duplicate outline Metadata payloads out of TOC navigation and visible WordPress text
Values are not identical
Expected: 11
Actual: 10

1 test files, 16 assertions, 2 failures
```

After the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataSelectedDuplicateOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses selected duplicate outline Metadata entries after stale malformed operands
PASS keeps selected duplicate outline Metadata payloads out of TOC navigation and visible WordPress text

1 test files, 68 assertions, 0 failures
```

Adjacent outline metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataSelectedDuplicateOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataUnreadableStreamTailBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
...
6 test files, 349 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-selected-duplicate-metadata-operand-currentbase.php
```

The smoke emits `root_selected_entry_index=1`, `root_selected_object=9`,
`item_selected_entry_index=1`, `item_selected_object=11`,
`stale_malformed_operand_excluded=true`,
`visible_text_excludes_outline_metadata=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted outline metadata operand-shape rejection,
duplicate `/Metadata` provenance, duplicate root metadata fallback text
exclusion, root/item metadata stream review, stream-generation exactness,
unreadable stream tails, xref repair, PageLabels, action-chain review, or
outline title/color/style behavior. The bounded behavior is only the selected
duplicate `/Metadata` policy when an earlier stale duplicate has trailing
operands.

## Dependency closure

No new support component is needed. The patch reuses the native PDF dictionary
scanner, current object resolver, stream decoder, outline metadata review
extractor, TOC/navigation mirrors, and WordPress smoke renderer. Live OCR,
Surya/Texify/Torch, pypdfium/PDFium rendering, Python markerPDF workers, and
external PDF tools remain intentionally out of scope.
