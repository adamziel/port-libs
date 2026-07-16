# markerPDF xref Prev-chain DSS indirect Prev helper current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T224208Z`
Base: `fb68aedd3080f5c5d86cf57108d39e4c2a7b6359`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF and security metadata extraction through parser-backed PDF object loading before conversion. Under the no-GPU markerPDF scope, the PHP lane owns native xref `/Prev` traversal and review-only security metadata extraction without running OCR, models, Python workers, PDFium, or external PDF tools.

PDF incremental updates may store the previous xref offset behind a direct numeric helper object. That helper must be selected generation-exactly from objects before the latest xref stream; later same-number post-xref objects are not allowed to redirect the `/Prev` chain. For WordPress import, catalog `/DSS` validation streams are review metadata only: hashes and counts are exposed, raw certificate, OCSP, CRL, timestamp, and signature payload bytes are not.

## Behavior

`PdfDocumentSecurityStoreExtractor` now resolves xref section `/Prev` values through a generation-exact direct helper object that appears before the current xref section. The resolver supports direct integers, one-item arrays, and bounded helper chains, and it ignores post-xref same-number decoys by choosing the latest matching helper definition before the current xref offset.

The focused fixture appends a current catalog and signature field, publishes a latest xref stream with `/Prev 40 0 R`, stores `40 0 obj` as the base xref offset before that stream, then appends decoy `40 0` and decoy `/DSS` objects after `%%EOF`. Native DSS extraction now inherits the previous validation streams, correlates the VRI key with the current signature contents digest for review, and excludes all raw validation bytes and post-xref decoys.

## Evidence

Red baseline after adding the focused case:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainDssIndirectPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect xref-stream Prev helper before DSS signature review
Expected: true
Actual: false
1 test files, 5 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainDssIndirectPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect xref-stream Prev helper before DSS signature review
1 test files, 33 assertions, 0 failures
```

DSS/security adjacent gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainDssIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainDssCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssXrefPrevRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssSignatureCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 179 assertions, 0 failures
```

Incremental xref regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 612 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dss-indirect-prev-currentbase.php
dss_indirect_prev_helper_selected=true
dss_signature_vri_matched=true
post_xref_prev_helper_decoy_excluded=true
raw_validation_payload_omitted=true
visible_text_selected=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted text/metadata/EmbeddedFiles damaged-offset repair, stale explicit-offset repair, wrong-current-offset repair, classic-table indirect `/Prev` repair, DSS damaged direct `/Prev` repair, DSS omitted-graph repair, duplicate `/Prev` handling, forward `/Prev` handling, or object-stream carrier repair.

The bounded behavior here is specifically `PdfDocumentSecurityStoreExtractor` resolving a latest xref-stream `/Prev` value through a generation-exact direct numeric helper before DSS signature-review extraction while excluding later same-number helper and DSS decoys.

## Dependency Closure

No new support component is needed. This reuses native PHP direct-object scanning, xref stream decoding, `/Prev` chain traversal, DSS validation-stream summarization, and WordPress security-review smoke output. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
