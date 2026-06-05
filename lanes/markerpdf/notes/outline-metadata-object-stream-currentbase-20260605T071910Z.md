## markerpdf-outline-metadata-boundary-current-base-20260605T071910Z

Scope: native no-GPU markerPDF outline metadata boundary on accepted base
`da7b62c5099050bda765163fcb64c1d4fb8a0bc5`.

Source truth:
- Upstream markerPDF receives TOC/bookmark rows through PDFium/pdftext for
  searchable PDFs; PDF 1.5 object streams are standard parser input and should
  not cause bookmarks to disappear from review metadata.
- This slice stays in the native parser/converter scope: no OCR, Surya,
  Texify, Torch, pypdfium, Python model workers, or external PDF tools.

Behavior added:
- `PdfOutlineExtractor` now expands bounded `/Type /ObjStm` dictionary members
  before resolving catalog `/Outlines`, outline items, local `/S /GoTo`
  actions, and chained review-only `/URI` actions.
- Compressed outline titles and action URI strings remain review metadata and
  are not promoted into visible WordPress paragraph text.

Evidence:
- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataObjectStreamCurrentBaseTest.php`
  failed with `1 test files, 8 assertions, 1 failures` because compressed
  outline rows were absent from TOC/navigation review.
- After fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataObjectStreamCurrentBaseTest.php`
  passed with `1 test files, 22 assertions, 0 failures`.
- Outline family: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php`
  passed with `43 test files, 2328 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-object-stream-currentbase.php`
  passed and emitted compressed outline navigation rows, `GoTo`/`URI` review
  action metadata, `visible_text_excludes_outline_metadata=true`,
  `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Dependency closure:
- No new support component is needed. This reuses the lane-local native PDF
  tokenizer plus bounded Flate decoding already used by the searchable-PDF
  parser surface. GPU/model parity remains intentionally out of scope.

Non-overlap:
- Avoids the accepted PageLabels comment-boundary slice and existing direct
  outline parent/prev/last/comment/title/generation boundaries. This patch is
  specifically about compressed object-stream outline dictionaries feeding TOC
  and navigation review metadata.
