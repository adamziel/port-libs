# markerPDF Native PHP Lane

This directory contains the native PHP markerPDF port used by the WordPress/Data Liberation import work. It is deliberately not a Python, Torch, Surya, Texify, OCR, Streamlit, FastAPI, or GPU integration. The library focuses on deterministic shared-hosting pieces: searchable-PDF parsing, PDF metadata/review extraction, supplied OCR/layout/table/equation handoff boundaries, Gutenberg-ready block rendering, and benchmark-style quality gates.

Examples directory:

- Local: [examples/](examples/)
- Repository: <https://github.com/adamziel/port-libs/tree/main/lanes/markerpdf/examples>

Run the focused markerPDF suite from the repository root:

```sh
php tools/run-tests.php lanes/markerpdf/tests
```

Run every markerPDF scenario example:

```sh
for f in lanes/markerpdf/examples/*.php; do php "$f" >/dev/null || echo "$f"; done
```

## Extract Searchable PDF Text

`PdfTextExtractor` reads PDF content streams and turns visible text into WordPress-ready output without running external PDF tools. It covers common operators and several less obvious PDF text boundaries: stream filters, CMaps, Type0/CID fonts, simple-font encodings, text positioning, page resource inheritance, object streams, xref repair, and inline image exclusion.

Representative examples:

- [examples/wordpress-import.php](examples/wordpress-import.php)
- [examples/wordpress-pdf-ascii85-filter-import.php](examples/wordpress-pdf-ascii85-filter-import.php)
- [examples/wordpress-pdf-standard-macroman-symbol-encoding-import.php](examples/wordpress-pdf-standard-macroman-symbol-encoding-import.php)
- [examples/wordpress-pdf-encoding-differences-import.php](examples/wordpress-pdf-encoding-differences-import.php)
- [examples/wordpress-pdf-cmap-tounicode-row-count-boundary.php](examples/wordpress-pdf-cmap-tounicode-row-count-boundary.php)
- [examples/wordpress-pdf-cmap-indirect-filter-array-tail-currentbase.php](examples/wordpress-pdf-cmap-indirect-filter-array-tail-currentbase.php)
- [examples/wordpress-pdf-indirect-filter-decodeparms-owner-currentbase.php](examples/wordpress-pdf-indirect-filter-decodeparms-owner-currentbase.php)
- [examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php](examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php)

Run a few of them:

```sh
php lanes/markerpdf/examples/wordpress-import.php
php lanes/markerpdf/examples/wordpress-pdf-standard-macroman-symbol-encoding-import.php
php lanes/markerpdf/examples/wordpress-pdf-indirect-filter-decodeparms-owner-currentbase.php
```

## Gate Imports With Benchmark Reports

The benchmark helpers make PDF import quality measurable before content reaches an editor. They replay supplied conversion callbacks against committed reference snippets and produce upstream-shaped report data for WordPress import gates.

Representative examples:

- [examples/wordpress-benchmark-report.php](examples/wordpress-benchmark-report.php)
- [examples/wordpress-benchmark-runner.php](examples/wordpress-benchmark-runner.php)
- [examples/wordpress-benchmark-score-verifier-currentbase.php](examples/wordpress-benchmark-score-verifier-currentbase.php)
- [examples/wordpress-supplied-document-benchmark.php](examples/wordpress-supplied-document-benchmark.php)
- [examples/wordpress-multicolcnn-supplied-benchmark.php](examples/wordpress-multicolcnn-supplied-benchmark.php)
- [examples/wordpress-switch-transformers-supplied-benchmark.php](examples/wordpress-switch-transformers-supplied-benchmark.php)

```sh
php lanes/markerpdf/examples/wordpress-benchmark-report.php
php lanes/markerpdf/examples/wordpress-supplied-document-benchmark.php
```

## Convert Supplied Layout, Table, And Equation Boundaries

When a hosting environment or separate service supplies pdftext/layout/order/table/equation data, the PHP lane can assemble it into Markdown, Gutenberg block previews, review metadata, and quality reports without executing OCR or layout models locally.

Representative examples:

- [examples/wordpress-pdftext-block-import.php](examples/wordpress-pdftext-block-import.php)
- [examples/wordpress-pdftext-page-range-import.php](examples/wordpress-pdftext-page-range-import.php)
- [examples/wordpress-table-recognition-handoff.php](examples/wordpress-table-recognition-handoff.php)
- [examples/wordpress-table-ocr-span-grid-benchmark-format-bundle-currentbase.php](examples/wordpress-table-ocr-span-grid-benchmark-format-bundle-currentbase.php)
- [examples/wordpress-equation-import.php](examples/wordpress-equation-import.php)
- [examples/wordpress-supplied-equation-import.php](examples/wordpress-supplied-equation-import.php)
- [examples/wordpress-texify-equation-batch-preflight.php](examples/wordpress-texify-equation-batch-preflight.php)

```sh
php lanes/markerpdf/examples/wordpress-table-recognition-handoff.php
php lanes/markerpdf/examples/wordpress-supplied-equation-import.php
```

## Review Metadata Without Executing PDF Actions

The lane also extracts review metadata that WordPress importers can use without executing JavaScript, PDF actions, media playback, remote actions, or model workers.

Useful examples:

- [examples/wordpress-pdf-outline-import.php](examples/wordpress-pdf-outline-import.php)
- [examples/wordpress-pdf-outline-action-chain-metadata-currentbase.php](examples/wordpress-pdf-outline-action-chain-metadata-currentbase.php)
- [examples/wordpress-pdf-page-annots-comment-reference-boundary-currentbase.php](examples/wordpress-pdf-page-annots-comment-reference-boundary-currentbase.php)
- [examples/wordpress-pdf-acroform-fields-import.php](examples/wordpress-pdf-acroform-fields-import.php)
- [examples/wordpress-pdf-xmp-metadata-import.php](examples/wordpress-pdf-xmp-metadata-import.php)
- [examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php](examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php)
- [examples/wordpress-pdf-image-xobject-boundary-import.php](examples/wordpress-pdf-image-xobject-boundary-import.php)

```sh
php lanes/markerpdf/examples/wordpress-pdf-page-annots-comment-reference-boundary-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-import.php
```

## Obscure PDF Features Already Covered

The markerPDF lane has focused examples and tests for many PDF edge cases that are easy to miss:

- Stream filters: `ASCIIHexDecode`, `ASCII85Decode`, `RunLengthDecode`, `LZWDecode`, `FlateDecode`, filter arrays, predictor parameters, malformed filter fail-closed behavior, and indirect `/Filter` or `/DecodeParms` operands.
- Fonts and text: `/WinAnsiEncoding`, `/MacRomanEncoding`, `StandardEncoding`, Symbol fallback, custom `/Differences`, Type0/CID fonts, `/ToUnicode` maps, CMap row-count boundaries, CMap comments, source-width fallback, vertical writing, and glyph-width grouping.
- Parser recovery: classic xref tables, xref streams, hybrid xrefs, object streams, `/Prev` incremental updates, current-generation precedence, stale stream-length recovery, and stream-owner boundaries.
- Review-only metadata: outlines, named destinations, page labels, annotations, link and markup spans, AcroForm field state, XFA packets, signatures, attachments, XMP/Info metadata, viewer preferences, page actions, and open actions.
- WordPress handoffs: supplied pdftext dictionaries, layout/order payloads, table geometry, equation replacement, image review metadata, debug overlays, runtime preflight, upload/API response shaping, and benchmark reports.

The guiding rule is stable: keep native PHP deterministic behavior in scope, and keep GPU/model/Python execution out of scope.
