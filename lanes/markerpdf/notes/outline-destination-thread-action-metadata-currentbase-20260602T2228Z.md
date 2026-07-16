# Outline Destination Thread Action Metadata Current Base

Micro-slice: `outline-destination-thread-action-metadata-currentbase`

Base accepted HEAD: `5eb7c8f9b2d7a9a15b9d174ca06467c45dce2fca`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/cleaners/toc.py::get_pdf_toc`, delegates outline extraction to the PDF engine and preserves only title, level, and zero-based page rows: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py
- Upstream `marker/pdf/extract_text.py::get_text_blocks` returns page text blocks separately from TOC metadata, so action/thread operands remain navigation review metadata rather than visible page text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF action behavior for this slice: bookmarks/annotations can use actions; Thread actions begin article-thread reading, and Adobe pdfmark documentation notes Article maps to the PDF Thread action type and that actions should be treated separately from destinations: https://opensource.adobe.com/dc-acrobat-sdk-docs/library/pdfmark/pdfmark_Actions.html

## Implementation

- `PdfOutlineExtractor::threadActionDestinationDetails()` now normalizes local Thread action targets with a `page_object` value when the selected bead points at a current-document page.
- `PdfOutlineExtractor::destinationActionTargetContext()` now carries `destination_action_target_thread_destination` and `destination_action_target_thread_page_object` alongside the existing target thread object/index/title/bead fields.
- Added `PdfOutlineDestinationThreadActionMetadataCurrentBaseTest.php` covering a direct outline `/Dest 9 0 R` value where object `9` is an `/S /Thread` action dictionary with chained URI and JavaScript actions.
- Added `wordpress-pdf-outline-destination-thread-action-metadata-currentbase.php` proving the Gutenberg-facing metadata has page-object/thread fields and that Thread, URI, JavaScript, page-open URI, embedded XML, and review-state operands stay out of visible paragraphs.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationThreadActionMetadataCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes outline destination Thread action target metadata on primary and chained rows
Expected: 4
Actual: NULL
PASS keeps outline destination Thread action operands out of visible WordPress text
1 test files, 39 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationThreadActionMetadataCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes outline destination Thread action target metadata on primary and chained rows
PASS keeps outline destination Thread action operands out of visible WordPress text
1 test files, 89 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php
21 test files, 1491 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineDestinationThreadActionMetadataCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-destination-thread-action-metadata-currentbase.php
```

All three lint checks passed.

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $f . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $f . " ok\n"; }'
lanes/markerpdf/lane-status.json ok
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json ok
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-destination-thread-action-metadata-currentbase.php >/tmp/markerpdf-outline-destination-thread-action-metadata-currentbase.out
wc -l /tmp/markerpdf-outline-destination-thread-action-metadata-currentbase.out
16 /tmp/markerpdf-outline-destination-thread-action-metadata-currentbase.out
```

The smoke emitted `outline_action_types=["Thread","URI","JavaScript"]`, `thread_action_target_page_object=4`, `thread_action_target_thread_page_object=4`, `thread_action_target_destination="0"`, `thread_action_target_label="Article 9"`, `thread_action_target_beads=[22]`, `chained_target_attachment="destination-thread-review.xml"`, and `visible_text_excludes_action_operands=true`.

```text
git diff --check -- lanes/markerpdf
```

Passed with no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused behavior tests move `907 -> 909` pass / `0` fail.
- Mapped markerPDF/PDF semantics move `638 -> 639 / 78` with `pdfOutlineDestinationThreadActionMetadataCurrentBase`.
- WordPress scenario count moves `907 -> 909`.

## Non-Overlap

This does not repeat accepted direct named Thread action rows, outline `/A /Thread` action transition rows, name-tree GoTo-to-Thread page-review propagation, article-thread bead navigation, remote Thread action stacks, destination action page-label/StructElem summaries, or page PieceInfo target-review propagation. The bounded new behavior is direct outline `/Dest` values that point at Thread action dictionaries and need normalized target page-object plus thread destination/page-object metadata on primary and chained review rows.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, outline destination/action walker, article-thread bead resolver, page label/transition/action metadata, page review extractor, associated-file review, and visible text boundary. Full live upstream runner parity remains gated on pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed for this bounded PHP slice.
