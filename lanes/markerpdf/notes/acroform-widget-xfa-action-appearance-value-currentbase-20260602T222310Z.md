# markerPDF AcroForm Widget XFA Action Appearance Value Current Base

Micro-slice: `form-rebase-widget-xfa-action-appearance-value-currentbase`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native page text through `marker/pdf/extract_text.py::get_text_blocks()`, which delegates PDF parsing to `pdftext.extraction.dictionary_output(...)` before Marker block/page conversion.
- Upstream `marker/pdf/images.py::render_image()` renders preview images with annotations disabled (`draw_annots=False`), so form widget actions and XFA packets remain outside preview execution.
- The native PHP boundary follows the lane's accepted AcroForm convention: `/V` and `/DV` are current/default value source truth, widget `/AS` selects appearance review state, `/XFA` packet data is review metadata, and `/A`/`/AA` actions are never executed during WordPress import.

## Implementation

- `PdfAcroFormExtractor` now adds `widget_xfa_action_appearance_value_review` for XFA-referenced fields with widgets/actions.
- The review row combines:
  - AcroForm current/default value state from `/V` and `/DV`;
  - XFA packet/template/dataset match metadata and dynamic value previews;
  - widget `/AS`, selected `/AP /N` appearance object/hash, and stale-state summary;
  - field and widget action review rows, including `/Next` rows, safety labels, targets, field selections, and execution flags.
- The row keeps import policy explicit: XFA values do not replace current/default values, actions do not submit/reset/import/execute, and appearance streams are not used as field values.

## Verification

Red-first before implementation:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php
```

Failed on missing `widget_xfa_action_appearance_value_review` in the new focused test.

Post-change focused verification:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php
```

Passed: `1 test files, 809 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-acroform-widget-xfa-action-appearance-value-currentbase.php
```

Emitted `current_value="Static AcroForm summary"`, `xfa_dynamic_value_present=true`, `appearance_state="Ready"`, `action_types=["URI","Hide","SubmitForm","JavaScript"]`, `action_operands_excluded_from_visible_text=true`, `xfa_payload_excluded_from_visible_text=true`, and all execution flags false.

Changed PHP lint:

```sh
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-widget-xfa-action-appearance-value-currentbase.php
```

All reported `No syntax errors detected`.

Diff whitespace check:

```sh
git diff --check -- lanes/markerpdf
```

Passed with no output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted XFA packet metadata, XFA signature widget review, AcroForm current/default value-state extraction, widget appearance-state review, standalone widget appearance/action-cycle metadata, SubmitForm/ResetForm resource review, or signature seed/lock action review. The bounded new behavior is the combined current-base review row for ordinary XFA-backed widgets where current value, selected appearance, field/widget actions, and XFA dynamic data all coexist.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, AcroForm field/widget traversal, XFA packet parser, action-chain reviewer, stream-filter decoder, selected appearance review path, and WordPress smoke path. Full upstream runner parity remains gated by Python/model/pdftext/pypdfium/Surya/Texify and external PDF tooling availability.
