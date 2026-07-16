# AcroForm calculation/signature state boundaries, 2026-06-02

Micro-slice: `acroform-calculation-signature-state-boundaries-20260602T081217Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF extraction at the native `pdftext`/`pypdfium2` dependency boundary and does not execute AcroForm JavaScript, signing, or signature validation while converting documents.
- PDF AcroForm semantics define catalog `/AcroForm /CO` as the calculation order array, field/widget `/AA /C` as a calculate action, `/SigFlags` bit 1 as a signatures-present hint, `/SigFlags` bit 2 as append-only update intent, and signature field `/Lock` dictionaries as field-state locks applied when the signature field is signed.
- The upstream cache path was unavailable in this isolated worktree, matching prior markerPDF PDF slices; this handoff uses the accepted lane manifest/notes plus PDF AcroForm dictionary semantics already mapped by `PdfAcroFormExtractor`.

Implemented behavior:

- `PdfAcroFormExtractor::extractForm()` now exposes review-only `signature_flags` metadata for `/SigFlags`, including `signatures_exist`, `append_only`, and `executes_signature_validation=false`.
- Every field now carries `calculation_state` metadata that marks `/CO` participation, calculate-action presence, calculate script source hashes/filters, and `executes_javascript=false`.
- Signature fields now carry `signature_state` metadata that separates signature dictionaries from ordinary field values, records signed/append-only byte-range state, and keeps `executes_signing=false`.
- Signed signature `/Lock` dictionaries now derive per-field `signature_lock_state`, so calculated fields such as `invoice.total` can be marked locked by `approval.signature` without running calculations or validating the signature.

Non-overlap:

- This does not repeat accepted AcroForm `/CO` action inventory, current/default value-state extraction, widget `/AS` default comparison, DocMDP permissions, signature seed-value `/SV`, or `/Lock` dictionary field-scope parsing. It adds the cross-field state boundary that ties root `/SigFlags`, `/CO`, calculate actions, and signed lock application together.

Focused verification:

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-calculation-signature-state-import.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed with 1 test file, 359 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-calculation-signature-state-import.php` emitted signature flags `3`, calculation order `invoice.total`, `invoice.amount`, `approval.signature`, calculated field `invoice.total`, locked fields `invoice.amount` and `invoice.total`, signed append-only signature review metadata, and no calculation, JavaScript, signing, signature-validation, Python/model, or external PDF-tool execution.
- `php -r` JSON validation passed for `lanes/markerpdf/lane-status.json` and `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/markerpdf` passed.

Status delta:

- `phpPass`: `435 -> 436`.
- mapped focused markerPDF/PDF semantics: `288 -> 289 / 78`.

Dependency closure:

- No new support component is needed. This reuses the native PDF object/dictionary parser, stream decoder for JavaScript action streams, AcroForm field traversal, signature metadata parser, and review metadata paths. Full CMS/PKCS#7 signature validation and signing remain out of scope and would require a separate native cryptographic support component before activation.
