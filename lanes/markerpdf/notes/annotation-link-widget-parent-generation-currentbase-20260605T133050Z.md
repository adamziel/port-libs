# markerpdf annotation-link widget parent generation boundary

Slice: `markerpdf-annotations-links-boundary-current-base-20260605T133050Z`

Base accepted HEAD: `f142d7b9b18cd05cbd5f51482c8462a8ab4294f0`

## Source truth

- PDF Link annotations can be page annotations directly or AcroForm Widget annotations whose terminal field parent provides link-relevant `/A`, `/AA`, or `/Dest` entries.
- PDF indirect references include object number and generation. A widget `/Parent 20 1 R` must not inherit action dictionaries from a stale `20 0 obj` field dictionary even when that stale object appears later in file order.
- This is native searchable-PDF parser behavior only. No live OCR, Surya/Texify/Torch model path, external PDF renderer, browser action execution, or PDF action execution is used.

## Behavior implemented

- `PdfLinkAnnotationExtractor` now resolves widget parent chains by exact object and generation before inheriting `/A`, `/AA`, and `/Dest`.
- Existing object-only review metadata (`widget_field_parent_object`, `widget_field_chain`, and `widget_link_field_sources`) is preserved.
- New review metadata records the generation that supplied inherited widget link keys:
  - `widget_field_parent_generation`
  - `widget_field_chain_generations`
  - `widget_link_field_source_generations`
  - span aliases under `link_widget_*`

## Evidence

Red run before production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationWidgetParentGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses exact widget Parent generations before inheriting link actions for WordPress spans
Values are not identical
Expected: 'https://example.com/current-parent-generation-link'
Actual: 'https://example.com/stale-parent-generation-link'

1 test files, 5 assertions, 1 failures
```

Green focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationWidgetParentGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses exact widget Parent generations before inheriting link actions for WordPress spans

1 test files, 41 assertions, 0 failures
```

Adjacent focused family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationParentGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkPageGenerationBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS uses exact annotation object generations for page link and markup boundaries
PASS honors exact page-object generations for annotation P link boundaries
PASS keeps link annotation object generations exact before WordPress span promotion
PASS uses exact page Parent generations before link annotation CropBox boundaries
PASS resolves indirect widget link rectangles and flags at the current page annotation boundary
PASS inherits page widget link actions from terminal field dictionaries without promoting detached fields

6 test files, 186 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-widget-parent-generation-currentbase.php
```

The smoke emits `stale_parent_generation_excluded=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with Gutenberg text using only the current generation-one parent URI.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the existing native PDF object-generation table and link/action review parser in `lanes/markerpdf/src`.

## Non-overlap

This does not repeat the prior named-destination byte-limit slice, top-level `/A` array rejection, link annotation object generation tests, page `/P` generation tests, page parent CropBox generation tests, or widget link rectangle/flag inheritance tests. The new boundary is specifically widget field-parent action inheritance when the parent reference includes a nonzero generation and stale generation-zero parent/action objects are present.

## Next task

Continue no-GPU markerPDF work in non-overlapping native PDF parser areas: annotation/form generation boundaries not covered by widget parent action inheritance, stream-filter/xref recovery, metadata, image/filter metadata, page geometry, or supplied table/equation handoffs.
