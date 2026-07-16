# markerPDF xref Prev chain attachment near-miss current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T100853Z`

Base accepted HEAD: `ed88f1b21096dcd5f07b89b89e9c62085e08ed52`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF parsing, attachment preflight, and WordPress conversion through PDFium/pdftext parser boundaries before model handoff. Under the current no-GPU lane scope, this native PHP slice owns the PDF xref repair behavior needed to select current catalog attachment metadata without invoking OCR/models/Python/PDF tools.

PDF incremental updates may use an xref stream whose `/Prev` value should point to the prior xref section. Damaged producer output can land the numeric pointer just before the real previous table, for example in producer padding. The attachment preflight path must repair that bounded near-miss to the nearest valid prior top-level xref section, while keeping the latest xref stream's `/Root` authoritative.

## Behavior

`PdfAttachmentExtractor` now normalizes `/Prev` offsets for both classic xref tables and xref streams before merging previous rows. If the declared `/Prev` offset is valid, it is used directly. If it is invalid or points forward, the extractor uses the latest valid xref table or xref stream before the current section. This mirrors the existing text/metadata xref-chain repair policy and keeps current-section rows authoritative.

The focused fixture keeps generation-0 EmbeddedFiles/name-tree/page-associated-file objects in the repaired previous xref section, appends unindexed generation-1 decoys for the same object numbers, then writes a current xref stream whose `/Root 1 1 R` references the generation-0 attachment graph and whose `/Prev` lands inside padding immediately before the previous xref table. The repaired chain selects `repaired-prev-chain.csv` and `repaired-prev-page.xml`, marks the catalog AF mirror, preserves the page AF row, excludes the unindexed generation decoys, and omits raw payload bytes from the WordPress summary.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainAttachmentNearMissPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs near miss xref stream Prev offset before generation exact attachments

1 test files, 35 assertions, 0 failures
```

Scoped attachment/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachment*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChain*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevOffsetRepairCurrentBaseTest.php
18 test files, 1434 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainAttachmentNearMissPrevCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-attachment-nearmiss-currentbase.php
```

All reported no syntax errors.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-attachment-nearmiss-currentbase.php
```

The smoke emits `prev_offset_repaired_backward=true`, `attachment_count=2`, `catalog_af_mirror_marked=true`, `page_af_preserved=true`, `unindexed_generation_decoy_excluded=true`, `raw_payload_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted current-generation trailer `/Root` repair, same-generation damaged xref-stream row repair, classic table damaged `/Prev` repair, indirect `/Prev` helper repair, compressed `/Prev` helper repair, sparse latest `/Info` inheritance, latest free-row suppression, object-stream carrier recovery, xref-stream `/W` or `/Index` indirect operand repair, Type3 CharProc metric handling, or metadata-side xref repair.

The bounded behavior here is specifically `PdfAttachmentExtractor` attachment-summary selection when an xref-stream or classic table `/Prev` pointer is malformed but a valid prior xref section exists before the current section.

## Dependency closure

No new support component is needed. This slice reuses the native direct object scanner, xref table/stream parser, Flate stream decoder, PDF dictionary/value resolver, EmbeddedFiles attachment preflight, and WordPress smoke renderer. Full upstream model parity remains out of scope under the no-GPU markerPDF directive: live OCR, Surya/Torch, Texify, pypdfium/PDFium rendering, tabled-pdf model execution, Streamlit/FastAPI workers, and exact model benchmark parity were not run.
