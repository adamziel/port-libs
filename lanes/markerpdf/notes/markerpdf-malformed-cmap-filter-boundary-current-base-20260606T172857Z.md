# markerpdf-malformed-cmap-filter-boundary-current-base-20260606T172857Z

## Scope

- Lane: markerpdf
- Accepted base: `f0133633366ca90b1289c4c40a7a9202c36d9be1`
- Cluster: native searchable-PDF Type0 Encoding CMap `/WMode` token boundary on filtered CMap streams.
- Non-overlap: this is not another malformed stream `/Filter` operand rejection, DecodeParms alignment, post-`/Length` tail scan, duplicate filter, CMap row-count, usecmap, Type3, OCR, Surya/Texify/Torch, or model-worker slice. It keeps valid `/Filter /FlateDecode` CMap streams decodable while constraining CMap program parsing.

## Behavior

PDF CMaps can declare vertical writing mode with a top-level `/WMode 1 def`. The existing parser used a raw regex over the bounded decoded CMap program, so a filtered Encoding CMap containing decoy text such as `(/WMode 1 def) pop` or `<< /Note (/WMode 1 def) >> pop` incorrectly flipped Type0 text grouping to vertical writing. WordPress paragraph extraction then merged horizontal text as `VertImport` and `Data Flow`.

The patch adds token-aware CMap `/WMode` scanning that follows the same literal-string, dictionary, array, hex, and comment boundaries used by CMap operator discovery. Real top-level `/WMode` directives still set writing mode, while decoys inside literals or nested dictionaries are ignored. The filtered CMap remains valid and decodes through the native `FlateDecode` stack.

## Evidence

- Red-first focused run after adding the focused test:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapWModeFilterBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 5 assertions, 1 failures`.
  - Failure: literal `(/WMode 1 def)` decoy produced `["VertImport","Data Flow"]` instead of horizontal `["Vert","Import","Data","Flow"]`.
- Focused test after fix:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapWModeFilterBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 22 assertions, 0 failures`.
- Adjacent CMap filter and vertical-writing family:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapWModeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayFilterTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapLengthOperandFilterBoundaryCurrentBaseTest.php`
  - Result: `6 test files, 1756 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-wmode-filter-boundary-currentbase.php --self-test`
  - Result: `self_test_passed=true`, `filtered_cmap_decoded=true`, `literal_wmode_decoy_ignored=true`, `safe_lines=["Vert","Import","Data","Flow"]`, `decoded_cmap_count=2`, `filters=["FlateDecode"]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Syntax and diff checks:
  - `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
  - `php -l lanes/markerpdf/tests/PdfParserMalformedCMapWModeFilterBoundaryCurrentBaseTest.php` => no syntax errors.
  - `php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-wmode-filter-boundary-currentbase.php` => no syntax errors.
  - `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'` => both JSON files OK.
  - `git diff --check -- lanes/markerpdf` => no whitespace errors.

## Dependency Closure

No new support component is needed. The slice stays inside the existing native PHP PDF tokenizer, CMap parser, stream filter decoder/review path, and WordPress smoke harness. GPU/model OCR, pypdfium/PIL raster execution, external PDF tools, Surya, Texify, Torch, Streamlit/FastAPI model workers, and live-service providers remain intentionally out of scope under the markerPDF no-GPU directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around CMap token boundaries, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
