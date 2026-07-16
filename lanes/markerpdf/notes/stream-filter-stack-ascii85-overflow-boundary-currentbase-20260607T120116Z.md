# markerPDF stream filter stack ASCII85 overflow boundary

Session: `port-dev-markerpdf-stream-filter-stack-20260607T120116Z`

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260607T120116Z`

Base accepted HEAD: `37b6b8cba9853ec530d73a609e75241368314341`

## Scope

This slice stays inside the no-GPU markerPDF native parser scope. It maps a PDF stream-filter boundary for `ASCII85Decode`: a five-character ASCII85 group represents one 32-bit tuple, so groups above `0xffffffff` must fail closed instead of wrapping into bytes and allowing later page-content operators to import.

The red-first probe on the accepted base used an `uuuuu` ASCII85 tuple before otherwise valid encoded page text. Before the decoder change, `PdfTextExtractor::extractTextLines()` returned:

```php
[
    'ASCII85 Overflow Leak',
    'Visible After Overflow',
]
```

After the patch, full-tuple overflow (`uuuuu`) and final partial-tuple overflow (`uu~>`, padded during decode) reject the malformed streams while preserving the valid following content stream.

## Verification

Focused commands run:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-ascii85-overflow-currentbase.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-ascii85-overflow-currentbase.php
git diff --check -- lanes/markerpdf
```

Observed focused movement: one new PHP PASS case and 12 new focused assertions in `PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`, plus one WordPress smoke scenario. Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted stream-filter trailing-payload checks, compact/null/extra DecodeParms alignment, malformed DecodeParms numeric token rejection, exact-generation filter operands, default/identity Crypt filters, RunLength/LZW end-marker recovery, DCT/CCITT/JPX/JBIG2 image-filter boundaries, CMap filter EOD handling, or xref/object-stream filter owner recovery. The bounded behavior is ASCII85 numeric tuple overflow validation inside the native stream decoder.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, filter-stack resolver, ASCII85 decoder, content-token parser, and WordPress smoke renderer. Full OCR/model parity remains intentionally out of scope under the no-GPU markerPDF direction; no Python, OCR, model, pypdfium, PIL, external PDF tool, or live-service provider execution was run.
