# markerPDF classic xref post-startxref trailer boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T111130Z`
Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T111130Z`
Base accepted HEAD: `c13b20681f4805fae1edeffd61b3ffb4b45a217c`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF text extraction and low-level xref parsing to pdftext/PDFium before model-dependent OCR/layout/table work. In the native no-GPU PHP lane, the corresponding boundary is the classic xref table grammar: subsection rows must be followed by a trailer dictionary before the file-level `startxref` and `%%EOF` markers. A trailer dictionary found after those markers is not part of the selected xref section and must not replace current WordPress page text, metadata, or EmbeddedFiles attachments.

## Change

- `PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now stop classic xref trailer lookup when a top-level `startxref` or `%%EOF` marker is reached before `trailer`.
- Added a focused fixture where the final `startxref` points at an incomplete decoy classic xref table whose only trailer appears after `startxref/%%EOF`.
- Added a WordPress smoke that proves current paragraphs, XMP/Info metadata, EmbeddedFiles extraction, and attachment preflight remain on the current classic table while the decoy trailer/root is excluded.

## Evidence

Red-first focused run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
```

Result: `1 test files, 490 assertions, 1 failures`; the new case imported `Post startxref trailer decoy page` / `Late trailer root leak`.

After implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
```

Result: `1 test files, 516 assertions, 0 failures`.

Syntax checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-classic-xref-post-startxref-trailer-currentbase.php
```

Result: no syntax errors detected.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-post-startxref-trailer-currentbase.php
```

Result: emits `post_startxref_trailer_rejected=true`, `current_classic_xref_import_kept=true`, `decoy_post_startxref_trailer_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Diff hygiene:

```bash
git diff --check -- lanes/markerpdf
```

Result: clean.

## Status delta

`lanes/markerpdf/lane-status.json` records `phpPass` `1762` and `wordpressScenarios` `1606` for the focused xref boundary behavior. No blocker remains for this slice.

## Next task

Continue native classic xref repair parity on a non-overlapping boundary, preferably `/Prev` chain validation across mixed current/incremental sections or bounded xref-stream/classic handoff preflight without invoking OCR, GPU models, or external PDF tools.

## Non-overlap

This does not repeat accepted damaged `startxref` rebuild, stale valid `startxref` repair, post-EOF garbage after a complete current trailer, commented/name/composite `startxref`, name-delimited `xref`, malformed or punctuation xref rows, comment-only rows, malformed trailing subsections before trailer, literal-string xref offsets, stream-owned trailer dictionaries, stream-owned composite tokens, forward `/Prev` repair, xref-stream `/Prev`, object-stream, free-entry, hybrid, encryption, metadata, attachment generation, or DCTDecode filter-boundary slices. The bounded behavior is specifically rejecting a classic xref section whose trailer dictionary only appears after `startxref` or `%%EOF`.

## Dependency closure

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref table parser, trailer parser, metadata extractor, embedded-file extractor, attachment preflight, page-tree walker, text-token extractor, and WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/table models, Texify, tabled-pdf, Streamlit/FastAPI workers, and external OCR/rendering tools remain intentionally out of scope for this no-GPU markerPDF slice.
