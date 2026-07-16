# markerPDF Page Resources stream-object boundary

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T041825Z`

Accepted base: `6e5d902022f755a3c78c4c384261dbbf7101f888`

## Source truth

- PDF page `/Resources` is an inheritable page attribute whose effective value is a resource dictionary. A stream object is not a valid page resource dictionary just because its stream dictionary contains `/Font`, `/XObject`, or other resource-like keys.
- Upstream markerPDF reaches this boundary through PDFium/pdftext object resolution. The native no-GPU PHP path must therefore reject explicit page `/Resources` stream objects before searchable text extraction, Form XObject expansion, and WordPress paragraph rendering.

## Change

- `PdfTextExtractor` now treats an indirect page or Form `/Resources` operand that resolves to a stream object as malformed for resource-dictionary lookup.
- `PdfPagePropertyExtractor` applies the same stream-object rejection before page-boundary resource metadata, so review rows report `unresolved_or_malformed` instead of promoting stream dictionary keys.
- Added a focused fixture where a page has `/Resources 12 0 R`, and object `12` is a stream object with decoy `/Font` and `/XObject` entries. The page should emit only raw `A`, not the stream-resource font text, Form XObject text, parent inherited resource text, or resource stream payload text.
- Added a WordPress smoke that emits Gutenberg paragraph output plus flags proving no Python, OCR/model, external PDF tool, stream dictionary promotion, parent-resource inheritance, or stream-payload promotion occurred.

## Evidence

Red-first command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php
```

Failed before implementation:

```text
FAIL fails closed on page Resources stream objects instead of inheriting parent or promoting stream dictionaries
Expected: array (0 => 'A')
Actual: array (0 => 'Stream resource font leak', 1 => 'Stream resource form leak')
1 test files, 1 assertions, 1 failures
```

Passing focused command after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php
```

Result:

```text
PASS fails closed on page Resources stream objects instead of inheriting parent or promoting stream dictionaries
1 test files, 15 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-resource-stream-boundary-currentbase.php
```

Result: exits `0` and emits `resource_status="unresolved_or_malformed"`, `resource_owner_object=3`, `resource_object=12`, `stream_resource_dictionary_promoted=false`, `parent_resource_inherited_after_malformed_stream=false`, and `stream_payload_promoted=false`.

Final related-family verification:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php
```

Result: `6 test files, 207 assertions, 0 failures`.

Additional checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-resource-stream-boundary-currentbase.php
php -r '$json = file_get_contents("lanes/markerpdf/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

Result: all passed.

## Non-overlap

This does not repeat accepted page-tree resource inheritance, top-level page `/Resources null`, indirect null `/Resources`, explicit empty dictionary override, generation-mismatched resource references, generation-exact parent lineage, escaped `/Type` page-tree names, nested private resource decoy exclusion, Form XObject omitted/null `/Resources` inheritance, resource-entry generation filtering, page `/Contents` non-inheritance, xref/object-stream repair, image/filter exclusion, or PageLabels work. The bounded behavior is only explicit page `/Resources` operands resolving to stream objects.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, page resource resolver, stream-object detector, text extractor, page-boundary metadata extractor, focused TestRunner, and WordPress smoke renderer. Full upstream PDFium/pdftext parity, scanned-PDF OCR, Surya/Texify/Torch model execution, and raster rendering remain intentionally out of scope under the no-GPU markerPDF directive.
