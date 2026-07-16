# markerPDF AcroForm Widget Appearance Calculation-Order Review

Micro-slice: `acroform-widget-appearance-calcorder-review-currentbase-20260602T170725Z`

Base accepted HEAD: `49180e79432b8b918699ff28f84476d5fe362bc7`

## Source Truth

- Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF conversion through provider/converter/model pipelines and documents that forms are a hard PDF layout edge; the native lane keeps this boundary deterministic without invoking Python workers or model inference.
- Upstream `marker/pdf/images.py::render_image()` renders PDF pages with `draw_annots=False`, so widget annotation appearance rendering is not an import-side action execution primitive.
- PDF AcroForm `/CO` is an ordered calculation field reference list. This slice keeps `/CO`, widget `/AP /N` state dictionaries selected by `/AS`, and field/widget `/AA /C` JavaScript as review metadata. It does not calculate field values, execute JavaScript, render appearance streams, or replace field `/V`.

## Implemented Behavior

- `PdfAcroFormExtractor::extractForm()` now adds `calculation_order_review` alongside the existing stable `calculation_order` rows.
- Each review row classifies the `/CO` object as `field`, `widget`, `field_widget`, `non_field_object`, or `unresolved`.
- Widget `/CO` rows resolve their parent field object/name, selected `/AP /N` state, available appearance states, selected appearance object, decoded appearance hash, stale-state flag, and non-execution flags.
- Field-level `calculation_state` now exposes the matched target kind, field object, widget object, selected appearance state/object, and explicit `appearance_value_used_for_calculation=false`.
- Existing `calculation_order` output remains additive-compatible as `object` plus `field_name`.

## Non-Overlap

This does not repeat accepted AcroForm field flags/default appearance, current/default value state, field hierarchy value boundaries, calculation/format/keystroke/validate action review, signature lock/signature state, XFA signature widget review, selected widget appearance action boundary, cyclic `/Next` widget action review, non-JavaScript form action review, annotation widget review, or general JavaScript action-chain inspection. The new behavior is specifically the AcroForm `/CO` object review boundary when a calculation-order entry points at a widget annotation and when stale `/CO` objects are unresolved.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceCalcOrderReviewCurrentBaseTest.php` passed: `1 test files, 71 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceActionCycleCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureFieldActionStateCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceCalcOrderReviewCurrentBaseTest.php` passed: `4 test files, 895 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-widget-appearance-calcorder-currentbase.php` passed and emitted `calculation_order_target_kinds=["widget","field","unresolved"]`, `selected_appearance_object=30`, `unresolved_order_object=99`, field `/V` values, selected appearance hash, and all execution flags false.

## Dependency Closure

No new support component is needed. This reuses native PDF object parsing, AcroForm field/widget traversal, appearance stream review hashing, Flate stream decoding for JavaScript preview hashes, bounded action review, and WordPress smoke output. Full form calculation execution, appearance rendering, JavaScript execution, PDFium/PIL rendering, XFA JavaScript, and external PDF tooling remain out of scope unless a separate native PDF action/calculation/rendering component is explicitly activated with fixtures and safety gates.
