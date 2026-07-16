# Classic xref private-tail EOF boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T073343Z`
Session: `port-dev-markerpdf-xref-classic-rebuild-20260608T073343Z`
Base accepted HEAD: `c0961f7d76e4f4ac51c31452364f795d95eceddf`

## Source Truth

markerPDF imports searchable PDF text, document metadata, and attachments through parser layers before any OCR/model path. In the native no-GPU scope, classic xref rebuild must follow PDF parser boundaries: a numeric `startxref` operand with a private non-comment tail is rejected, and later bytes after that rejected revision's EOF must not become a rebuild target.

This slice extends the accepted numeric private-tail boundary to post-EOF garbage. Before the fix, a valid current revision followed by a rejected `startxref` private-tail revision and then post-EOF xref/trailer garbage rebuilt to the post-EOF decoy. Current WordPress page text, XMP/Info metadata, EmbeddedFiles, attachment summary, and free xref rows were replaced by the decoy revision.

## Implementation

- `PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now detect the latest top-level rejected `startxref` token whose operand parser returns `null`.
- `PdfXrefFreeObjectMap` uses the same boundary so annotation/free-row suppression follows the selected import root.
- When such an invalid top-level token is after the last valid `startxref`, classic rebuild scans are capped at the first top-level `%%EOF` after that invalid token, preventing later post-EOF xref garbage from winning.

## Evidence

Red-first focused run before parser changes:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildPrivateTailEofBoundaryCurrentBaseTest.php`

Result: `1 test files, 6 assertions, 1 failures`; the extractor selected `Post EOF private-tail decoy page` and `Later EOF root leak`.

After implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildPrivateTailEofBoundaryCurrentBaseTest.php`

Result: `1 test files, 36 assertions, 0 failures`.

Adjacent classic-xref subset:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildPrivateTailEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicStartxrefOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildMissingStartxrefEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildPriorStartxrefMissingFinalBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildCommentOnlyStartxrefCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildObjectOwnedStartxrefCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildStreamPayloadBoundaryCurrentBaseTest.php`

Result: `8 test files, 269 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-private-tail-eof-currentbase.php`

Result: exits 0 and emits `uses_current_page_text=true`, `rejects_private_tail_revision=true`, `excludes_post_eof_decoy=true`, `metadata_title_current=true`, `info_title_current=true`, `embedded_file_current=true`, `free_row_current=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed no-digit `startxref` EOF repair, missing final `startxref` rebuild, comment-only `startxref`, object-owned/composite ignored tokens, stream-owned xref payloads, literal/name xref decoys, private-tail rejection without post-EOF garbage, malformed subsection rows, plus headers, zero-count subsections, forward `/Prev`, xref-stream repair, object-stream repair, CMap/filter work, OCR/model execution, or supplied-boundary table/equation work.

The bounded behavior is only post-EOF classic xref garbage after a rejected top-level numeric private-tail `startxref` revision.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP tokenizer, direct-object inventory, classic xref table parser, text extractor, metadata extractor, EmbeddedFiles/attachment extractors, and free-object map. GPU/OCR/model execution, Python workers, external PDF tools, pypdfium/PIL, Surya/Texify/Torch, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
