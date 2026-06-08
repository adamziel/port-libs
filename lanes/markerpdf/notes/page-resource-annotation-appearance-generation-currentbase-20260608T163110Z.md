# Page Resource Annotation Appearance Generation Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260608T163110Z`

Session: `port-dev-markerpdf-resource-inherit-20260608T163110Z`

Base accepted HEAD: `f7bb0ce56c95f19eaed5b64a386c252d4eb5269a`

## Source Truth

PDF indirect references include both object number and generation. Annotation appearance streams can omit their own `/Resources`, in which case markerPDF inherits the invoking page resource context for text extraction. That inherited context must only be used after the `/AP` dictionary reference and selected normal appearance `/N` reference resolve to the exact requested generation.

This slice keeps valid nonzero-generation annotation appearances importable while rejecting stale same-object-number appearance streams or appearance dictionaries before page resources are inherited into the appearance text path.

## Red-First Evidence

Before the source change, this focused probe rendered a stale appearance stream through inherited page resources even though the annotation referenced `30 1 R` and only `30 0 obj` existed:

```php
array (
  0 => 'stale appearance resource leak',
)
```

After the fix, the same probe returns an empty line set for the stale appearance, while a valid `30 1 obj` appearance still imports current appearance text.

## Patch

`PdfTextExtractor` now resolves annotation appearance `/AP` dictionaries and selected `/N` appearance streams with exact generation checks before decoding the Form XObject and inheriting page resources. The decoded appearance path carries the resolved stream body with the object number, so valid nonzero-generation appearances remain usable without falling back to a stale generation-zero object.

Focused coverage:

- `lanes/markerpdf/tests/PdfPageResourceAnnotationAppearanceGenerationCurrentBaseTest.php` rejects stale `/N 30 1 R` references before inherited page resources can render the stale appearance.
- The same test accepts a valid nonzero-generation `30 1 obj` appearance and confirms stale `30 0 obj` text is excluded.
- The same test rejects a stale indirect `/AP 40 1 R` dictionary before `/N` lookup can reach a generation-zero decoy stream.
- `lanes/markerpdf/examples/wordpress-pdf-page-resource-annotation-appearance-generation-currentbase.php` emits WordPress paragraphs for page text and the current-generation appearance only, with review flags for stale generation rejection and no model/tool execution.

## Verification

Focused direct test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceAnnotationAppearanceGenerationCurrentBaseTest.php
```

Result: `1 test files, 23 assertions, 0 failures`.

Adjacent annotation appearance/resource family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceAnnotationAppearanceGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceAnnotationAppearanceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceAnnotationAppearancePropertiesCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetActionResourceAppearanceCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSubmitResetAppearanceLockCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormXfaAppearanceCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceCharacteristicsCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceActionCycleCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceExportCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceCalcOrderReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceStateCurrentBaseTest.php
```

Result: `11 test files, 601 assertions, 0 failures`.

Page-resource current-base family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php
```

Result: `50 test files, 1130 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-page-resource-annotation-appearance-generation-currentbase.php
```

Result: exits 0 with `valid_nonzero_generation_appearance_imported=true`, `stale_same_number_generation_rejected=true`, `stale_missing_generation_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page resource inheritance, null and indirect-null resources, direct resource dictionary tails, category/entry tail boundaries, resource wrapper resolution, annotation appearance inheritance, annotation appearance Properties scoping, Form XObject malformed resources, image XObject inherited resource provenance, xref repair, OCR/model work, or external PDF tool behavior. The bounded behavior is generation-exact annotation appearance `/AP` and `/N` resolution before inherited page resource use.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP object scanner, exact-generation object body maps, page-tree resource resolver, annotation appearance decoder, content tokenizer, page boundary metadata extractor, and WordPress smoke path. GPU/model OCR, Surya/Texify/Torch, raster rendering, multiprocessing workers, and live external services remain intentionally out of scope.
