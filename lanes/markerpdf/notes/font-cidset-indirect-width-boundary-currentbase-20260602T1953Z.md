# font-cidset-indirect-width-boundary-currentbase

Session: `port-dev-markerpdf-font45-20260602T1953Z`
Base accepted HEAD: `ca550807cded80a5a0bf98599fdd8ae923c222c8`

## Source Truth

Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets text through the pdftext/PDFium parsing stack. The relevant parser behavior for this native PHP slice is object-reference fidelity: an indirect reference is the object number plus generation. A `/FontDescriptor` entry such as `/CIDSet 7 1 R` must not silently read stale object `7 0 obj`.

## Behavior

`PdfTextExtractor::cidSetFromFontBody()` now resolves descriptor `/CIDSet` references through the generation-aware object table before using CIDSet bits to suppress CIDFont default-width grouping. If the referenced generation is absent, markerPDF ignores the CIDSet instead of reading a same-number stale stream. This preserves WordPress-visible text such as `WideBlock` when the stale CIDSet would otherwise split default-width glyphs into `Wide Block`.

This is intentionally narrower than accepted indirect `/DW`, indirect `/W`, vertical `/W2`, descriptor default-width, and CIDSet default-width grouping slices. It covers the stale-generation indirect CIDSet boundary before text-gap grouping.

## Evidence

Red before source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidSetIndirectWidthBoundaryCurrentBaseTest.php`

Failed with extracted text `Wide Block` instead of `WideBlock` (`1 test files, 1 assertions, 1 failures`).

Green after source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidSetIndirectWidthBoundaryCurrentBaseTest.php`

Passed: `1 test files, 6 assertions, 0 failures`.

Adjacent focused gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidSetIndirectWidthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CidSetDescriptorDefaultCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`

Passed: `5 test files, 626 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-cidset-indirect-width-boundary-currentbase.php`

Passed with `stale_cidset_generation_excluded=true` and Gutenberg paragraph text `WideBlock`.

PHP lint passed for changed PHP files, and `git diff --check -- lanes/markerpdf` passed.

## Dependency Closure

No new support component is needed. This reuses markerPDF native PHP object parsing, exact-generation owner lookup, stream decoding, CIDSet bit parsing, Type0/CIDFont width grouping, and the existing WordPress smoke path. Full upstream parity remains gated on the Python/pdftext/PDFium/Surya/model/tool stack and was not attempted in this isolated slice.
