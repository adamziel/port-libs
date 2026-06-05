# markerPDF Attachment Platform EF Key Boundary

Session: `port-dev-markerpdf-attachments-20260605T085609Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T085609Z`
Base accepted HEAD: `f5c7edb91ea7c6e3cd3926bdcae9179c3343e48f`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, delegating low-level PDF parsing to `pdftext.dictionary_output()` and PDFium page text before model/OCR fallback: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF FileSpec `/EF` dictionaries contain a subset of `/F`, `/UF`, `/DOS`, `/Mac`, and `/Unix` keys corresponding to the same filename entries in the FileSpec dictionary. Attachment import should therefore prefer the embedded stream whose `/EF` key matches the selected FileSpec filename source before fallback review.

## Behavior

The fixture uses a DOS-only FileSpec filename:

```pdf
<< /Type /Filespec
   /DOS (LEGACY\\WP-SOURCE.XML)
   /EF << /F 11 0 R /DOS 12 0 R >>
>>
```

Object `11 0 R` is a stale `/F` fallback stream and object `12 0 R` is the current DOS stream. Before the fix, `PdfAttachmentExtractor` chose `/EF /F`, so the preflight byte length/checksum came from the stale payload. `PdfEmbeddedFileExtractor` also selected the stale stream for the name-tree review row.

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now derive the FileSpec filename source first and prefer that same key in `/EF`. They still fall back to the historical `/F`, `/UF`, `/DOS`, `/Unix`, `/Mac` order for malformed PDFs where the matching stream is absent.

## Evidence

Red-first probe:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentPlatformEmbeddedFileKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL selects platform-matched EF stream for DOS FileSpec attachment preflight
Expected: 50
Actual: 52
FAIL selects platform-matched EF stream for embedded-file review rows
Expected: 1
Actual: 2
1 test files, 3 assertions, 2 failures
```

Focused passing run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentPlatformEmbeddedFileKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS selects platform-matched EF stream for DOS FileSpec attachment preflight
PASS selects platform-matched EF stream for embedded-file review rows
1 test files, 51 assertions, 0 failures
```

The WordPress smoke `wordpress-pdf-attachment-platform-ef-key-currentbase.php` validates `ef_key=DOS`, checksum match on the DOS stream, stale `/F` checksum exclusion, payload-byte omission from summary rows, visible text isolation, and no Python/model/external-tool execution.

## Non-Overlap

This does not repeat accepted EmbeddedFiles name-tree extraction, `/Limits` pruning, duplicate name-tree key suppression, catalog/page `/AF` mirror marking, FileAttachment annotation presentation metadata, related-file `/RF` name pairs, FileSpec `/FS`/`/ID`/`/V` metadata, filename path review, AFRelationship/checksum review, encrypted EFF redaction, object-stream attachment selection, trailer-root/xref attachment selection, or PieceInfo/portfolio metadata. The bounded behavior is only platform-matched `/EF` key selection for multi-entry FileSpec embedded-file streams.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, FileSpec dictionary parser, embedded-file stream decoder, checksum review, attachment summary redaction, visible-text extractor, and WordPress smoke path. GPU/model execution, live OCR, Surya/Texify/Torch, PDFium rendering, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF direction.
