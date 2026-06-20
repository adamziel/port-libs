# markerPDF encrypted object/xref combinations current-base

Micro-slice: `plib-tuzwg.11`
Base accepted HEAD: `d729d9dc505bd51d2503d850996434143a339e6b`

## Source Truth

Upstream markerPDF keeps low-level PDF page text extraction behind `marker/pdf/extract_text.py`: `get_text_blocks()` delegates structured extraction to `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` delegates page text extraction to pypdfium. That makes xref traversal, object-stream expansion, and encrypted-document fail-closed behavior parser/dependency boundaries for this PHP lane before WordPress paragraphs or metadata are emitted.

## Behavior

The focused coverage adds two encrypted current-base fixtures:

- a current xref-stream trailer whose `/Encrypt 30 0 R` resolves to a type-2 compressed object-stream member before stale previous trailer duplicates;
- a current hybrid classic xref table with companion `/XRefStm` rows that resolve compressed `/Encrypt`, `/P`, `/EncryptMetadata`, `/CF`, `/StmF`, and `/StrF` operands before stale previous direct objects.

Both fixtures assert that encrypted text extraction stays blocked without decryption, current XMP is preserved only when the selected current `/EncryptMetadata false` permits it, Info dictionary strings stay suppressed, stale duplicate text and raw key material do not leak into metadata/preflight JSON, and no malformed encrypted-permission diagnostics are emitted.

## Verification

Focused syntax:

```text
php -l lanes/markerpdf/tests/PdfEncryptedObjectXrefCombinationCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfEncryptedObjectXrefCombinationCurrentBaseTest.php
```

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedObjectXrefCombinationCurrentBaseTest.php
1 test files, 86 assertions, 0 failures
```

Adjacent encrypted/xref/object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedObjectXrefCombinationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainEncryptOmittedRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCarrierType2MetadataAttachmentCurrentBaseTest.php
6 test files, 569 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
1651 test files, 82961 assertions, 0 failures
```

## Non-Overlap

This does not repeat accepted xref trailer `/Encrypt` Prev inheritance, current `/Encrypt null` precedence, omitted current Encrypt row repair, standalone indirect permission operands, public-key recipient selection, object-stream carrier type-2 metadata/attachment selection, hybrid free-row carrier precedence, or malformed encrypted-permission boundaries.

The new boundary is the combination of encrypted security dictionaries and operands selected through xref streams, hybrid companion xref streams, and object streams before stale duplicate security state.

## Dependency Closure

No new support component was required; the production parser already resolved this breadth. The slice reuses the native PHP direct-object scanner, xref table/stream parser, `/Prev` merger, object-stream decoder, metadata extractor, security preflight, and text extractor. Full upstream markerPDF parity remains dependency-gated by live `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark workflow tooling, and external OCR/rendering helpers.
