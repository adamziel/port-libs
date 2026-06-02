# markerPDF Font CMap CID Type3 Width Spacing Bundle Current Base

Session: `port-dev-markerpdf-font70-20260602T215950Z`

Micro-slice: `font-cmap-cid-type3-width-spacing-bundle-currentbase`

Base accepted HEAD: `5cd9230be04519cb4852fe5076346eb28b7e6962`

## Source Truth

Upstream `sddai/markerPDF` remains pinned in this lane manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream visible PDF text flows through `marker/pdf/extract_text.py::get_text_blocks()` into `pdftext.extraction.dictionary_output()` and through `naive_get_text()` into pypdfium text pages:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

This native slice owns the reduced PHP boundary below that upstream pdftext/PDFium layer: content-stream text operators, explicit font Encoding CMaps, ToUnicode mappings, CID-based widths, and text-state word spacing must be resolved before WordPress paragraph grouping. The adjacent accepted Type3 CMap spacing slice covered CID 32 as the space character. This slice covers the inverse source-code boundary: a raw source code that looks like `0x20` is not a word-space source when the explicit Type3 Encoding CMap maps it to a non-space CID.

## Native Behavior

`PdfTextExtractor::sourceKeyUsesWordSpacing()` now consults the explicit CMap CID map before treating raw source code `0x20` as word-spacing eligible. When a Type3 font has an Encoding CMap entry `<0020> 65`, the source code decodes through ToUnicode to `A`, uses CID 65 for width and word-spacing decisions, and does not receive extra `Tw` spacing merely because the raw bytes look like a space.

The focused fixture uses a Type3 font with:

- `/Encoding` CMap mapping `<0020> -> 65` and `<0021> -> 66`;
- `/ToUnicode` mapping those source codes to `A` and `B`;
- `/Widths [500 500]` for CIDs 65 and 66;
- the double-quote text operator with `Tw` and `Tc`, followed by a positioned `Tm` gap.

Before the fix the quote operator incorrectly counted raw `<0020>` as a space, so the following positioned gap was under-threshold and visible text collapsed to `AB`. After the fix, the explicit non-space CID wins and the positioned WordPress paragraph text is `A B`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses Type3 CMap CIDs rather than raw 0x20 for quote operator word spacing on current base
Expected: ['A B']
Actual: ['AB']
1 test files, 1 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 7 assertions, 0 failures
```

Adjacent font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 641 assertions, 0 failures
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-cmap-cid-type3-width-spacing-bundle-currentbase.php
No syntax errors detected in all changed PHP files.
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-cmap-cid-type3-width-spacing-bundle-currentbase.php
```

The smoke emits a JSON review comment with `explicit_cid_overrides_raw_0x20_for_word_spacing=true`, `positioned_word_gap_preserved=true`, `raw_source_code_hidden_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraph `<p>A B</p>`.

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

- Behavior tests: `883 -> 884`.
- Focused new test: `7` assertions.
- Mapped upstream/dependency semantics: `623 -> 624 / 78`.
- WordPress scenarios: `883 -> 884`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, content-stream tokenizer, Type3 Encoding CMap parser, ToUnicode CMap parser, CID width lookup, positioned text grouping path, and WordPress smoke renderer. Full upstream runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded PHP slice.

## Non-Overlap

This does not repeat accepted Type3 CharProc width handling, Type3 CIDSet descriptor fallback, simple Type3 CMap spacing where CID 32 is the space, Type0 CIDFont width/CMap grouping, source-space bidi ToUnicode replacement text, image/font/security/table/parser batches, or upstream runner metadata work. The new boundary is specifically explicit Type3 Encoding CMap CIDs overriding raw source-code `0x20` for double-quote word-spacing decisions.

Root harness: not run - isolated micro-slice.
