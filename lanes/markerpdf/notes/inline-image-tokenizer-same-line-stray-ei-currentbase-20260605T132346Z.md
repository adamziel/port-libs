# markerPDF inline image tokenizer same-line stray EI boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T132346Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text extraction through pdftext/PDFium-style content-stream parsing before WordPress conversion. Under the current no-GPU markerPDF scope, this lane owns native PHP content-stream tokenization for searchable PDFs.

PDF content operators are tokenized by delimiters, not by a required newline after every operator. A preview-only inline image fallback can therefore be followed by visible `BT ... ET` text on the same line as the accepted `EI` boundary, then later encounter a stray `EI` operator. The tokenizer must keep that visible same-line text while still excluding inline image bytes and any trailing payload junk.

## Behavior

`PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` now accepts the narrow same-line fallback segment only when the first non-whitespace token is a bare balanced `BT ... ET` text object with no queued graphics-state operands, marked-content wrappers, or trailing outside-text operands. Existing line-separated text-object, graphics-state wrapped, and marked-content fallback boundaries remain unchanged.

The focused fixture uses a sample-floor JBIG2 preview-only inline image with one image byte, a same-line visible text object before a later stray `EI`, and a final visible text object after the stray operator. Native text extraction now emits:

```text
Before Same Line Stray
Visible Same Line Before Stray
Visible After Same Line Stray
```

while excluding `\x80 EI` image bytes.

## Evidence

Red baseline after adding the focused case and before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL closes preview-only fallback before same-line text followed by stray EI operator
Values are not identical
Expected: array (
  0 => 'Before Same Line Stray',
  1 => 'Visible Same Line Before Stray',
  2 => 'Visible After Same Line Stray',
)
Actual: array (
  0 => 'Before Same Line Stray',
  1 => 'Visible After Same Line Stray',
)

1 test files, 269 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
30 PASS cases

1 test files, 277 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php | rg "preview_only_same_line|executes_python_or_models|executes_external_pdf_tools|Visible Same Line"
<!-- markerpdf-inline-image-tokenizer-boundary-currentbase {... &quot;executes_python_or_models&quot;:false, ... &quot;executes_external_pdf_tools&quot;:false, ... &quot;preview_only_same_line_stray_ei_text_preserved_after_safe_boundary&quot;:true, ...} -->
<p>Visible Same Line Before Stray</p>
```

Adjacent inline-image guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
62 PASS cases

4 test files, 692 assertions, 0 failures
```

Syntax and metadata checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
lanes/markerpdf/lane-status.json OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK

git diff --check -- lanes/markerpdf
no output
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inline image DecodeParms decoding, ASCII85 end-marker repair, DCT/CCITT/JPX/JBIG2 image filter boundaries, null/abbreviated filter-array handling, slash-delimited `EI` operators, line-separated stray-`EI` fallback, graphics-state wrapped stray-`EI` fallback, marked-content ActualText fallback, xref repair, stream filters, metadata, annotations, forms, page geometry, table/equation handoffs, or model/OCR work.

The bounded behavior here is specifically preview-only inline image fallback closure before a same-line balanced `BT ... ET` text object followed by a later stray `EI` operator.

## Dependency Closure

No new support component is needed. This slice reuses native PHP content-stream tokenization, inline-image dictionary/sample-floor heuristics, text extraction, and the WordPress smoke renderer. Full upstream OCR/model parity remains intentionally out of scope under the no-GPU markerPDF directive; no Python, pdftext, Surya/Torch, Texify, external PDF tools, or live services were executed.
