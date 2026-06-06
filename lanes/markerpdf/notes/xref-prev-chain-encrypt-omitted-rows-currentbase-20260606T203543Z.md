# markerPDF xref Prev chain Encrypt omitted rows current-base

Date: 2026-06-06 UTC
Base accepted HEAD: `1a04e44c91a22f3d4217b77b07bd40823238f1c6`
Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T203543Z`

## Source-truth behavior

Upstream markerPDF delegates searchable-PDF parsing to `pdftext`/PDFium before OCR/model stages. Under the current no-GPU markerPDF scope, this lane owns the native PHP parser boundary where xref-selected objects, trailers, metadata, encryption preflight, and WordPress import decisions are recovered without running models or external PDF tools.

PDF incremental updates can append same-generation replacement objects before the latest xref stream while the latest xref stream omits some current rows. Existing current-base repair handled latest trailer `/Root` and `/Info` graph rows before stale `/Prev` inheritance. This slice adds the same bounded repair for a latest trailer `/Encrypt` reference so current encryption dictionaries and `EncryptMetadata` policy are selected before stale previous-section encryption rows.

## Implementation

- `PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now seed omitted current-update graph repair from `/Encrypt` alongside `/Root` and `/Info`.
- The repair remains bounded to direct object definitions between the previous xref offset and the current xref offset, and it does not override explicitly present current xref rows.
- Added `PdfXrefPrevChainEncryptOmittedRowsCurrentBaseTest.php`, where the latest xref stream names `/Encrypt 30 0 R` but omits object 30 while `/Prev` contains a stale same-generation object 30.
- Added `wordpress-pdf-xref-prev-chain-encrypt-omitted-rows-currentbase.php` to show the WordPress import preflight boundary: current XMP is preserved because current `EncryptMetadata false` is selected, while encrypted text remains blocked and stale key material stays out of review output.

## Verification

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainEncryptOmittedRowsCurrentBaseTest.php`

Result before source repair: `1 test files, 1 assertions, 1 failures`; metadata source was only `encryption` because stale `/Prev` encryption suppressed current XMP.

Focused after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainEncryptOmittedRowsCurrentBaseTest.php`

Result: `1 test files, 19 assertions, 0 failures`.

Focused family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainEncryptOmittedRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainOmittedCurrentRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileEncryptedEffBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedRelatedFileBoundaryCurrentBaseTest.php`

Result: `7 test files, 822 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-encrypt-omitted-rows-currentbase.php`

Output confirms `current_encrypt_dictionary_selected=true`, `current_xmp_preserved=true`, `encrypt_metadata_false_selected=true`, `stale_prev_encrypt_suppressed=true`, `encrypted_text_blocked=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat existing `/Prev` inheritance of encryption when the current trailer omits `/Encrypt`, explicit current `/Encrypt null` clearing, damaged `/Root` and `/Info` offset repair, omitted current `/Root` and `/Info` graph repair, same-generation metadata/attachment offset repair, xref-stream indirect `/Prev` helper repair, compressed `/Prev` helpers, object-stream carrier recovery, free-row suppression, or DCT/filter/image preview work.

The bounded new behavior is only latest trailer `/Encrypt` same-generation current-row repair before stale `/Prev` encryption rows are inherited.

## Dependency closure

No new support component is needed. The slice reuses the native PHP direct-object scanner, xref table/stream parser, `/Prev` chain walker, current-update graph repair, metadata extractor, security preflight, encrypted attachment review paths, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers, which remain intentionally out of scope under the no-GPU directive.

## Next task

Continue native no-GPU markerPDF parser work with a non-overlapping xref, metadata, annotation/form, page-geometry, image/filter, or supplied-boundary table/equation handoff.
