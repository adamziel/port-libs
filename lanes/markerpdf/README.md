# markerPDF Native PHP Lane

This directory contains the native PHP markerPDF port used by the WordPress/Data Liberation import work. It is deliberately not a Python, Torch, Surya, Texify, OCR, Streamlit, FastAPI, or GPU integration. The library focuses on deterministic shared-hosting pieces: searchable-PDF text extraction, PDF preflight checks, supplied OCR/layout/table/equation handoff boundaries, Gutenberg-ready block rendering, and benchmark-style quality gates.

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

`PdfTextExtractor` reads common PDF content-stream text operators and turns them into block-ready text lines. This example uses a tiny synthetic PDF with a MacRoman simple font. It exercises one of the obscure paths that often breaks naive PDF text extractors: high-byte legacy font encoding before WordPress paragraph rendering.

```php
<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require 'tools/bootstrap.php';

$content = "BT /Fmac 12 Tf 72 720 Td <576F72645072657373204D6163526F6D616E3A208E2044617461D120DB> Tj "
    . "T* [<D251756F7465D3> 120 <20496D706F7274>] TJ ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fmac 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /MacRomanEncoding >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
```

Complete runnable example:

- [examples/wordpress-pdf-macroman-import.php](examples/wordpress-pdf-macroman-import.php)

```sh
php lanes/markerpdf/examples/wordpress-pdf-macroman-import.php
```

It emits Gutenberg paragraph blocks and records that no Python, model worker, or external PDF tool was used.

## Gate Imports With Benchmark Reports

The benchmark helpers make PDF import quality measurable before content reaches an editor. They replay supplied conversion callbacks against committed reference snippets and produce the same report shape the upstream benchmark loop expects.

- [examples/wordpress-benchmark-report.php](examples/wordpress-benchmark-report.php)

```sh
php lanes/markerpdf/examples/wordpress-benchmark-report.php
```

Expected shape:

```json
{
  "scenario": "wordpress-pdf-benchmark-report",
  "passes_upstream_ci_marker_thresholds": true,
  "report": {
    "marker": {
      "avg_score": 0.8125502414038184
    }
  }
}
```

## Convert Supplied Layout, Table, And Image Boundaries

When a hosting environment or separate service supplies pdftext/layout/order/table/image data, `SuppliedDocumentConverter` can assemble it into Markdown and Gutenberg-preview metadata without executing OCR or layout models locally.

- [examples/wordpress-supplied-document-benchmark.php](examples/wordpress-supplied-document-benchmark.php)

```sh
php lanes/markerpdf/examples/wordpress-supplied-document-benchmark.php
```

That example converts supplied page dictionaries into a heading, paragraphs, a Markdown table, and an image placeholder, then runs the result through a benchmark gate. It is a good template for a WordPress import worker that accepts precomputed model-boundary payloads.

## Obscure PDF Features Already Covered

The markerPDF lane has focused examples and tests for several edge cases that are easy to miss:

- Stream filters: `ASCIIHexDecode`, `ASCII85Decode`, `RunLengthDecode`, `FlateDecode`, PNG/TIFF predictor parameters, and indirect `/Filter` or `/DecodeParms` objects.
- Font encodings: `/WinAnsiEncoding`, `/MacRomanEncoding`, custom `/Differences` arrays, indirect `/Encoding` dictionaries, PDF name escapes such as `/F#31`, and partial `/ToUnicode` CMap fallback.
- Text positioning: adjacent text operators, `TJ` numeric positioning, `Tc`, `Tw`, `Tz`, double-quote spacing operands, non-identity `Tm` horizontal scaling, and `q`/`Q` graphics-state scoping.
- Parser hardening: UTF-16 literal strings, unknown literal escapes, inline image payload skipping, variable-width CMap `begincodespacerange`, page-range slicing, text-length preflights, and block/span cleanup before Gutenberg rendering.
- WordPress handoffs: table recognition payloads, equation replacement payloads, image insertion metadata, debug bbox overlays, upload preview metadata, batch conversion planning, and API-upload response shaping.

Useful examples:

- [examples/wordpress-pdf-ascii85-filter-import.php](examples/wordpress-pdf-ascii85-filter-import.php)
- [examples/wordpress-pdf-indirect-filter-import.php](examples/wordpress-pdf-indirect-filter-import.php)
- [examples/wordpress-pdf-tounicode-import.php](examples/wordpress-pdf-tounicode-import.php)
- [examples/wordpress-pdf-differences-import.php](examples/wordpress-pdf-differences-import.php)
- [examples/wordpress-table-recognition-handoff.php](examples/wordpress-table-recognition-handoff.php)
- [examples/wordpress-supplied-equation-import.php](examples/wordpress-supplied-equation-import.php)

Run them from the repository root:

```sh
php lanes/markerpdf/examples/wordpress-pdf-ascii85-filter-import.php
php lanes/markerpdf/examples/wordpress-pdf-indirect-filter-import.php
php lanes/markerpdf/examples/wordpress-pdf-tounicode-import.php
php lanes/markerpdf/examples/wordpress-pdf-differences-import.php
php lanes/markerpdf/examples/wordpress-table-recognition-handoff.php
php lanes/markerpdf/examples/wordpress-supplied-equation-import.php
```
