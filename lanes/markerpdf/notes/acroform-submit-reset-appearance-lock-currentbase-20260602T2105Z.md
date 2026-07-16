# AcroForm Submit/Reset Appearance Lock Current Base

## Source Truth

- Upstream markerPDF pinned in `UPSTREAM_TEST_MANIFEST.json` uses `marker/pdf/extract_text.py::get_text_blocks()` to route page text through `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` through pypdfium page text extraction. The import boundary is extracted page text plus review metadata, not PDF viewer action execution.
- Relevant PDF parser behavior for this slice: AcroForm `/SubmitForm` and `/ResetForm` action dictionaries select fields with `/Fields` and `/Flags`; widget annotations select normal appearances through `/AS` and `/AP /N`; signature fields can declare `/Lock` dictionaries that apply after a signature is present. The native port must surface those relationships without submitting data, resetting values, enforcing locks, executing JavaScript/actions, or rendering appearance streams.

## Implemented Behavior

- Added `submit_reset_appearance_lock_review` on AcroForm fields that carry SubmitForm or ResetForm actions.
- The review correlates:
  - field and widget action counts/triggers/objects;
  - selected, submitted, and reset field names;
  - target current/default value state;
  - selected widget normal appearance objects and stale appearance counts;
  - signed signature-lock coverage over target fields.
- All import-time execution flags remain false: no action execution, JavaScript, form submission, reset mutation, signature validation, appearance stream execution, rendering, Python/model work, or external PDF tools.

## Non-Overlap

This does not repeat existing AcroForm rich-text submit/reset resource review, seed-value lock action review, widget appearance action-cycle review, field action FileSpec review, XFA widget review, or signature widget review. The new behavior is the combined current-base review across submit/reset action selections, selected widget appearance state, and signed field-lock state.

## Focused Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSubmitResetAppearanceLockCurrentBaseTest.php`
  - `1 test files, 66 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php`
  - `18 test files, 1784 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-submit-reset-appearance-lock-currentbase.php`
  - Emitted WordPress review output with `locked_target_field_names: ["article.consent"]`, `selected_appearance_objects: [50]`, and all execution flags false.
- `php -l` passed for changed PHP source/test/example files.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency Closure

No new support component is needed. This reuses the native `PdfAcroFormExtractor`, action review parsing, value-state extraction, widget appearance metadata, and signature-lock review already present in the lane. Full live upstream parity remains blocked on the Python/PDF/model stack (`pdftext`, `pypdfium2`, Surya/Torch, Streamlit/FastAPI runtime, OCR/raster tooling, and external PDF tools), but this slice is covered by native PHP fixtures and tests.
