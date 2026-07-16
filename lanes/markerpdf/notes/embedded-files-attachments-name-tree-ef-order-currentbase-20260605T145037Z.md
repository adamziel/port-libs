## markerpdf embedded-files attachment boundary current-base

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T145037Z`
Base accepted HEAD: `ab0579d2d089b95ff0a65136decc676646ae544e`

Implemented a native no-GPU attachment boundary for FileSpec dictionaries that
do not contain `/F`, `/UF`, `/DOS`, `/Unix`, or `/Mac` filename entries and
therefore use the `/Names /EmbeddedFiles` name-tree key as the WordPress display
filename. In that fallback case, both `PdfAttachmentExtractor` and
`PdfEmbeddedFileExtractor` now choose embedded file streams in standard
`/UF`, `/F`, `/Unix`, `/Mac`, `/DOS` fallback order, while preserving the
existing platform-matched behavior when a real FileSpec filename key is present.

Source truth:

- Upstream markerPDF boundary: attachments remain native searchable-PDF parser
  metadata under the current no-GPU scope; no Surya/OCR/model execution is
  needed for EmbeddedFiles preflight.
- PDF parser behavior: FileSpec `/EF` dictionaries contain embedded file stream
  entries keyed by `/F`, `/UF`, `/DOS`, `/Mac`, and `/Unix`; when no matching
  platform key exists, attachment readers such as HexaPDF use `/UF`, `/F`,
  `/Unix`, `/Mac`, `/DOS` fallback order.

Focused evidence:

- Red-first before implementation:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreeFallbackEfOrderCurrentBaseTest.php`
  failed after 2 assertions because the summary selected stale `/F` bytes
  instead of the current `/UF` stream.
- After implementation:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreeFallbackEfOrderCurrentBaseTest.php`
  => 1 test files / 40 assertions / 0 failures.
- Adjacent attachment EF/name metadata family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentPlatformEmbeddedFileKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentIndirectNameKeyCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataBoundaryCurrentBaseTest.php`
  => 3 test files / 129 assertions / 0 failures.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-attachment-name-tree-ef-order-currentbase.php`
  emits `ef_key=UF`, `filename_source=name_tree_key`,
  `stale_f_stream_excluded=true`, `payload_bytes_omitted_from_summary=true`,
  `visible_text_excludes_attachment_payloads=true`,
  `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Dependency closure:

- No new support component is required. This reuses the existing native PDF
  object parser, FileSpec dictionary parser, stream decoders, and attachment
  summary paths. GPU/model/OCR execution remains intentionally out of scope.

Next task:

- Continue non-overlapping native markerPDF parser behavior around searchable
  PDF attachments, fonts/CMaps, stream filters, xref repair, forms,
  annotations, metadata, image/filter metadata, or supplied-boundary table and
  equation handoffs.
