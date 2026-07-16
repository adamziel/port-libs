# AcroForm Widget Appearance State Current Base

- Session: port-dev-markerpdf-form39pdf-20260602T1908Z
- Base accepted HEAD: 78dacbd21ee6b9a83b42fbcf69facc371244266b
- Slice: acroform-widget-appearance-state-currentbase

## Source Truth

- Upstream markerPDF keeps PDF extraction routed through the parser/text-extraction boundary in `marker/pdf/extract_text.py`; this lane keeps the port native PHP and does not execute markerPDF Python, pdftext, pypdfium, rendering engines, actions, or model workers.
- PDF AcroForm button widgets use `/AS` to select a widget appearance state from the normal appearance dictionary `/AP /N`; `/Off` represents the unselected widget state, and field `/V` remains the authoritative form value for imports.
- This slice treats a non-`/Off` widget `/AS` that is absent from `/AP /N` as stale review metadata rather than current checked state.

## Implementation

- `PdfAcroFormExtractor` now validates button widget `/AS` names against each widget `/AP /N` state dictionary before marking a widget checked.
- Stale non-`/Off` `/AS` names no longer synthesize a current value when field `/V` is absent, and they no longer make widget state look consistent with field `/V`.
- Field and widget review rows now expose `acroform_widget_appearance_state_currentbase` metadata including valid/on states, stale widgets, current source, and non-execution/non-rendering flags.
- WordPress smoke output records stale widget objects while keeping appearance-stream payload text out of visible content.

## Evidence

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` -> no syntax errors detected
- `php -l lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceStateCurrentBaseTest.php` -> no syntax errors detected
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-widget-appearance-state-currentbase.php` -> no syntax errors detected
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` -> valid JSON
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceStateCurrentBaseTest.php` -> 1 test files, 78 assertions, 0 failures
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-widget-appearance-state-currentbase.php` -> emitted stale widget objects 8 and 12 with all execution/render flags false
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroForm.*Test\\.php' | sort)` -> 12 test files, 1356 assertions, 0 failures
- `git diff --check -- lanes/markerpdf` -> passed

## Status Delta

- `phpPass`: 679 -> 680
- `wordpressScenarios`: 679 -> 680
- `mappedUpstreamSemantics`: 493 -> 494

## Non-Overlap

This does not repeat accepted AcroForm default-state fallback comparison, widget appearance/action cycles, widget characteristics, rich text/action/resource review, XFA widget review, calculation order, submit/reset review, or field hierarchy value review. It is limited to the current-base relationship between field `/V`, widget `/AS`, and widget `/AP /N` state dictionaries.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object parser, AcroForm field/widget traversal, and existing review metadata boundaries. No Python, pdftext, pypdfium, model, renderer, OCR, JavaScript, form action, signature validation, or external PDF tooling is executed.
