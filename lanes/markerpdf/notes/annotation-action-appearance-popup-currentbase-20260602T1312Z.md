# markerPDF Annotation Action Appearance Popup Boundary

Session: `port-dev-markerpdf-annot11pdf-20260602T1312Z`
Micro-slice: `annotation-action-appearance-popup-currentbase-20260602T1312Z`
Base accepted HEAD: `f3a2623aa13660850917b15f153d4f6b7ceba6a6`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes visible PDF text through `marker/pdf/extract_text.py` and `pdftext.extraction.dictionary_output`; image rendering uses `marker/pdf/images.py::render_image()` with `draw_annots=False`.
- Source links inspected for this slice:
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- Relevant PDF action behavior: annotation `/A`, `/AA`, and `/Next` action dictionaries can carry `/Named`, `/Hide`, `/ImportData`, `/SubmitForm`, and `/ResetForm` entries. WordPress import must surface them as review metadata only; selected `/AP` appearance text can be imported by the native appearance boundary, while popup/action operand strings stay out of visible paragraphs.

## Implemented Behavior

- `PdfActionReviewExtractor` now emits review-only metadata for standard non-JavaScript annotation actions:
  `/Named`, `/Hide`, `/ImportData`, `/SubmitForm`, and `/ResetForm`.
- Chained `/Next` rows preserve action type, safety label, action object, chain metadata, file/target fields, field object/name targets, flags, submit format, and reset/include/exclude mode where applicable.
- The generic page annotation path keeps selected `/AP /N` appearance state and nested `/Popup` review metadata while excluding off-state appearances, duplicate reverse popups, popup strings, and action target strings from visible WordPress text.

## Evidence

Red-first focused failure after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
1 test files, 167 assertions, 1 failures
Expected safety labels named-action-review/import-data-action-review/submit-form-action-review; actual unsupported-action-review.
```

Passing focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
1 test files, 195 assertions, 0 failures
```

Adjacent action-review gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php
3 test files, 348 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-action-appearance-popup-boundary.php
```

The smoke emitted `primary_action_types=["Named","ImportData","SubmitForm"]`,
`primary_action_safety=["named-action-review","import-data-action-review","submit-form-action-review"]`,
`additional_action_types=["Hide","ResetForm"]`,
`additional_action_safety=["hide-action-review","reset-form-action-review"]`,
`selected_appearance_state="On"`, `selected_appearance_visible=true`,
`popup_text_excluded_from_visible_text=true`,
`action_targets_excluded_from_visible_text=true`, and all PDF action,
JavaScript, Python/model, and external-tool execution flags false.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-annotation-action-appearance-popup-boundary.php
git diff --check -- lanes/markerpdf
```

All passed. Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` / behavior tests move `505 -> 506`.
- Mapped markerPDF semantics move `353 -> 354 / 78`.

## Non-Overlap

This does not repeat accepted generic annotation `/A` URI/GoTo/Launch/JavaScript review, selected `/AP` state dictionary import, popup nesting, annotation border/color/geometry metadata, link/text-markup action review, rich-media media/action target review, or AcroForm-specific non-JavaScript form action review. The new behavior is the shared annotation action reviewer surface for standard non-JavaScript action dictionaries on current page annotations while preserving appearance and popup import boundaries.

## Dependency Closure

No new support component is needed. This reuses the native PDF object/value parser, action-chain walker, page annotation traversal, appearance summary path, text extractor appearance boundary, and WordPress smoke path. Full upstream markerPDF runner parity remains dependency-gated by `pdftext`, `pypdfium2`, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtimes, and external PDF tooling.
