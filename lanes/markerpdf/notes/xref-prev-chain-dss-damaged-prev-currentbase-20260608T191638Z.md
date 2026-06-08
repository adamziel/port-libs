# markerPDF xref Prev chain DSS damaged Prev current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T191638Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260608T191638Z`
Base accepted HEAD: `ac0b2b26074ec6e75d171aa7e3eb5bbc4ca926f1`

## Source Truth

Upstream markerPDF delegates searchable-PDF and security-review object loading to native PDF parser behavior before conversion. In this no-GPU PHP lane, xref `/Prev` traversal and catalog `/DSS` review are owned locally. Incremental PDFs may store catalog DSS validation material in a previous xref section while the latest xref stream points to that section with a damaged `/Prev` offset. The security preflight path must repair that bounded pointer to a valid previous xref table or xref stream before deciding whether DSS validation streams are present.

## Behavior

`PdfDocumentSecurityStoreExtractor` now normalizes classic-table and xref-stream `/Prev` operands before merging previous rows. If the declared `/Prev` points forward, into a damaged section, or into a nearby xref keyword, the extractor falls back to the latest valid prior xref table or xref stream. The repair mirrors the existing xref-chain policy used by the text, metadata, embedded-file, and attachment paths, but is scoped to DSS security-store review.

The focused fixture keeps `/DSS`, `/VRI`, certificate, and OCSP stream rows only in the previous classic xref table. The current xref stream owns the catalog, signature field, and signature object, but its `/Prev` points into the middle of the previous `xref` keyword. Before the fix, the DSS extractor returned `present=false`; after the fix, WordPress security preflight reports the review-only DSS validation streams, matches the VRI key to the signature contents SHA-1, and keeps all raw signature/certificate/OCSP bytes out of output.

## Evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssXrefPrevRepairCurrentBaseTest.php
```

Result: `1 test files, 2 assertions, 1 failures`; `PdfDocumentSecurityStoreExtractor` returned `present=false` for the repaired DSS fixture.

Focused after fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssXrefPrevRepairCurrentBaseTest.php
```

Result: `1 test files, 34 assertions, 0 failures`.

Adjacent DSS/security regression:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssXrefPrevRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssCertActionPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssSignatureCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssSignatureReferenceTransformCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `6 test files, 818 assertions, 0 failures`.

Xref-prev regression:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssXrefPrevRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
```

Result: `2 test files, 646 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-security-dss-damaged-prev-currentbase.php
```

Result: exits `0`; smoke status reports `dss_present=true`, `dss_validation_stream_count=2`, `dss_vri_match_status=matched_signature_contents_sha1`, review-only signature/revocation flags, `raw_signature_bytes_exposed=false`, `raw_validation_bytes_exposed=false`, and `executes_external_pdf_tools=false`.

Syntax:

```bash
php -l lanes/markerpdf/src/PdfDocumentSecurityStoreExtractor.php
php -l lanes/markerpdf/tests/PdfSecurityDssXrefPrevRepairCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-security-dss-damaged-prev-currentbase.php
```

Result: no syntax errors.

## Non-Overlap

This does not repeat accepted text/metadata/attachment damaged `/Prev` repair, classic table damaged `/Prev` repair for content import, duplicate `/Prev` xref-stream handling, direct or compressed `/Prev` helper repair, inherited sparse trailer `/Root`/`/Info`, xref-stream `/W` and `/Index` operand repair, free-object annotation suppression, object-stream carrier recovery, encrypted permission operand review, or live OCR/model/PDFium execution. The bounded behavior is only catalog DSS security-store row selection when `PdfDocumentSecurityStoreExtractor` must repair a damaged `/Prev` before security preflight.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref table parser, xref stream decoder, DSS validation-material summarizer, signature preflight, and WordPress block smoke renderer. Full upstream parity for pdftext/PDFium rendering, Surya/Torch OCR/layout/table models, Texify, Streamlit/FastAPI workers, benchmark model downloads, and external PDF tools remains intentionally out of scope for this no-GPU markerPDF slice.
