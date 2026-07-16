# markerPDF public-key permission current-base

Micro-slice: `security-publickey-permission-currentbase`

Base accepted HEAD: `28240b72b0f77821c5ac2cf978b4d8bf8469270e`

## Source truth

- Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `pdftext.extraction.dictionary_output(...)` / PDFium-style extraction before conversion. Encrypted content must stay fail-closed until native decryption and permission authorization exist.
- PDF public-key security handlers store access permissions inside PKCS#7 recipient envelopes. For legacy `adbe.pkcs7.s3` and `adbe.pkcs7.s4`, active `/Recipients` are on the encryption dictionary; for `adbe.pkcs7.s5`, recipient lists move to selected crypt-filter dictionaries.

## Implemented behavior

- `PdfMetadataExtractor` now treats legacy public-key encryption dictionary `/Recipients` as selected permission envelopes for non-`adbe.pkcs7.s5` public-key handlers.
- Public-key review metadata now includes top-level recipient counts, selected-recipient sources, selected source policy, selected hashes, and selected byte totals.
- `PdfSecurityPreflight` picks up the corrected selected-recipient count through the existing permission preflight path.
- Encrypted text extraction remains blocked. Raw recipient envelopes, CMS parsing, decryption, permission enforcement, Python/model execution, and external PDF tools remain disabled.
- Added a WordPress smoke for legacy public-key recipient permissions.

## Non-overlap

This does not repeat the accepted public-key recipient-envelope inventory, selected-vs-unselected `adbe.pkcs7.s5` crypt-filter recipient review, public-key DSS review, Standard permission/digest/authentication review, malformed Standard permission reserved-bit review, encrypted metadata source priority, signature ByteRange/DSS/DocMDP review, AcroForm permission actions, or Launch/URI permission action review.

The new behavior is only the active selected-recipient permission boundary for legacy top-level public-key `/Recipients`.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php` failed before implementation with legacy selected recipient count `0`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php` passed with `1 test files, 44 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `3 test files, 1304 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-publickey-permission-currentbase.php` emitted `text_blocked=true`, `selected_recipient_count=2`, `top_level_recipients_selected=true`, `raw_recipient_material_exposed=false`, `executes_cms_parse=false`, `executes_decryption=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status delta

- Behavior tests move `659 -> 661` pass / `0` fail.
- Mapped semantics move `482 -> 483 / 78`.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, encryption dictionary parser, public-key recipient inventory, encrypted-text fail-closed gate, and security preflight report path.

Full CMS/PKCS#7 parsing, private-key matching, recipient permission decoding, Standard security-handler decryption, public-key decryption, permission enforcement, signature validation, revocation checks, signing, and trust-chain handling remain out of scope. Activating them requires a separate bounded native cryptography/decryption component with password fixtures, public-key recipient fixtures, decrypted stream/string fixtures, and signature-validation evidence.
