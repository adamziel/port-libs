# markerPDF duplicate outline-root metadata fallback boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260606T233924Z`
Base: `eb7d11e9bcd6594ca75065e9ce45b3589c10aa36`

## Source Truth

Upstream markerPDF keeps PDF outlines/bookmarks as navigation/review metadata
instead of body text. Under the current no-GPU markerPDF scope, this maps to
native searchable-PDF parser behavior: outline dictionaries and their
`/Metadata` streams are review-only provenance, not WordPress paragraphs.

Existing PHP behavior already selected the last duplicate top-level
outline-root `/Metadata` operand for document-outline review metadata. The
gap was the lightweight fallback text boundary: `PdfTextExtractor` only marked
the first outline `/Metadata` stream reference as review-only before scanning
all decoded streams. A malformed/no-page-tree PDF with duplicate root metadata
could therefore leak the selected current metadata stream into visible text.

## Implementation

`PdfTextExtractor::outlineMetadataObjectGenerationSet()` now collects every
top-level `/Metadata` operand from outline item/root dictionaries and marks
each exact stream reference as review-only for fallback stream exclusion. This
aligns fallback text extraction with the duplicate-entry review policy already
used by `PdfMetadataExtractor`.

## Red-First Evidence

Before the source fix, this no-file probe returned the visible fallback body
plus the selected duplicate metadata payload:

```text
'Visible duplicate outline metadata fallback body
Selected duplicate outline metadata payload'
```

The unselected first metadata stream was excluded, but the current selected
duplicate stream was not.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-outline-duplicate-root-metadata-fallback-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-outline-duplicate-root-metadata-fallback-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php
1 test files, 64 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataNavigationReviewCurrentBaseTest.php
4 test files, 205 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-outline-duplicate-root-metadata-fallback-currentbase.php
reports duplicate_entries=true, selected_entry_index=1, selected object 9,
both duplicate metadata payloads excluded, outline title excluded from visible
text, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted direct outline-root metadata stream boundary
or the outline item duplicate-key review slice. It covers the lightweight
fallback stream scanner when duplicate outline-root `/Metadata` operands are
present and no page tree blocks fallback scanning.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF
dictionary scanner, exact-generation object resolver, stream dictionary
decoder, and document-outline metadata review path. GPU/model OCR, pypdfium,
Python markerPDF workers, and external PDF tools remain intentionally out of
scope for this lane.
