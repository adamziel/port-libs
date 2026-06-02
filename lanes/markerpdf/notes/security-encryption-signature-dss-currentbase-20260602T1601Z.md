# markerPDF security encryption signature DSS current-base

Micro-slice: `security-encryption-signature-dss-currentbase-20260602T1601Z`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes ordinary PDF conversion through pypdfium/pdftext-style extraction and does not perform CMS/PKCS#7 signature validation, revocation checks, trust-chain validation, decryption, signing, or action execution during import.
- The lane already maps the catalog `/DSS` long-term-validation boundary as review metadata. This slice extends that same boundary to filtered DSS validation streams because PAdES/PDF DSS stores certificate, OCSP, CRL, and timestamp-token bytes in PDF streams whose `/Filter` operands may be direct or indirect.
- Existing markerPDF native stream-filter behavior supports indirect filter-name arrays for visible text extraction. This slice applies the same PDF parser expectation to DSS validation streams while preserving the stricter review-only security surface.

## Red-first evidence

- Before implementation, an in-memory DSS fixture with `70 0 obj << /Filter 80 0 R ... >> stream ... endstream` and `80 0 obj /FlateDecode endobj` reported `filters=[]`, `length=38`, and `hash_matches_decoded=false` for a 30-byte decoded certificate payload.

## Implementation

- `PdfDocumentSecurityStoreExtractor` now resolves `/Filter` operands through direct names, indirect names, direct arrays, indirect arrays, and `null` array entries before decoding supported DSS validation streams.
- Catalog `/DSS` global `/Certs`, `/OCSPs`, `/CRLs`, VRI `/Cert` `/OCSP` `/CRL`, and `/TS` timestamp-token summaries now hash decoded validation bytes when the filter chain is declared indirectly.
- Raw certificate, OCSP, CRL, timestamp-token, and signature contents remain suppressed. The preflight still does not decrypt, validate signatures, check revocation, build trust chains, sign, execute PDF actions, run Python/models, or call external PDF tools.

## Non-overlap

This does not repeat accepted Standard encryption permission metadata, encrypted metadata source priority, signature ByteRange preflight, DocMDP/FieldMDP/UR3 reference transforms, AcroForm seed-value `/SV`, field `/Lock`, signed lock-state review, generic visible-text filter arrays, stream DecodeParms fail-closed behavior, or the prior direct DSS Cert/OCSP/CRL/VRI review slice. The new behavior is specifically indirect `/Filter` operand resolution for DSS validation streams before security-review hashing.

## Verification

- `php -l lanes/markerpdf/src/PdfDocumentSecurityStoreExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-signature-dss-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `1 test files, 208 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed with `3 test files, 1160 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-signature-dss-currentbase.php` passed and emitted `indirect_filter_decoded=true`, validation filters `[FlateDecode]`, `[ASCIIHexDecode]`, `[ASCIIHexDecode,FlateDecode]`, and `[ASCIIHexDecode]`, `dss_present=true`, `validation_stream_count=4`, `review_required_signature_metadata`, `raw_validation_bytes_exposed=false`, `executes_signature_validation=false`, `executes_revocation_check=false`, `executes_trust_chain_validation=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -r '$files=["lanes/markerpdf/lane-status.json","lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'` passed for both JSON files.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency closure

No new support component is needed. This reuses the native PDF object, dictionary, array, stream-filter, and security-preflight parser paths already in the markerPDF lane. Full CMS/PKCS#7 parsing, X.509 validation, OCSP/CRL validation, timestamp-token validation, trust-store integration, decryption, and signing remain out of scope and would require a separate native cryptography/validation component with signed PDF fixtures before activation.
