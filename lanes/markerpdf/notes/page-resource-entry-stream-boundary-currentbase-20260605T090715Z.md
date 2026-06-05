# markerPDF page resource entry stream boundary current-base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T090715Z`
Session: `port-dev-markerpdf-resource-inherit-20260605T090715Z`
Base accepted HEAD: `ff5511ebaa7007fb5360709d25981536ab21fcaf`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text through pdftext/PDFium-backed page extraction before OCR/model stages. In the native no-GPU PHP lane, page-tree `/Resources` inheritance is the parser boundary before WordPress paragraph import. PDF resource dictionaries may reference stream objects for `/XObject` entries, but `/Font` and marked-content `/Properties` entries are dictionary resources; a malformed inherited stream entry must not be used as a font map or ActualText property list.

## Behavior

`PdfTextExtractor` now rejects inherited `/Font` and `/Properties` resource entries when the referenced object resolves to a stream object. The same stream entry is still valid when it appears under `/XObject`, preserving Form XObject expansion and image/media review semantics.

`PdfPagePropertyExtractor` now mirrors that boundary in review metadata: stream-backed `/Font` and `/Properties` entries are not listed as `font_names` or `properties_names`, while valid `/XObject` stream entries remain visible as review-only resource names.

## Verification

Red-first probe before implementation:

```bash
php <<'PHP'
// Fixture with inherited /Font << /F1 5 0 R >> and /Properties << /P1 7 0 R >>,
// where objects 5 and 7 are stream objects.
PHP
```

The probe emitted `Stream font entry leak` and `Stream property ActualText leak`, and page-boundary metadata listed `font_names=["F1"]` plus `properties_names=["P1"]`.

Focused test after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceEntryStreamBoundaryCurrentBaseTest.php
```

Passed: 1 test file, 15 assertions, 0 failures.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-page-resource-entry-stream-boundary-currentbase.php
```

Passed with `stream_font_entry_rejected=true`, `stream_property_entry_rejected=true`, `xobject_stream_still_valid=true`, `visible_paragraph_count=3`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent page-resource family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceEntryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
```

Passed: 11 test files, 569 assertions, 0 failures.

Syntax/diff checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceEntryStreamBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-entry-stream-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All syntax checks and `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat accepted font ToUnicode/width resource inheritance, leaf `/Resources` no-partial-merge behavior, page-resource generation guards, malformed top-level `/Resources`, stream-valued resource category rejection, resource entry generation rejection, Form XObject resource inheritance, image XObject inherited-owner review, optional-content image invocation review, xref/object-stream recovery, or payload exclusion. The bounded behavior is only stream-object rejection for inherited `/Font` and `/Properties` resource entries while preserving valid `/XObject` stream entries.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-aware resource reference resolver, page-tree resource inheritance path, stream-object detector, text extractor, page-boundary review extractor, and WordPress smoke renderer. Live OCR, PDFium rendering, Surya/Texify/Torch model execution, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
