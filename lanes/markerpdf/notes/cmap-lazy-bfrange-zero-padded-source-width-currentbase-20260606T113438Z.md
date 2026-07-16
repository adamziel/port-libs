# markerPDF CMap lazy bfrange zero-padded source-width fallback

Session: `port-dev-markerpdf-source-width-20260606T113438Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260606T113438Z`
Base: `454eb2e80ab750c1392b21e50662320bbde7c428`

## Source truth

The lane manifest pins upstream `sddai/markerPDF` at
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. That upstream delegates
searchable-PDF text extraction through PDF parser behavior where ToUnicode CMap
source codes define the visible text and CIDFont widths define the glyph
advances. The native no-GPU PHP fallback must preserve that contract for large
`beginbfrange` rows even when only a lazy range entry, not an eagerly expanded
direct map key, proves that a zero-padded source suffix is a real glyph code.

## Behavior

Before the source edit, a prototype fixture decoded the visible text from a
large ToUnicode bfrange but did not recognize the zero-padded source suffix for
width segmentation because the suffix lived only in `unicodeRanges`. The first
run's styled bbox was `[0,0,72,12]` and the second was `[72,0,108,12]`, counting
padding bytes as glyph advances.

After the patch, `zeroPaddedSourceKeysForFontWidths()` includes lazy ToUnicode
range key lengths and accepts suffixes that resolve through
`toUnicodeRangeTextForSourceKey()`. The same fixture now preserves the expected
styled bboxes `[0,0,48,12]` and `[48,0,60,12]` while keeping CMap program bytes
out of visible text.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/tests/PdfCMapLazyBfrangeZeroPaddedSourceWidthCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-cmap-lazy-bfrange-zero-padded-source-width-currentbase.php` => no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLazyBfrangeZeroPaddedSourceWidthCurrentBaseTest.php` => `1 test files, 10 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMap*SourceWidth*CurrentBaseTest.php` => `8 test files, 470 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-cmap-lazy-bfrange-zero-padded-source-width-currentbase.php` => emitted the Gutenberg paragraph and metadata flags for lazy bfrange text preservation, zero-padded width collapse, false wide padding bbox exclusion, CMap program text exclusion, and no Python/model/external PDF tool execution
- `git diff --check -- lanes/markerpdf` => no whitespace errors

Root harness status: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This slice reuses the existing native CMap
parser, lazy ToUnicode range resolver, CIDFont width handling, and in-memory PDF
fixture builder. GPU/OCR/model parity remains intentionally out of scope under
the markerPDF no-GPU lane override.

## Non-overlap

This does not repeat accepted direct ToUnicode map zero-padding, repeated
zero-prefix, large bfrange text-only, sparse codespace, short bfrange array,
TJ, or Type3 property-boundary work. The new boundary is lazy ToUnicode
`beginbfrange` suffix recognition inside zero-padded source-width fallback.
