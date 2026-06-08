# Classic Xref Double-EOF Rebuild Boundary Current Base

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T090654Z`  
Base accepted HEAD: `05e4fcd3a69e7d0bfda76b2957c43641b3f4f01c`

## Source Truth

- Upstream `sddai/markerPDF` relies on native searchable-PDF parser output before OCR/model stages. This PHP lane mirrors that native parser boundary for text, metadata, EmbeddedFiles, and attachment preflight without launching PDFium, OCR, Surya, Texify, Torch, Streamlit/FastAPI workers, or external PDF tools.
- A damaged numeric classic `startxref` can be repaired by scanning for a prior usable classic xref table, but the selected revision's first top-level `%%EOF` closes that repair window unless a later usable `startxref` marker explains a newer damaged revision. A later post-EOF classic `xref`/`trailer`/`%%EOF` block without a selectable startxref is garbage and must not become the WordPress import root.

## Behavior

Classic rebuild boundary selection in text, metadata, embedded-file, attachment, and free-object-map paths now tracks the first top-level EOF after the selected `startxref` token. When the selected numeric operand points to no classic table, rebuild scanning will not cross that EOF to a later xref table unless a later top-level startxref token appears between the candidate table and the later EOF.

This preserves accepted recovery for prior valid `startxref` plus missing final startxref revisions, including the free-object-map path, while closing the double-EOF leak where a post-EOF decoy table previously replaced:

- current page text;
- XMP and trailer Info metadata;
- catalog `/Names /EmbeddedFiles`;
- attachment summary/preflight rows.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildDoubleEofBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bounds damaged classic xref rebuild to the first EOF before later EOF trailer garbage
Expected: array (
  0 => 'Current double-EOF xref page',
  1 => 'First EOF boundary kept',
)
Actual: array (
  0 => 'Post EOF double xref decoy page',
  1 => 'Second EOF root leak',
)
1 test files, 4 assertions, 1 failures
```

## Verification

Focused repair check after the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildDoubleEofBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS bounds damaged classic xref rebuild to the first EOF before later EOF trailer garbage
1 test files, 30 assertions, 0 failures
```

Regression set for the new case plus the missing-final-startxref repairs that must remain accepted:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildDoubleEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildPriorStartxrefMissingFinalBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 157 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-double-eof-boundary-currentbase.php
```

Result: emits Gutenberg paragraphs for `Current double-EOF xref page` and `First EOF boundary kept`, with diagnostic booleans `uses_current_classic_trailer_root=true`, `keeps_current_metadata_root=true`, `keeps_current_info_root=true`, `imports_current_attachment=true`, `current_attachment_checksum_matches=true`, `excludes_post_eof_xref_page=true`, `excludes_post_eof_root_leak=true`, `excludes_post_eof_metadata=true`, `excludes_post_eof_attachment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted classic xref EOF-boundary case where post-EOF xref garbage had no closing EOF, the missing-startxref EOF-boundary case, comment-only startxref recovery, prior valid startxref missing-final recovery, object-owned/composite startxref bounds, malformed row/subsection guards, private-tail startxref rejection, xref stream `/Prev` repair, object stream repair, or hybrid xref behavior.

The bounded behavior here is specifically the double-EOF classic table rebuild leak: selected numeric startxref is damaged, the current table is before the first EOF, and a later xref/trailer/second-EOF block with no selectable startxref must remain non-current garbage.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, selected-startxref parser, classic xref table parser, trailer parser, metadata extractor, EmbeddedFiles extractor, free-object-map helper, attachment summary path, and WordPress smoke renderer. GPU/model OCR, PDFium rendering, external PDF tools, live Surya/Texify/Torch execution, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
