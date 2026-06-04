# markerPDF AcroForm fields indirect widget operands current base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260604T234541Z`
Base accepted HEAD: `57058b982e38efb74137da09319fa7203abc89a4`

## Source Truth

- Upstream Marker converts PDFs to Markdown/JSON and explicitly treats forms as document structure, while the current lane remains no-GPU/no-model and native-PDF focused: https://github.com/datalab-to/marker
- PDF widget annotations are annotation dictionaries, so their `/Rect` geometry and `/F` annotation flags may be indirect PDF operands. The native field review must resolve those operands before WordPress overlay metadata, without executing actions or rendering appearances.

## Implemented Behavior

- `PdfAcroFormExtractor` now resolves indirect numeric operands inside AcroForm widget `/Rect` arrays before normalizing field-review rectangles.
- Widget `/F` annotation flags now resolve through indirect integer objects before hidden/no-view/printable metadata is computed.
- The focused WordPress smoke now includes an `indirect.geometry` field and reports `indirect_widget_rect_resolved=true`, `indirect_widget_flags_resolved=true`, and `indirect_widget_visibility=visible`.
- Field values, detached widgets, appearance streams, JavaScript, Python/model workers, and external PDF tools remain excluded from visible import execution.

## Evidence

Red-first focused run after adding the boundary case:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
1 test files, 104 assertions, 1 failures
Failure: widget /Rect parsed as [30, 0, 31, 0] from object-reference tokens.
```

Focused run after patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
1 test files, 133 assertions, 0 failures
```

Focused AcroForm family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroForm.*Test\.php$' | sort)
24 test files, 2308 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php
```

Emitted `field_count=4`, `indirect_widget_rect_resolved=true`, `indirect_widget_flags_resolved=true`, `indirect_widget_visibility=visible`, and all execution flags false.

Syntax and hygiene:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php
jq empty lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

All passed.

## Status Delta

- `phpPass` moves `1125 -> 1126`.
- `wordpressScenarios` moves `1118 -> 1119`.
- Added 1 focused AcroForm PASS case and expanded the existing WordPress AcroForm fields smoke.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page-widget link promotion, indirect widget `/Rect` and `/F` handling in `PdfLinkAnnotationExtractor`, direct AcroForm field discovery, indirect `/Fields` and `/Kids` arrays, generation-exact field references, widget appearance/action review, XFA/signature review, or link/markup annotation geometry. The new behavior is specifically AcroForm field-review metadata resolving indirect widget geometry and visibility operands.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-checked indirect-reference resolver, array/value tokenizer, AcroForm field/widget review path, and existing WordPress smoke harness. Full upstream markerPDF parity for live OCR, PDFium rendering, Surya/Texify/Torch models, Streamlit/FastAPI workers, and exact model benchmarks remains intentionally out of scope under the no-GPU markerPDF directive.
