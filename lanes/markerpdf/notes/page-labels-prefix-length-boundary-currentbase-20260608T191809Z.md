# PageLabels Prefix Length Boundary Current Base

Slice: `markerpdf-page-labels-boundary-current-base-20260608T191809Z`
Base: `ac0b2b26074ec6e75d171aa7e3eb5bbc4ca926f1`

## Source Truth

- Upstream markerPDF text extraction remains delegated to `pdftext.extraction.dictionary_output` in `marker/pdf/extract_text.py`; this PHP lane stays native and no-GPU. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel tests model catalog `/PageLabels` as a number tree whose value dictionaries may contain optional `/S`, `/P`, and `/St` fields, and exercise generated labels across large page ranges. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp

## Implemented Boundary

The existing PHP PageLabels implementation already bounded generated Roman and alphabetic suffixes to 4096 bytes, but raw decoded `/P` prefixes were unbounded. A crafted searchable PDF could therefore put a very large literal or indirect string into `/P` and inflate WordPress page-break metadata even though paragraph text stayed small.

This patch adds a 4096-byte decoded prefix cap to both native paths:

- `PdfTextExtractor::pageLabelPrefix()`
- `MarkerAppPreview::pageLabelPrefixValueAfterName()`

Oversized `/P` values are treated as unusable and the parser keeps scanning later duplicate `/P` entries. This preserves the existing first-usable duplicate-key behavior while keeping page labels bounded.

## Evidence

Pre-fix red check:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsPrefixLengthBoundaryCurrentBaseTest.php
```

Result: `1 test files, 1 assertions, 1 failures`; the label array contained a 4097-byte `L...` prefix.

Post-fix focused check:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsPrefixLengthBoundaryCurrentBaseTest.php
```

Result: `1 test files, 17 assertions, 0 failures`.

PageLabels neighborhood check:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*Test.php
```

Result: `49 test files, 877 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-page-labels-prefix-length-currentbase.php
```

Result: exits 0 with page labels `["4","Safe-8","App-Z"]`, `oversized_prefix_rejected=true`, `duplicate_safe_prefix_preserved=true`, and no Python/models or external PDF tools.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This is not another PageLabels tree traversal, duplicate `/Type`, malformed UTF-16, generated suffix, xref fallback, or trailer-root selection patch. It only bounds decoded `/P` prefix strings before WordPress page metadata.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF token/string decoding, object-reference resolution, PageLabels number-tree parser, and preview metadata path. OCR, raster image parsing, pypdfium/PIL, Surya/Texify/Torch, Python models, and external PDF tools remain out of scope for this no-GPU markerPDF slice.

## Next

Continue non-overlapping native markerPDF work around searchable-PDF parser fidelity: font/CMap encoding edges, stream filters, xref repair, annotations/forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
