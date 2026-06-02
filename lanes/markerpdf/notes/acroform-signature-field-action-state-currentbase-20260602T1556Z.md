# AcroForm Signature Field Action State Current Base, 2026-06-02

Micro-slice: `acroform-signature-field-action-state-currentbase-20260602T1556Z`

Source truth:

- Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. `marker/pdf/extract_text.py::get_text_blocks()` delegates page text extraction to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` reads pypdfium text pages; `marker/convert.py::convert_single_pdf()` consumes those extracted pages before OCR/layout/table/model stages. Source: `https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py` and `https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py`.
- PDF interactive form semantics keep signature field `/V` dictionaries, widget `/AS` appearance state, field/widget `/A` and `/AA` actions, and `/Lock` dictionaries as PDF object metadata. This native boundary must not sign, validate signatures, execute JavaScript/actions, launch files, import FDF data, or use appearance text as document content.
- The isolated worktree does not contain a local markerPDF upstream checkout; source inspection used the pinned upstream GitHub files plus the accepted lane manifest/notes.

Implemented behavior:

- `PdfAcroFormExtractor` now derives `signature_action_state` for `/FT /Sig` fields after calculation, signature, widget, and signed-lock metadata are assembled.
- The summary ties together signed state, `/SigFlags`, current signature dictionary source, field and widget action counts/types/triggers/safety labels, blocked unsafe action count, selected widget normal appearance objects, stale appearance count, and `/Lock` field scope.
- The summary explicitly reports `field_value_used_for_signature=false`, `appearance_value_used_for_signature=false`, `executes_action=false`, `executes_javascript=false`, `executes_signature_validation=false`, and `executes_signing=false`.
- The WordPress smoke covers a signed `approval.signature` field with unsafe URI, Named, Hide, Launch, and GoTo actions, selected `/AS /Signed` appearance object, and `/Lock /Include` applying to `article.title`.

Focused evidence:

- Red-first check before implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSignatureFieldActionStateCurrentBaseTest.php` failed with missing `signature_action_state`.
- Post-change focused check: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSignatureFieldActionStateCurrentBaseTest.php` passed with `1 test files, 38 assertions, 0 failures`.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-acroform-signature-field-action-state-currentbase.php` emitted `signature_field=approval.signature`, `signed=true`, `signature_object=30`, `action_count=5`, action types `URI, Named, Hide, Launch, GoTo`, `blocked_unsafe_action_count=1`, selected appearance object `50`, locked field `article.title`, and all execution flags false.
- `php -l` passed for changed PHP files.
- `git diff --check -- lanes/markerpdf` passed.

Status delta:

- Behavior tests move `532 -> 533`.
- Mapped markerPDF/PDF semantics move `379 -> 380 / 78`.

Non-overlap:

This does not repeat accepted AcroForm current/default value-state metadata, widget default `/AS` state comparison, widget appearance/value/action stream review, non-JavaScript field/widget action review, nested `/Next` action walking, calculation/signature `/CO` state, XFA signature state, signature seed-value `/SV`, `/Lock` dictionary parsing, FieldMDP/UR3 reference transforms, or security preflight. The bounded behavior is the cross-cutting state summary for a signed signature field that has both field-level and widget-level actions plus selected appearance and signed lock effects.

Dependency closure:

No new support component is needed. This reuses the native PDF object scanner, dictionary/array parser, AcroForm field/widget traversal, action-chain review walker, signature metadata parser, normal appearance review, and signed lock-state application. Full CMS/PKCS#7 validation, signing, XFA execution, JavaScript execution, pdftext/pypdfium execution, Python model execution, and external PDF tooling remain out of scope.
