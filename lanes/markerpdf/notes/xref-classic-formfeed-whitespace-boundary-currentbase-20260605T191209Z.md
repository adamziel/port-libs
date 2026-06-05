# markerPDF classic xref form-feed whitespace boundary current base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T191209Z`
Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T191209Z`
Base accepted HEAD: `1fee675cfc053b65d6824b32dd8851f66511d8c2`

## Source Truth

Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`
delegates searchable PDF text, metadata, and attachments to parser-backed PDF
extraction before OCR/model fallback. Under the current no-GPU scope, this
slice maps a native classic xref-table parser boundary: PDF form-feed is
lexical whitespace and must not cause rebuild selection to fall back to stale
previous xref tables.

## Behavior

Some PDFs place form-feed whitespace after the `xref` or `trailer` keywords in
a current classic xref table. The parser already accepted NUL whitespace in
classic xref row bodies and form-feed at keyword-boundary checks, but the row
reader normalized only NUL before applying line-oriented subsection parsing.
That meant a damaged final `startxref` could skip the current table and select
stale page text, XMP/Info metadata, EmbeddedFiles, and attachment summaries
from an earlier table.

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and
`PdfAttachmentExtractor` now normalize form-feed to ordinary whitespace before
classic xref row parsing. Row validation remains strict: malformed first
sections still fail closed, and accepted partial-trailing-subsection behavior
is preserved.

## Evidence

Red probe before source edit:

```text
Fixture with current classic table using form-feed after xref selected:
Stale FF xref page
```

Focused verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicFormFeedWhitespaceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS accepts form-feed PDF whitespace around rebuilt classic xref sections before WordPress imports

1 test files, 29 assertions, 0 failures
```

Adjacent classic-xref verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicFormFeedWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicPdfWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicGenerationOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicStartxrefOperandTailBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 838 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-formfeed-whitespace-currentbase.php
```

The smoke exits 0 and emits `formfeed_whitespace_accepted=true`,
`current_classic_xref_import_kept=true`, `stale_prev_import_excluded=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`,
with two current Gutenberg paragraph comments.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted NUL xref whitespace, malformed first-section row
rejection, punctuation row suffix rejection, zero-count subsection rejection,
comment-only row skipping, commented `xref`/`startxref` tokens, name,
composite, literal, hex, or stream-owned decoys, post-`startxref` trailers,
partial trailing xref subsections, direct object offset repair, `/Prev`
generation repair, xref-stream/object-stream repair, hybrid xref behavior,
metadata-only extraction, font/CMap behavior, image/filter metadata,
annotations, forms, security preflight, OCR/model work, or supplied table/equation
handoffs.

The bounded behavior is only accepting form-feed as PDF whitespace around
current classic xref table syntax before rebuild selection chooses text,
metadata, EmbeddedFiles, and attachment roots.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object
scanner, classic xref table parser, startxref rebuild selection, metadata
extractor, text extractor, embedded-file extractor, attachment preflight
summarizer, stream decoder, and WordPress smoke path. Live OCR, PDFium
rendering, Surya/Torch/Texify models, Streamlit/FastAPI workers, and exact
upstream model benchmark parity remain intentionally out of scope under the
current no-GPU markerPDF direction.
