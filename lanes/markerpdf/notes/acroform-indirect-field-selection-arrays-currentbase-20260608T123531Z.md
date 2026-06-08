# markerPDF AcroForm Indirect Field-Selection Arrays Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T123531Z`
Session: `port-dev-markerpdf-acroform-fields-20260608T123531Z`
Base accepted HEAD: `1ab19e7fce393babcf85f95972afe8b0500b6e5a`

## Source Truth

- Manifest-pinned upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF structure through parser output before OCR/model handoff. Under the current no-GPU lane scope, AcroForm fields, calculation order, signature lock scope, and form actions are native parser/review metadata.
- PDF AcroForm array-valued entries may be direct arrays or indirect array objects. The existing native parser already applies this to `/AcroForm /Fields` and `/Kids`; this slice applies the same boundary to `/CO`, signature `/Lock /Fields`, SubmitForm/ResetForm `/Fields`, and Hide `/T`.

## Implemented Behavior

- `PdfAcroFormExtractor` now resolves direct or indirect array objects for AcroForm calculation order review.
- Signature field locks now resolve indirect `/Fields` array objects before computing signed lock state.
- SubmitForm/ResetForm `/Fields` and Hide `/T` field-target arrays now accept indirect array objects while preserving the old default-all behavior when `/Fields` is omitted or non-array.
- Nested arrays, nested dictionaries, comment-only object references, and empty scalar names stay excluded from field-object promotion and action target names.
- Added a WordPress smoke proving the reviewed field targets remain metadata only; form actions, JavaScript, signature validation/signing, OCR, models, and external PDF tools are not executed.

## Red First

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormIndirectFieldSelectionArraysCurrentBaseTest.php
=> FAIL resolves indirect AcroForm field selection arrays across calculation locks and actions
=> Expected calculation_order [8, 10, 12], Actual []
```

That showed `/CO 60 0 R` was ignored when object `60 0 obj` contained the calculation-order array.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormIndirectFieldSelectionArraysCurrentBaseTest.php
=> 1 test files, 65 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormIndirectFieldSelectionArraysCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceCalcOrderReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSeedLockActionsCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldActionSubmitResetResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormChoiceRichTextSubmitResetCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureFieldActionStateCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsActionGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSubmitResetAppearanceLockCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectActionFlagsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsActionDictionaryBoundaryCurrentBaseTest.php
=> 13 test files, 772 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-indirect-field-selection-arrays-currentbase.php
=> exits 0; calculation_order, lock_field_names, submit/reset/hide action field names resolve; action_payload_text_exposed=false; executes_python_or_models=false; executes_external_pdf_tools=false
```

Focused delta: +2 focused PASS cases, +65 focused assertions, and +1 WordPress smoke.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP object table, direct-or-indirect array resolver, generation-aware reference scanner, AcroForm field-name index, action review extractor, text extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, PDF action execution, JavaScript execution, signature validation/signing, decryption/password validation, Streamlit/FastAPI workers, and external PDF tools remain intentionally outside the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted `/AcroForm /Fields` indirect-array extraction, direct dictionary field materialization, object-stream field recovery, page widget parent repair, generation filtering for direct action field lists, direct calculation-order generation review, seed lock/action metadata, XFA signature widget review, widget appearance streams, default resources, rich text, choice-field top index, security permission preflight, annotations, outlines, xref repair, fonts, CMaps, image/filter metadata, supplied tables/equations, or pdftext dictionary sidecar behavior. The bounded behavior is only indirect array-object resolution for AcroForm field-selection arrays used by calculation order, signature locks, SubmitForm/ResetForm, and Hide action review.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around forms, annotations, fonts, CMaps, stream filters, xref repair, metadata, outlines, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
