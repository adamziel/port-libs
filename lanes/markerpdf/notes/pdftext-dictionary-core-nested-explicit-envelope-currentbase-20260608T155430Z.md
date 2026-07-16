# pdftext Dictionary Core Nested Explicit Envelope Boundary

Session: `port-dev-markerpdf-pdftext-dictionary-20260608T154652Z`
Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T154652Z`
Accepted base: `d52b47d5f0116b3f13f1ca89d35e0306d7bf8730`

## Source Truth

`pdftext.dictionary_output` emits an ordered list of page dictionaries. MarkerPDF consumes those selected page dictionaries before layout/order assignment. Native cache adapters may wrap that selected `dictionary_output`/`pdftext` payload inside another explicit cache envelope, but stale wrapper `pages` must not replace the nested selected page list.

## Behavior

- `PdfTextDocumentExtractor::pageListFromExplicitDictionaryEnvelope()` now checks nested explicit `dictionary_output` and `pdftext` cache values before falling back to wrapper `pages`.
- Safe pdftext span URLs and page refs are still preserved after unwrapping.
- Wrapper metadata, stale wrapper page text, raw page/span payloads, and adapter sidecars stay out of WordPress paragraph text and serialized document metadata.
- No Python pdftext, pypdfium, OCR, CUDA/Torch, raster rendering, model worker, or external PDF tool execution is used.

## Evidence

Red-first focused run before the production fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreNestedExplicitEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL prefers nested explicit dictionary_output caches before stale wrapper pages at the core boundary
Expected: 8801
Actual: 7801
FAIL prefers nested explicit pdftext JSON caches before stale wrapper pages at the core boundary
Expected: 9901
Actual: 8901
1 test files, 6 assertions, 2 failures
```

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreNestedExplicitEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS prefers nested explicit dictionary_output caches before stale wrapper pages at the core boundary
PASS prefers nested explicit pdftext JSON caches before stale wrapper pages at the core boundary
1 test files, 34 assertions, 0 failures
```

Adjacent family verification:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfTextDictionary*Test.php' | sort)
28 test files, 2111 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-core-nested-explicit-envelope-currentbase.php
nested_explicit_cache_selected=true
stale_wrapper_pages_excluded=true
safe_pdftext_link_promoted=true
pdftext_ref_preserved=true
wrapper_payload_excluded=true
executes_python_pdftext=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is needed. This reuses the existing native `pdf-text-dictionary-core` boundary and only tightens explicit cache-envelope selection before downstream WordPress import.

## Next Task

Continue no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior: fonts, CMaps, stream filters, xref repair, metadata, annotations/forms/security, page geometry, image/filter review metadata, or supplied table/equation handoffs.
