# AcroForm Rich Text XFA Action State Current-Base

Session: `port-dev-markerpdf-form40pdf-20260602T1922Z`
Micro-slice: `acroform-richtext-xfa-action-state-currentbase`
Base accepted HEAD: `2f7ab5c6c7fa7a5a593e92a06a3c2a9a2e3a8f58`

## Source Truth

- Upstream markerPDF remains pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/pdf/extract_text.py` routes PDF text through `pdftext.extraction.dictionary_output(...)` and pypdfium text pages; it does not execute PDF actions, XFA JavaScript, form submission/reset, or rich-text rendering as part of native text extraction.
- PDF form behavior source truth for this slice: AcroForm `/V` and `/DV` are the static current/default field state; `/RV` and `/DS` are rich-text/default-style metadata; `/XFA` can carry dynamic dataset values; `/A` and `/AA` carry action dictionaries. The PHP lane keeps these active or dynamic payloads review-only for WordPress import.

## Behavior

- `PdfAcroFormExtractor` now adds `rich_text_xfa_action_state_review` for rich-text fields that have matching XFA metadata or field/widget actions.
- The review correlates static `/V` and `/DV`, rich `/RV` and `/DS`, matched XFA template/data paths, field/widget action types/triggers/safety labels, SubmitForm/ResetForm field names, and selected widget appearance state.
- Static AcroForm current/default values remain authoritative. Rich HTML, XFA dataset values, action targets/scripts, and appearance stream payloads are not imported as field values or visible WordPress text.

## Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormRichTextXfaActionStateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS summarizes rich text XFA and AcroForm action state at current base without importing active payloads
1 test files, 71 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormRichTextXfaActionStateCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormChoiceRichTextSubmitResetCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormXfaWidgetCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetRichTextActionResourceCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 286 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-richtext-xfa-action-state-currentbase.php
passed; emitted WordPress review metadata showing AcroForm /V current value, /DV default value, rich-text hash, XFA data path preview, JavaScript/SubmitForm/ResetForm action review, selected widget appearance object, and all execution/import flags false.
```

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormRichTextXfaActionStateCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-richtext-xfa-action-state-currentbase.php
all reported no syntax errors
```

```text
git diff --check -- lanes/markerpdf
passed
```

## Status Delta

- Behavior tests move `705 -> 706`.
- WordPress scenarios move `705 -> 706`.
- Mapped markerPDF/PDF semantics move `508 -> 509 / 78` pending integration.
- New mapped behavior label: `mappedPdfAcroFormRichTextXfaActionStateCurrentBaseReviewBehaviors`.

## Non-Overlap

This does not repeat accepted XFA widget current-base review, choice/rich-text SubmitForm/ResetForm field-value review, signature field action-state review, widget rich-text default-style/resource action review, AcroForm non-JavaScript action parsing, XFA signature widget review, or widget normal/rollover/down appearance reviews. The bounded behavior is the synthesized current-base review row for an ordinary rich-text AcroForm field that also has XFA matches and field/widget actions.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, AcroForm field traversal, rich-text review parser, XFA packet/data-path review, action-chain walker, widget appearance-state review, stream decoder, and native text extraction leak boundary. Full XFA layout/data binding, XFA JavaScript execution, rich-text HTML rendering, form submission/reset execution, appearance rendering, pypdfium/pdftext execution, Python models, and external PDF tools remain out of scope.
