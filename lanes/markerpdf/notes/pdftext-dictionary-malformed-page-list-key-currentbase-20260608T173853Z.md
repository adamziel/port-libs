# markerpdf pdftext dictionary malformed page-list key boundary current-base

- Slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T173853Z`
- Accepted base: `a6a647ebd353274fa49c3f82015976e88c6af903`
- Scope: native no-GPU pdftext dictionary core boundary only.

## Source Truth

Upstream `pdftext.dictionary_output(...)` returns an ordered list of page dictionaries. MarkerPDF consumes that selected page list before page/block/span conversion. Native WordPress import caches may wrap the list under `dictionary_output`, `pdftext`, `pages`, `page_map`, or `pageMap`, but a known page-list key that is present and malformed is corrupt cache state, not permission to scan sibling numeric adapter rows.

## Implemented Boundary

- `PdfTextDocumentExtractor::pageListFromExplicitDictionaryEnvelope()` now remembers malformed known page-list keys inside explicit `dictionary_output`/`pdftext` envelopes.
- If no valid page list is recovered from those known keys, the explicit cache envelope fails closed instead of falling back to sibling numeric rows.
- This prevents stale or injected numeric adapter rows from becoming WordPress paragraphs when `pages`, `page_map`, or `pageMap` is malformed.
- No Python pdftext, pypdfium, OCR, CUDA/Torch, raster rendering, model workers, multiprocessing, or external PDF tools are used.

## Evidence

Red-first focused run before the production fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreMalformedPageListKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed explicit dictionary_output pages keys before sibling page rows
Expected exception InvalidArgumentException was not thrown
FAIL rejects malformed explicit pdftext page_map keys before sibling page rows
Expected exception InvalidArgumentException was not thrown
1 test files, 2 assertions, 2 failures
```

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreMalformedPageListKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed explicit dictionary_output pages keys before sibling page rows
PASS rejects malformed explicit pdftext page_map keys before sibling page rows
1 test files, 2 assertions, 0 failures
```

Adjacent family verification:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfTextDictionary*Test.php' | sort)
34 test files, 2256 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-malformed-page-list-key-currentbase.php
malformed_dictionary_output_pages_key_rejected=true
malformed_pdftext_page_map_key_rejected=true
sibling_numeric_rows_not_imported=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat BOM JSON envelope decoding, nested explicit dictionary_output precedence, page_map/pageMap valid unwrapping, duplicate normalized page-map keys, JSON list-entry decoding, layout/order typed payload ambiguity, runtime preflight boundaries, fonts/CMaps, stream filters, xref repair, metadata, annotations, forms, security preflight, image/filter review metadata, or table/equation supplied-boundary handoffs.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `pdf-text-dictionary-core-boundary` adapter. Remaining live OCR/model parity is intentionally out of scope under the current no-GPU markerPDF directive, not a blocker for this searchable-PDF/import-cache boundary.

## Next

Continue with non-overlapping native markerPDF parser/converter behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations/forms/security, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
