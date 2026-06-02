# AcroForm Seed Lock Actions Current Base, 2026-06-02

Micro-slice: `acroform-seed-lock-actions-currentbase`

Source truth:

- Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. `marker/pdf/extract_text.py::get_text_blocks()` delegates PDF text extraction to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` reads pypdfium text pages; `marker/convert.py::convert_single_pdf()` consumes those extracted pages before OCR/layout/table/model stages. Source: `https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py` and `https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py`.
- PDF AcroForm signature seed-value dictionaries (`/SV`), signature field locks (`/Lock`), and field/widget actions (`/A`, `/AA`, `/Next`) are interactive PDF object metadata. MarkerPDF import should expose these as review metadata without signing, validating signatures, submitting forms, resetting values, importing FDF data, changing widget visibility, executing JavaScript, or promoting action targets into visible WordPress text.
- The isolated worktree does not contain a local markerPDF upstream checkout; source inspection used the pinned upstream GitHub files plus the accepted markerPDF manifest and existing lane notes.

Implemented behavior:

- `PdfAcroFormExtractor` now adds `signature_seed_lock_action_review` to signature fields after signature state, signed-lock state, and action review rows are assembled.
- The review correlates signed state, `/SV` required constraints, timestamp and MDP policy, `/Lock` scope and permission label, field-level `/AA` actions, widget `/A` actions, chained `/Next` actions, action safety labels, and overlap between action target fields and locked fields.
- The review explicitly reports all import-time execution flags as false: seed constraints are not enforced during import, lock scope is not used to execute actions, form actions do not execute on import, and signing/signature validation stay disabled.
- The WordPress smoke covers a signed `approval.signature` field with `/SV` required filter/subfilter/reason/add-revision/digest constraints, `/Lock /Include` over `article.title`, field-level `SubmitForm` chained to `ImportData`, and widget-level `ResetForm` chained to `Hide`.

Focused evidence:

- Red-first check before implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSeedLockActionsCurrentBaseTest.php` failed with missing `signature_seed_lock_action_review`; output was `1 test files, 6 assertions, 1 failures`.
- Post-change direct check: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSeedLockActionsCurrentBaseTest.php` passed with `1 test files, 60 assertions, 0 failures`.
- Related family check: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAcroFormSignatureFieldActionStateCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSeedLockActionsCurrentBaseTest.php` passed with `5 test files, 959 assertions, 0 failures`.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-acroform-seed-lock-actions-currentbase.php` emitted `signature_field=approval.signature`, `signed=true`, seed constraints `filter, subfilter, reason, add_revision_info, digest_method`, `lock_action=Include`, locked field `article.title`, action types `SubmitForm, ImportData, ResetForm, Hide`, locked action field `article.title`, and all execution flags false.

Status delta:

- Focused behavior tests add 2 TestRunner PASS cases and 60 assertions.
- WordPress smoke coverage adds the seed-lock-action review import scenario.
- `phpPass`/WordPress scenario status is staged as `659 -> 661` for this isolated markerPDF lane patch.

Non-overlap:

This does not repeat accepted AcroForm seed-value parsing, signature lock dictionary parsing, signed-lock field-state application, signature field action-state summaries, XFA signature widget action review, security AcroForm permission-action preflight, nested `/Next` action walking, or visible-text extraction guards. The bounded behavior is the cross-reference payload that tells importers which seed constraints, locks, and form actions touch the same signature and locked fields.

Dependency closure:

No new support component is needed. This reuses the native PDF object scanner, dictionary/array parser, AcroForm field/widget traversal, signature metadata parser, seed-value parser, lock-state resolver, and action-chain review walker. Full CMS/PKCS#7 validation, signing, JavaScript execution, XFA execution, form submission/reset/import execution, pdftext/pypdfium execution, Python model execution, and external PDF tooling remain out of scope.
