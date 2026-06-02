# markerPDF AcroForm Widget Appearance Action-Cycle Review

Session: `port-dev-markerpdf-form28pdf-20260602T1630Z`
Micro-slice: `acroform-widget-appearance-action-cycle-review-currentbase-20260602T1630Z`
Base accepted HEAD: `ce4d02651156db0ca80cec00a035bd5f5795584e`

## Source Truth

- Upstream `sddai/markerPDF` is pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates PDF text extraction to `pdftext.extraction.dictionary_output(...)`; `naive_get_text()` delegates pypdfium text-page extraction. AcroForm action execution is outside that import boundary.
- `marker/pdf/images.py::render_image()` renders page previews through PDFium with `draw_annots=False`, so form widget appearance rendering is not an action-execution primitive for import.
- PDF action dictionaries may carry `/Next` as a single action or an array. Cyclic widget `/A` chains must be bounded and surfaced as review metadata without executing URI, JavaScript, Launch, or Hide actions.

Upstream references:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`

## Implemented

- `PdfAcroFormExtractor` now attaches an `action_review` block to every AcroForm field and widget action source.
- The review block records `action_count`, `chained_action_count`, max depth, blocked cycle edges, blocked max-depth edges, and blocked action object numbers.
- Existing action rows remain stable: URI, JavaScript, Hide, Launch, SubmitForm, ResetForm, and other action metadata still appear as review-only rows without added synthetic action rows.
- The focused widget fixture selects `/AP /N /Ready` through `/AS /Ready`, preserves field `/V` as the imported value, walks `/A 20 0 R` through nested `/Next` arrays, and reports two blocked cyclic edges (`23 -> 21` and `22 -> 20`) without executing actions.
- WordPress smoke emits review metadata proving `cycle_edges_blocked=2`, selected appearance object `30`, action types `URI`, `JavaScript`, `Hide`, and `Launch`, field value `Final widget field value`, and all execution flags false.

## Verification

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceActionCycleCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-widget-appearance-action-cycle-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceActionCycleCurrentBaseTest.php` passed: `1 test files, 46 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed: `1 test files, 740 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-widget-appearance-action-cycle-currentbase.php` passed and emitted `cycle_edges_blocked=2`, `blocked_cycle_action_objects=[21,20]`, `selected_appearance_object=30`, `action_payloads_excluded_from_visible_text=true`, and all execution flags false.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- `lane-status.json` behavior tests: `558 -> 559`.
- `UPSTREAM_TEST_MANIFEST.json` mapped semantics: `399 -> 400 / 78`.
- New mapped inventory key: `mappedPdfAcroFormWidgetActionCycleReviewBehaviors`.

## Non-Overlap

This does not repeat accepted AcroForm selected widget appearance stream review, current/default value-state metadata, SubmitForm/ResetForm action parsing, non-JavaScript field action parsing, JavaScript payload hashing, nested `/Next` row traversal, signature action-state summaries, XFA signature widget review, catalog OpenAction `/Next` review, document-level JavaScript action-chain safety, annotation widget review, or rich-media action boundaries. This slice is limited to explicit field/widget `action_review` cycle/depth safety metadata for AcroForm action sources.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, dictionary/array parser, AcroForm field/widget traversal, action-chain walker, normal appearance review, text extractor boundary, and WordPress smoke harness. Full upstream markerPDF parity remains gated by Python, pdftext, pypdfium, Surya/Texify/model execution, Streamlit/FastAPI runtime, and external PDF tooling.
