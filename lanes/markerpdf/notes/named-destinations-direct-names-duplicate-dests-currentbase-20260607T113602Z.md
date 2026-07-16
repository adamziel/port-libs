# markerPDF Direct Names Duplicate Dests Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260607T113602Z`
Session: `port-dev-markerpdf-named-destinations-20260607T113602Z`
Base accepted HEAD: `f716e4283d84f7b543276d2a9be0237167ba1fa0`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable-PDF navigation metadata through pdftext/PDFium before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps a native PDF dictionary boundary for catalog `/Names /Dests`: duplicate decoded `/Dests` keys inside a direct inline catalog `/Names` dictionary are ambiguous, so the name-tree root fails closed before standalone WordPress named-destination review.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium raster execution, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor` now preserves the existing indirect `/Names` duplicate-key guard and also scans the raw direct inline catalog `/Names` dictionary for duplicate decoded `/Dests` keys such as `/#44ests ... /Dests ...`.
- Ambiguous direct name-tree roots are rejected by the standalone extractor before destination rows are normalized.
- Valid legacy catalog `/Dests` fallback remains available as review metadata.
- Destination labels and URI/action target text remain review metadata and do not leak into visible WordPress paragraphs.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDirectNamesDuplicateDestsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on direct catalog Names Dests duplicate keys before standalone destination review
Expected: array (0 => 'LegacyOk')
Actual: array (0 => 'Stale Tree', 1 => 'LegacyOk')
PASS keeps direct duplicate Names Dests rows out of link promotion and visible WordPress text
1 test files, 23 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDirectNamesDuplicateDestsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on direct catalog Names Dests duplicate keys before standalone destination review
PASS keeps direct duplicate Names Dests rows out of link promotion and visible WordPress text
1 test files, 37 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*Test\.php$' | sort)
Focused test run: 45 selected test files (root lock skipped)
45 test files, 1326 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-direct-names-duplicate-dests-currentbase.php
Result: exits 0 and emits destination_names=[LegacyOk], document_destination_names=[LegacyOk], promoted_link_objects=[9,10], direct_duplicate_name_tree_hidden=true, visible_text_excludes_destination_metadata=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
php -l lanes/markerpdf/tests/PdfNamedDestinationDirectNamesDuplicateDestsBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-direct-names-duplicate-dests-currentbase.php
Result: no syntax errors detected.
```

```text
git diff --check -- lanes/markerpdf
Result: no output.
```

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
Result: json ok.
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2849 -> 2851`.
- `wordpressScenarios`: `2389 -> 2390`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationDirectNamesDuplicateDestsBoundaryCurrentBaseTest.php` adds 2 PASS cases and 37 assertions.
- New WordPress smoke: `wordpress-pdf-named-destination-direct-names-duplicate-dests-currentbase.php`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token parser, raw dictionary scanner, generation-aware resolver, name-tree walker, page-tree indexer, destination normalizer, metadata extractor, link promotion path, text extractor, Markdown post-processor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted indirect catalog `/Names /Dests` duplicate-key handling, duplicate name-tree leaf row precedence, direct `/Limits` pruning, malformed leaf/intermediate `/Limits` fallback, indirect `/Kids`/`/Names`/`/Limits` arrays, child `/Kids` ordering by `/Limits`, PDFDocEncoding byte comparisons, indirect view operands, PDF name-key rejection, page-operand validation, non-GoTo action dictionary rejection, destination view-mode validation, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, outline destination action context, PageLabels number-tree ordering, link rectangle geometry, metadata root selection, attachments, fonts, images, stream filters, tables, or runtime conversion behavior. The bounded behavior is only duplicate decoded `/Dests` keys inside a direct inline catalog `/Names` dictionary before standalone named-destination extraction.

## Next Task

Continue non-overlapping native searchable-PDF parser work around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
