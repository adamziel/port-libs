# markerPDF Type3 CharProc ToUnicode Current Base

Session: `port-dev-markerpdf-font48-20260602T2026Z`
Micro-slice: `font-type3-charproc-to-unicode-currentbase`
Base accepted HEAD: `2bf77cd5f648f9f608014de847ea7b020b711784`

## Source Truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes low-level PDF text extraction through `pdftext.extraction.dictionary_output()` and then preserves extracted span text/font metadata in `marker/pdf/extract_text.py`.
- PDFium Type3 font loading resolves an Encoding to an Adobe glyph name, looks up that name in `/CharProcs`, and parses the charproc as a glyph form, not as page-visible text.
- Current pypdf dependency behavior treats Type3 fonts without `/ToUnicode` as interpretable only when all `/CharProcs` names map to standard Adobe glyph names.
- PDF reference behavior for Type3 fonts: `/CharProcs` keys are character names and each stream draws the character, with `d0` or `d1` providing glyph metrics.

Source links checked:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://pdfium.googlesource.com/pdfium/+/refs/heads/chromium/6489/core/fpdfapi/font/cpdf_type3font.cpp
- https://socket.dev/pypi/package/pypdf/diff/6.7.0
- https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.2.pdf

## Native Behavior

`PdfTextExtractor` now builds a bounded Type3 fallback Unicode map when a font has no `/ToUnicode`, its `/CharProcs` dictionary names all resolve through the native Adobe glyph-name mapping, and the active `/Encoding` CMap maps source bytes to matching CIDs. The fallback maps source text codes to glyph-name Unicode while preserving the existing CMap source boundaries and CID widths.

The glyph streams remain glyph-only: text-looking operators inside CharProc streams are not promoted to visible WordPress paragraphs.

## Evidence

Red-first focused check added for the current-base gap:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php
The accepted base had no Type3 no-ToUnicode CharProc glyph-name Unicode fallback for CMap source codes. During implementation, the first focused runs caught the missing map wiring/type boundary and an over-broad width fixture; the final fixture uses disjoint wide uppercase glyphs and thin lowercase glyphs.
```

New focused check after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS maps Type3 CMap source codes through CharProc glyph names before WordPress text extraction on current base

1 test files, 9 assertions, 0 failures
```

Focused font/text family after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontEncodingDifferencesCMapWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 633 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charproc-to-unicode-currentbase.php
charproc_glyph_names_decode_text=true; raw_source_controls_excluded=true; charproc_payload_visible_text_excluded=true; paragraphs include WIDEBLOCK and thin text
```

Validation:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-type3-charproc-to-unicode-currentbase.php
No syntax errors detected in all 3 changed PHP files.

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
markerpdf json ok

git diff --check -- lanes/markerpdf
passed with no output
```

## Status Delta

- Behavior tests: `781 -> 782`.
- Mapped upstream/dependency semantics: `555 -> 556 / 78`.
- WordPress scenarios: `781 -> 782`.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, CMap parser, Type3 CharProc dictionary parser, Adobe glyph-name fallback table, text-source segmentation, glyph-width grouping, and WordPress smoke path. Full upstream runner parity remains blocked by pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI paths, benchmark tooling, and external PDF/model execution.

## Non-Overlap

This does not repeat accepted Type3 CharProc `d0`/`d1` width handling, Type3 descriptor `/MissingWidth`, Type3 `/Encoding` CMap CIDSet width grouping, indirect Type3 Encoding Differences, simple-font Encoding Differences, subset glyph-name decoding, Type0 CID width mapping, or ToUnicode CMap row-count/surrogate handling. The new boundary is specifically Type3 no-`/ToUnicode` Unicode recovery from standard `/CharProcs` glyph names selected through an active CMap, with CharProc drawing payloads kept out of visible text.

Root harness: not run - isolated micro-slice.
