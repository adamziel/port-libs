# markerPDF AcroForm Choice/Rich Text Submit-Reset Review

Session: `port-dev-markerpdf-form30pdf-20260602T1653Z`
Micro-slice: `acroform-choice-richtext-reset-submit-review-currentbase-20260602T1653Z`
Base accepted HEAD: `16897955fedbe8eb586eccc43fee984b6415532f`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- The accepted manifest/notes record upstream reads for `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`: markerPDF imports PDF text at the pdftext/pypdfium extraction boundary and does not execute PDF form actions, JavaScript, external tools, or rich text rendering during conversion.
- PDF AcroForm semantics keep text field `/RV` rich text, choice field `/Opt` export/display pairs plus `/I` selected indexes, `/Ff` no-export fields, and `/SubmitForm`/`/ResetForm` field lists as form metadata. This native boundary resolves those states for review without submitting, resetting, rendering rich text, importing FDF, or executing actions.
- The isolated worktree has no local markerPDF upstream checkout under `.upstream-cache`; source-truth evidence is the pinned manifest plus accepted lane notes/source reads.

## Implemented

- `PdfAcroFormExtractor` now preserves `/RV` as `rich_text_review` metadata for rich-text text fields.
- The review records plain `/V` import value, `/RV` preview/hash/plain preview, byte count, source object, and false execution/import flags.
- SubmitForm and ResetForm action rows now include `field_value_review` blocks after field extraction resolves inherited values, widgets, flags, choice options, and defaults.
- SubmitForm review reports candidate/exported/excluded fields, no-export exclusions, push-button exclusions, selected choice option labels, and rich-text exclusion.
- ResetForm review reports affected fields, default/null reset values, choice default selections, cleared fields, and rich-text non-restoration.
- `wordpress-pdf-acroform-choice-richtext-submit-reset-currentbase.php` is the WordPress smoke for review metadata and visible-text leak prevention.

## Verification

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfAcroFormChoiceRichTextSubmitResetCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-choice-richtext-submit-reset-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormChoiceRichTextSubmitResetCurrentBaseTest.php` passed: `1 test files, 59 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceActionCycleCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormChoiceRichTextSubmitResetCurrentBaseTest.php` passed: `3 test files, 845 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-choice-richtext-submit-reset-currentbase.php` passed and emitted submitted fields `article.summary`, `article.topics`, and `article.empty`; excluded no-export field `internal.secret`; reset fields `article.summary`, `article.topics`, and `article.empty`; and all execution flags false.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Behavior tests move `577 -> 578`.
- Mapped markerPDF/PDF semantics move `414 -> 415 / 78`.
- New mapped inventory key: `mappedPdfAcroFormChoiceRichTextSubmitResetReviewBehaviors`.

## Non-Overlap

This does not repeat accepted AcroForm field flags/default appearance extraction, current/default value-state metadata, SubmitForm/ResetForm action dictionary parsing, field/widget JavaScript action review, non-JavaScript action review, nested `/Next` action walking, widget appearance/action-cycle metadata, XFA signature widget review, signature seed-value/lock dictionaries, or security preflight. The bounded behavior is the resolved field-value review for choice selections, rich-text `/RV`, no-export fields, push-button controls, and reset defaults on existing form action rows.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, field hierarchy resolver, choice option parser, field/widget action walker, value-state metadata, and text-extraction leak boundary. Full upstream markerPDF parity remains blocked by Python/pdftext/pypdfium/Surya/Texify/model execution, Streamlit/FastAPI runtime paths, and external PDF/OCR tooling.
