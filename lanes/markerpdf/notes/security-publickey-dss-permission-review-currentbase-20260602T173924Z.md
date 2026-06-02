# markerPDF public-key DSS permission review current-base

Micro-slice: `security-publickey-dss-permission-review-currentbase-20260602T173924Z`

Base: `252c505983bfd6b8ea68d7f5271483812ad199ee`

## Source truth

- Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native PDF text through `pdftext.dictionary_output()` and pypdfium2/PDFium helpers, so encrypted public-key content must stay blocked unless the PDF security handler can actually authorize/decrypt it.
- Adobe PDF Reference 1.7 section 3.5 says public-key security handlers use PKCS#7 recipient objects. For `adbe.pkcs7.s3` and `adbe.pkcs7.s4`, `/Recipients` lives in the encryption dictionary. For `adbe.pkcs7.s5`, recipient lists live in crypt filter dictionaries, and the recipient object includes access permissions. If a recipient appears in multiple lists, the first matching list determines permissions.
- PAdES/DSS source truth treats catalog `/DSS` validation material as certificates, OCSP responses, CRLs, and timestamp data for signature validation. This lane only inventories and hashes those bytes for review; it does not validate signatures or revocation.

Reference URLs:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.7old.pdf`
- `https://www.etsi.org/deliver/etsi_ts/102700_102799/10277804/01.01.02_60/ts_10277804v010102p.pdf`

## Implemented

- `PdfMetadataExtractor` now records which public-key crypt filters are actually selected by `/StmF`, `/StrF`, and `/EFF`.
- Selected recipient-bearing crypt filters are separated from unused recipient lists. Duplicate selected filters, such as the same default filter used for streams and strings, are counted once for recipient totals.
- `PdfSecurityPreflight` surfaces selected public-key recipient counts and selected/unselected crypt-filter names in the top-level encryption review, permission preflight, and permission-handler review.
- A combined fixture proves an encrypted `adbe.pkcs7.s5` PDF with selected document and embedded-file recipient filters, one unused recipient filter, and catalog `/DSS` validation streams stays review-only:
  - encrypted page text is not imported;
  - 3 total recipient envelopes are inventoried;
  - 2 recipient envelopes are selected by active `/StmF`/`StrF`/`EFF` filters;
  - the unused recipient filter remains visible as unselected review metadata;
  - DSS certificate and OCSP bytes are hashed only;
  - CMS parsing, decryption, permission enforcement, revocation checks, trust-chain validation, Python/model execution, and external PDF tools remain false.
- Added WordPress smoke `wordpress-pdf-publickey-dss-permission-review-currentbase.php` for the same boundary.

## Non-overlap

This does not repeat the accepted public-key recipient-envelope count/hash slice, direct DSS/VRI stream-hashing slice, indirect DSS filter operand slice, permission-handler reserved-bit slice, Standard permission digest/authentication slice, signature ByteRange/DSS/DocMDP correlation slice, encrypted metadata source priority slice, or trailer Encrypt/ID precedence slice.

The new behavior is specifically selected-versus-unselected public-key crypt-filter recipient permission review when DSS validation material coexists with encrypted public-key content.

## Verification

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` - passed.
- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` - passed.
- `php -l lanes/markerpdf/tests/PdfSecurityPreflightTest.php` - passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-publickey-dss-permission-review-currentbase.php` - passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` - passed, 1 test file / 475 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` - passed, 2 test files / 1073 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-publickey-dss-permission-review-currentbase.php` - emitted `text_blocked=true`, `permission_policy=public_key_recipient_permissions_blocked_without_private_key`, `recipient_count=3`, `selected_recipient_count=2`, selected filters `DefaultCryptFilter` and `EmbeddedFiles`, unselected filter `UnusedRights`, `dss_present=true`, `dss_validation_stream_count=2`, `raw_security_material_exposed=false`, and all CMS/decryption/revocation/trust-chain/Python/external-tool execution flags false.
- `git diff --check -- lanes/markerpdf` - passed.

## Dependency closure

No new support component is needed. The slice reuses the native PDF object parser, crypt-filter metadata parser, public-key recipient inventory, DSS stream summarizer, encrypted-text fail-closed gate, and security preflight report path.

Full CMS/PKCS#7 parsing, public-key private-key matching, recipient permission decoding, Standard security-handler password validation, decryption, permission enforcement, signature validation, revocation checks, and trust-chain handling remain out of scope. Activating them requires a separate bounded native cryptography/decryption component with password fixtures, public-key recipient fixtures, decrypted stream/string fixtures, and signature-validation evidence.
