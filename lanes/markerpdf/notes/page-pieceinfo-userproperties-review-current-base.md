# Page PieceInfo UserProperties Review

Slice: `markerpdf-page-pieceinfo-userproperties-review-current-base-20260602T0712Z`

Source-truth boundary:

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates raw PDF page and text extraction to pdftext/pypdfium before Markdown/block review; this slice keeps the review metadata native and non-executing.
- pypdf's PDF 1.7/2.0 page constants list page `/PieceInfo` as an optional page dictionary entry and catalog `/PieceInfo` as an optional catalog entry: https://pypdf.readthedocs.io/en/4.3.0/_modules/pypdf/constants.html
- The PDF reference UserProperties example requires catalog `/MarkInfo /UserProperties true`, then stores structure attributes under `/O /UserProperties` with `/P` user-property dictionaries containing `/N`, `/V`, optional `/H`, and optional `/F`: https://www.verypdf.com/document/pdf-format-reference/pg_0877.htm
- PDFBox exposes the same dependency behavior through `PDMarkInfo::usesUserProperties()` and `PDUserAttributeObject::getOwnerUserProperties()`: https://pdfbox.apache.org/docs/2.0.4/javadocs/org/apache/pdfbox/pdmodel/documentinterchange/logicalstructure/PDMarkInfo.html and https://pdfbox.apache.org/docs/2.0.7/javadocs/org/apache/pdfbox/pdmodel/documentinterchange/logicalstructure/PDUserAttributeObject.html

Native PHP behavior added:

- `PdfPagePropertyExtractor` walks catalog-ordered page objects, extracts direct or indirect page `/PieceInfo` application dictionaries, and normalizes `/LastModified` plus `/Private` review values.
- It honors catalog `/MarkInfo /UserProperties true` before traversing `/StructTreeRoot /K` structure elements, then collects `/A` attribute dictionaries whose owner is `/UserProperties`.
- User-property rows carry structure type, title, attribute object number, name, value, formatted value, and hidden state without executing PDF actions or loading external PDF tooling.
- `wordpress-pdf-page-pieceinfo-userproperties-import.php` emits the page review metadata as WordPress-safe comments while preserving visible paragraph text.

Focused evidence:

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` failed on current base with `Class "PortLibs\\MarkerPDF\\PdfPagePropertyExtractor" not found`.
- Passing focused gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` passed with `1 test files, 38 assertions, 0 failures`.
- Syntax checks passed for `PdfPagePropertyExtractor.php`, `PdfPagePropertyExtractorTest.php`, and `wordpress-pdf-page-pieceinfo-userproperties-import.php`.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-page-pieceinfo-userproperties-import.php` emitted `page_review_count: 1`, `piece_info_apps: ["WPImporter"]`, user property names `["WP Block","Supplier"]`, `hidden_user_property_count: 1`, and `Page Property Review` paragraph text with `executes_python_or_models=false` and `executes_external_pdf_tools=false`.
- Integration-base full gate: `php tools/run-tests.php lanes/markerpdf/tests` passed with `58 test files, 2330 assertions, 0 failures`.
- Integration-base diff check: `git diff --check -- lanes/markerpdf` passed.

Dependency closure:

- No new support component is needed. The slice reuses bounded native PDF object scanning, page-tree ordering, dictionary/value parsing, PDF string/name decoding, and tagged-PDF review metadata traversal. Full upstream Python/model/benchmark parity remains dependency-gated.
