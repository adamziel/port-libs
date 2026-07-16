# markerPDF Page Resource Direct Duplicate Entry Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260608T071228Z`
Session: `port-dev-markerpdf-resource-inherit-20260608T071228Z`
Base accepted HEAD: `9810a22ae19fe27e90898760045e29d34467c0ba`

## Source Truth

Upstream markerPDF obtains searchable PDF text from PDF parser/PDFium layers before OCR/model execution. In the native no-GPU scope, page-tree `/Resources` is an inherited parser lookup root, and duplicate resource entry names are unsafe because they can donate stale ToUnicode maps or marked-content replacement text into WordPress paragraphs. Existing current-base behavior already rejected duplicate indirect resource references; this slice extends the same fail-closed boundary to duplicate direct dictionary entries.

## Implementation

- `PdfTextExtractor::duplicateTopLevelResourceReferenceNames()` now treats every top-level resource entry name as countable for duplicate suppression, not only indirect references.
- `PdfPagePropertyExtractor::duplicateResourceSubdictionaryReferenceNames()` now applies the same value-type-agnostic duplicate-name suppression to page-boundary resource metadata.
- Added a focused PDF fixture where inherited `/Font` contains duplicate direct `/Fdup` font dictionaries and inherited `/Properties` contains duplicate direct `/DupActual` dictionaries. The stale/current duplicate values are suppressed, while valid direct siblings `/Fvalid` and `/ValidActual` still import.
- Added a WordPress smoke that emits safe Gutenberg paragraphs and review metadata proving duplicate direct resource text is excluded without Python/models/external PDF tools.

## Red / Green Evidence

Pre-fix focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDirectDuplicateEntryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects duplicate direct inherited resource entry names before font maps ActualText and metadata
Expected lines started with raw "A"; actual lines used "Current direct duplicate font leak" and "Current direct duplicate ActualText leak".
1 test files, 1 assertions, 1 failures
```

Post-fix focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDirectDuplicateEntryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects duplicate direct inherited resource entry names before font maps ActualText and metadata
1 test files, 17 assertions, 0 failures
```

Adjacent page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 43 selected test files (root lock skipped)
43 test files, 1245 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-direct-duplicate-entry-currentbase.php
```

The smoke exits 0 and reports `duplicate_font_suppressed=true`, `duplicate_actual_text_suppressed=true`, `review_font_names=["Fvalid"]`, `review_properties_names=["ValidActual"]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, page `/Resources null`, indirect null resources, indirect or direct tailed `/Resources` values, malformed `/Parent` references, generation-exact `/Kids`, duplicate `/Resources` keys, duplicate indirect resource entry references, malformed entry tails, category stream rejection, ProcSet review, image XObject inheritance review, annotation appearance inheritance, form null-resource inheritance, page `/Contents` non-inheritance, xref repair, metadata, forms, annotations, or OCR/model handoffs. The bounded behavior is only duplicate direct dictionary entries inside inherited `/Font` and `/Properties` resource subdictionaries before ToUnicode, ActualText, and page-resource metadata lookup.

## Dependency Closure

No new support component is needed. This reuses the existing native PDF tokenizer, page-tree resource inheritance, ToUnicode CMap parser, marked-content properties extraction, and page-boundary metadata paths. GPU/model/OCR execution, pypdfium/PIL raster rendering, Python, and external PDF tools remain intentionally out of scope.
