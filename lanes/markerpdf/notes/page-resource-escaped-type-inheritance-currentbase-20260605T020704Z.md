# markerPDF Page Resource Escaped Type Inheritance Current Base

Lane: `markerpdf`
Slice: `markerpdf-page-resource-inheritance-current-base-20260605T020704Z`
Base accepted HEAD: `542dde5fff8943ee8938b6e2d7f5e57b947893fe`

## Source Truth

PDF name tokens may use `#hh` escapes. Page-tree resource inheritance depends on recognizing `/Type /Page` leaves and `/Type /Pages` ancestors before resolving inherited `/Resources`. Upstream markerPDF reaches this through PDFium/pdftext page objects; this PHP no-GPU slice keeps the same searchable-PDF boundary without invoking Python, pypdfium, OCR models, or external PDF tools.

## Behavior

`PdfTextExtractor` and `PdfPagePropertyExtractor` now decode top-level `/Type` name values before deciding whether an object is a Catalog, Page, or Pages node. A leaf with `/Type /P#61ge` and an ancestor with `/Type /Pa#67es` now remains in the catalog page tree, inherits the ancestor resource dictionary, decodes the inherited Type0 font, expands the inherited Form XObject, and reports inherited resource metadata for WordPress review. Fallback stream scanning no longer treats the page stream and inherited Form stream as separate page texts for this valid escaped-name page tree.

## Evidence

Red-first focused run before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
FAIL inherits resources through escaped page tree Type names before WordPress text extraction
Expected: 'Escaped type inherited font text
Escaped type inherited form text
'
Actual: 'Escaped type inherited font text

Escaped type inherited form text
'
1 test files, 60 assertions, 1 failures
```

After source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 68 assertions, 0 failures
```

Adjacent resource/property run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
6 test files, 396 assertions, 0 failures
```

Broader page-discovery text extractor run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 628 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-resource-inheritance-import.php
```

The smoke emits `decodes_escaped_page_tree_type_names=true`, seven Gutenberg paragraph blocks, and no Python/model/external-tool execution flags.

Syntax/diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-resource-inheritance-import.php
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted top-level page `/Parent` parsing, indirect null `/Resources` inheritance, generation-mismatched resource fail-closed behavior, stale resource-entry generation suppression, nested private resource decoy exclusion, legacy Form XObject omitted-`/Resources` fallback, or the earlier escaped font-resource-name/filter-name slice. The new boundary is specifically decoded top-level page-tree `/Type` names before page discovery and inherited resource lookup.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, PDF name decoder, page-tree walker, stream decoder, CMap/font-resource mapping, Form XObject expansion, page-boundary review metadata, and WordPress smoke path. Live OCR/model execution and exact upstream GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
