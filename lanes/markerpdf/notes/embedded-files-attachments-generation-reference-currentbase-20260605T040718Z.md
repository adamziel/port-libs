# markerPDF Embedded Files Attachment Generation Reference Current Base

Session: `port-dev-markerpdf-attachments-20260605T040718Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T040718Z`
Base accepted HEAD: `9bdcc4412b1e3929aacccdfe68ef298910f9c004`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes visible searchable PDF text through parser-backed pdftext/PDFium extraction. Attachment FileSpec payloads are review metadata, not visible WordPress paragraph text.
- PDF indirect references include both object number and generation. Incremental updates can leave stale generation-zero FileSpec and EmbeddedFile objects behind a `/Prev` xref chain while the latest trailer names `/Root 1 1 R` and current attachment graph references `N 1 R`.
- The native no-GPU boundary here is the lightweight `PdfAttachmentExtractor` preflight path for WordPress summaries, without Python, OCR/models, external PDF tools, attachment action execution, or raw payload promotion.

## Behavior

`PdfAttachmentExtractor` now repairs nonzero-generation direct objects for the lightweight attachment graph:

- the latest trailer `/Root` reference can add a nonzero-generation catalog even when the latest xref row has a damaged offset;
- parsed nonzero-generation references are followed through `/Pages`, `/Names /EmbeddedFiles`, catalog/page `/AF`, FileSpec `/EF`, FileAttachment annotations, and `/RF` related files;
- direct reference resolution is generation-aware, so `4 1 R` cannot bind to a selected `4 0 obj` stale FileSpec and `5 1 R` cannot bind to a selected `5 0 obj` stale EmbeddedFile stream.

The focused fixture has stale generation-zero catalog/name-tree/FileSpec/EmbeddedFile rows in the previous xref section, then current generation-one rows after the previous section. The latest xref table names `/Root 1 1 R` but gives that row a damaged offset. Before the patch the preflight fell back to one stale page-associated attachment. After the patch it reports only the three current generation-one attachments and omits all payload bytes from the summary.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs generationed attachment references after damaged latest xref rows
Values are not identical
Expected: 3
Actual: 1

1 test files, 1 assertions, 1 failures
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs generationed attachment references after damaged latest xref rows

1 test files, 49 assertions, 0 failures
```

Attachment-family verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
7 test files, 948 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachments-generation-boundary-currentbase.php
```

Result: emits `generationed_root_repaired=true`, `generationed_filespec_selected=true`, `generationed_stream_selected=true`, `stale_prev_generation_attachment_excluded=true`, `payload_bytes_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and JSON:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-attachments-generation-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachments-generation-boundary-currentbase.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode((string) file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": valid JSON\n"; }'
lanes/markerpdf/lane-status.json: valid JSON
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json: valid JSON

git diff --check -- lanes/markerpdf
passed with no output
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted platform filename source selection, `/EF` key selection, `/AFRelationship` role mapping, checksum review, related-file `/RF` name pairs, EmbeddedFiles `/Limits` pruning, EOF-bounded object scanning, current direct xref row selection, xref-stream object-stream FileSpec resolution, latest trailer Root object-number selection, full `PdfEmbeddedFileExtractor` portfolio/PieceInfo/XMP/OutputIntent review, or the richer embedded-file extractor's generation repair. The bounded behavior is only nonzero-generation direct-reference repair in the lightweight `PdfAttachmentExtractor` WordPress preflight path.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct object scanner, xref table `/Prev` reader, parsed PDF value references, FileSpec parser, stream decoder, checksum review, and WordPress smoke pattern. Full markerPDF OCR/model parity remains dependency-gated by pdftext/PDFium rendering, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, model downloads, and external OCR/rendering helpers; none were executed here.
