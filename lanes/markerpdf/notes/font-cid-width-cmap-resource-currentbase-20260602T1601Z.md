# markerPDF Font CID Width CMap Resource Current Base

Session: `port-dev-markerpdf-font27pdf-20260602T1601Z`

Micro-slice: `font-cid-width-cmap-resource-currentbase-20260602T1601Z`

Base accepted HEAD: `53265cb17476632b769b039d29a72b1721c0e1a5`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction and font geometry to `pdftext.extraction.dictionary_output` before Marker groups text into page lines and blocks. This native PHP fallback keeps that boundary by resolving the Type0 font encoding CMap before descendant CIDFont width metrics decide whether adjacent text fragments belong in the same WordPress paragraph.

Relevant upstream/dependency references already captured in the lane manifest:

- `marker/pdf/extract_text.py` uses `dictionary_output` for PDF text dictionaries.
- `pyproject.toml` pins the PDF text/PDFium dependency surface used before Marker block conversion.
- The PDF font/CMap dependency behavior is object-aware: Type0 `/Encoding` CMaps map source codes to descendant CIDs, and descendant CIDFont `/W` widths are keyed by those CIDs rather than by the raw source bytes.

## Native Behavior

`PdfTextExtractor::fontCidEncodingMap()` now resolves a Type0 font with `/Encoding /SomeCMapName` through the decoded CMap resource inventory built by `namedCMapBodies()`. It also handles an indirect encoding object whose body is a name. The resolved CMap is parsed with the existing `parseCidCMap()` path, including `usecmap` base resources and cycle protection, before descendant CIDFont `/W` widths are attached to the ToUnicode text map.

The focused PDF declares:

- object 7: base CMap `/WPBaseResource-H` mapping source bytes `<01>` through `<09>` to CIDs 40-48;
- object 3: named Type0 encoding CMap `/WPResourceWidth-H` that uses `/WPBaseResource-H usecmap` and maps source bytes `<14>` through `<1B>` to CIDs 60-67;
- font object 2: `/Encoding /WPResourceWidth-H`, `/ToUnicode 6 0 R`, and descendant CIDFont `/W [40 48 1000 60 67 250]`.

Before the fix the named `/Encoding` was not resolved as a CID CMap, so width lookup used raw source bytes and `/DW 500`, producing `Wide Block` and `ThinText`. After the fix, the named CMap resource supplies the CIDs and the same content emits `WideBlock` and `Thin Text`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves named Type0 CMap resources before CIDFont width grouping on current base
Expected: ['WideBlock', 'Thin Text']
Actual: ['Wide Block', 'ThinText']
1 test files, 1 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves named Type0 CMap resources before CIDFont width grouping on current base
1 test files, 7 assertions, 0 failures
```

Adjacent font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
4 test files, 638 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-cid-width-cmap-resource-currentbase.php
```

The smoke emitted `named_encoding_cmap_resource_resolved=true`, `usecmap_base_resource_applied=true`, `descendant_cid_widths_selected=true`, `raw_source_width_fallback_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraphs for `WideBlock` and `Thin Text`.

Changed PHP lint passed:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-cid-width-cmap-resource-currentbase.php
```

Final whitespace/JSON gate:

```text
git diff --check -- lanes/markerpdf
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
```

Status delta: behavior tests `533 -> 534`; mapped semantics `380 -> 381 / 78`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, stream decoder, CMap decoder, named CMap inventory, `usecmap` CMap parser, descendant CIDFont width parser, text-position grouping path, and WordPress smoke path. Full upstream markerPDF runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, and benchmark tooling.

## Non-Overlap

This does not repeat nearest page font resource scoping, direct or indirect Type0 CMap stream operands, Identity-H/V fallback, predefined vertical CMap writing-mode detection, ToUnicode usecmap inheritance, decimal `/W` parsing, indirect CIDFont `/W`, vertical `/W2`, CIDSet/default-width grouping, simple-font widths, or descriptor-only defaults. The new boundary is specifically named Type0 `/Encoding /CMapName` resource resolution before descendant CIDFont `/W` width grouping on the current base.
