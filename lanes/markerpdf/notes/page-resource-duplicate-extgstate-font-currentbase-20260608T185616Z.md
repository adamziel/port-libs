# Page Resource Duplicate ExtGState Font Current Base, 2026-06-08

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260608T185616Z`
Session: `port-dev-markerpdf-resource-inherit-20260608T185616Z`
Base accepted HEAD: `be1daac3955666cd7f4550d89b27b78d713e0ae0`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed `pdftext.extraction.dictionary_output()` and pypdfium page text before Marker block conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Adobe PDF Reference 1.3 documents page attribute inheritance and content-stream resources: https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.3.pdf
- PDF page `/Resources` is an inheritable page-tree attribute. Content-stream `gs` operands select named `/ExtGState` resources from the effective resource dictionary, and ambiguous duplicate names must not choose a stale or later same-name entry before WordPress text import.

## Change

- `PdfTextExtractor::extGStateFontTextStatesForResourceOwnerBody()` now applies the same duplicate resource-name guard already used by page-resource review metadata.
- Duplicate inherited `/ExtGState` names are skipped before `gs` font text-state rewrites, so neither stale nor current duplicate CMaps can replace visible glyph fallback text.
- Valid sibling ExtGState resources still apply their `/Font [/Fname size]` text state and decode searchable text normally.

## Red-First Evidence

Before the production edit, the new fixture selected the later duplicate `/Dup Text` ExtGState and leaked its CMap text:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateExtGStateFontCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects duplicate inherited ExtGState font names before gs text-state leaks stale mappings
Expected: ['A', 'Valid inherited ExtGState font text']
Actual: ['Current duplicate ExtGState font leak', 'Valid inherited ExtGState font text']
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateExtGStateFontCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects duplicate inherited ExtGState font names before gs text-state leaks stale mappings
1 test files, 14 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 54 selected test files (root lock skipped)
54 test files, 1429 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-extgstate-font-currentbase.php
exits 0 with duplicate_extgstate_names_rejected=true, valid_extgstate_font_preserved=true, resource_names_excluded_from_paragraphs=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, effective page-resource resolver, duplicate resource-name detector, ExtGState `/Font` text-state rewrite path, CMap/font maps, page-boundary metadata extractor, and WordPress smoke harness. GPU/model OCR, pypdfium/PIL raster rendering, live Surya/Texify/Torch execution, PDF action execution, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted page resource null/omitted inheritance, parent lineage repair, comment-delimited references, object wrappers, malformed resource fail-closed behavior, duplicate Font/Properties entries, duplicate XObject `Do` expansion, duplicate Type3 font review, page-local resource override behavior, ExtGState font-array application, image XObject transparency review, forms, annotations, xref repair, stream filters, OCR/model work, or external PDF tooling. The bounded behavior is specifically duplicate inherited `/ExtGState` resource names in the `gs` font text-state rewrite path.
