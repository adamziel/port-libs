# markerPDF Font Type3 CharProc CIDSet Descriptor Current Base

Session: `port-dev-markerpdf-font35pdf-20260602T173924Z`

Micro-slice: `font-type3-charproc-cidset-descriptor-currentbase-20260602T173924Z`

Base accepted HEAD: `252c505983bfd6b8ea68d7f5271483812ad199ee`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes low-level PDF text extraction through `pdftext.extraction.dictionary_output`, then preserves span font name, flags, weight, and size in `pdftext_format_to_blocks()` before Marker block assembly:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/utils.py

Relevant PDF parser/dependency behavior from `pypdf` 5.4.0: font encodings are resolved with `get_object()`, ToUnicode CMaps provide decoded text, descendant `/W` and `/DW` widths drive CID advances, and simple-font `/FontDescriptor /MissingWidth` supplies the default width for character codes not covered by `/Widths`:

- https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py

The native PHP fallback therefore needs object-aware Type3 CharProc width handling, descriptor MissingWidth fallback, and descriptor flags/weight preservation before WordPress paragraph grouping.

## Native Behavior

`PdfTextExtractor::fontWidthMetrics()` now resolves simple-font `FontDescriptor /MissingWidth` through the existing object-aware descriptor and numeric resolver path. The value becomes the default glyph advance used when a Type3 text source code is outside the explicit `/Widths` array and has no matching `/CharProcs` d0/d1 width.

The focused fixture uses:

- a Type3 font with `/CharProcs 21 0 R` containing wide d0 glyphs and thin d1 glyphs;
- `/Widths 22 0 R` for codes 65-68;
- `/FontDescriptor 23 0 R` with indirect `/MissingWidth 24 0 R`, flags, weight, and a review-only `/CIDSet 26 0 R` stream;
- `/ToUnicode` for visible text decoding.

After the fix, glyphs outside `/Widths` and `/CharProcs` use descriptor MissingWidth so `MissWide` stays joined, while thin CharProc widths still preserve `Thin Join`. The CIDSet descriptor stream remains excluded from visible text.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses Type3 CharProc widths and descriptor MissingWidth before WordPress text grouping on current base
Expected: ['MissWide', 'Thin Join']
Actual: ['Miss Wide', 'Thin Join']
1 test files, 1 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontEncodingDifferencesCMapWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 691 assertions, 0 failures
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charproc-cidset-descriptor-currentbase.php
No syntax errors detected in all changed PHP files.
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charproc-cidset-descriptor-currentbase.php
```

The smoke emits Gutenberg paragraphs for `MissWide` and `Thin Join`, with `descriptor_flags_preserved=true`, `descriptor_weight_preserved=true`, `missing_width_gap_preserved=true`, `charproc_width_gap_preserved=true`, `cidset_payload_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

JSON validation:

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
markerpdf json ok
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
passed with no output
```

## Status Delta

- Behavior tests: `609 -> 610`.
- Focused new test: `10` assertions.
- Mapped upstream/dependency semantics: `442 -> 443 / 78`.
- WordPress scenarios: `609 -> 610`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, Type3 CharProc width parser, indirect array and numeric operand resolver, descriptor flag/weight extraction, ToUnicode CMap parser, positioned text grouping path, and WordPress smoke path. Full upstream runner parity remains blocked by the Python/PDF/model stack: pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, benchmark scripts, Streamlit/FastAPI paths, and external conversion workers.

## Non-Overlap

This does not repeat accepted direct Type3 CharProc width handling, indirect Type3 Encoding Differences, simple-font indirect FirstChar/Widths, Type0 CIDFont /W or /DW defaults, CIDSet vertical surrogate grouping, indirect Type0 Encoding names, or FontDescriptor flag-only styled-span extraction. The new boundary is specifically descriptor `/MissingWidth` fallback for Type3 glyph codes outside explicit `/Widths` and `/CharProcs`, with descriptor flags/weight and CIDSet stream exclusion preserved.

Root harness: not run - isolated micro-slice.
