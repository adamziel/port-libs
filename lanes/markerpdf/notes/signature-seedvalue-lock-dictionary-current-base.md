# markerpdf signature seed value and lock dictionary current base

Micro-slice: `markerpdf-signature-seedvalue-lock-dictionary-current-base-20260602T0645Z`

Source truth:

- Upstream markerPDF source remains the PDF-to-structured-content boundary from `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; upstream has no focused Python test for AcroForm signature seed dictionaries, so this slice follows the lane's native AcroForm parser pattern.
- PDF Reference 1.7 Table 8.83 describes signature field seed value dictionary entries including `Filter`, `SubFilter`, `DigestMethod`, real-valued `V`, `Reasons`, `MDP`, and `TimeStamp`; the `Ff` entry marks required constraints.
- Apache PDFBox `PDSignatureField::getSeedValue()` exposes `/SV` as the signature field seed dictionary, and `PDSeedValue` maps required flags for filter, subfilter, parser version, reason, legal attestation, add revision info, and digest method.
- HexaPDF's `SignatureField::LockDictionary` documents `/Type /SigFieldLock`, `/Action` values `All`, `Include`, and `Exclude`, optional `Fields`, and optional permission level `P`.

Implemented behavior:

- `PdfAcroFormExtractor` now attaches review-only `signature_seed_value` metadata to `/FT /Sig` fields with `/SV` dictionaries, including indirect dictionaries, required-constraint flags, filter/subfilter/digest/reason/legal attestation lists, parser-version requirements, MDP permission intent, timestamp URL/required state, and explicit `executes_signing=false`.
- `PdfAcroFormExtractor` now attaches review-only `signature_lock` metadata from `/Lock` dictionaries, including action validity, included/excluded field names, permission labels, and `executes_action=false`.
- The WordPress smoke emits signature seed/lock review metadata for import workflows without signing, executing PDF actions, Python/model calls, or external PDF tooling.

Non-overlap:

- This slice does not repeat the accepted DocMDP catalog `/Perms` signature permission extraction, AcroForm calculation/action JavaScript review metadata, SubmitForm/ResetForm review metadata, or AcroForm current/default value-state metadata.

Focused verification:

- Red-first check before implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` failed on missing `signature_seed_value` and `signature_lock` metadata in the new test.
- Post-change focused verification passed: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` reported `1 test files, 256 assertions, 0 failures`.
- Changed PHP lint passed for `lanes/markerpdf/src/PdfAcroFormExtractor.php`, `lanes/markerpdf/tests/PdfAcroFormExtractorTest.php`, and `lanes/markerpdf/examples/wordpress-pdf-signature-seedvalue-lock-import.php`.
- The new example smoke `php lanes/markerpdf/examples/wordpress-pdf-signature-seedvalue-lock-import.php` emitted required seed constraints, `SHA256`/`SHA512` digest metadata, timestamp-required metadata, included locked fields `registration.email` and `invoice.total`, `executes_signing=false`, `executes_action=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PDF object/dictionary parser in `PdfAcroFormExtractor`; full cryptographic signature validation and signing remain out of scope and would require a separate native CMS/PKCS#7/X.509 support component before activation.
