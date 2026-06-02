# markerPDF Metadata PDF/A Associated Name Tree Current Base

Session: `port-dev-markerpdf-meta48-20260602T2040Z`

Micro-slice: `metadata-pdfa-associated-name-tree-currentbase`

Base accepted HEAD: `d5484d08da8e3bf2726a4fddd0260f208a15e7d9`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts page text through `pdftext.extraction.dictionary_output(...)` in `marker/pdf/extract_text.py`, keeping native text/page content distinct from PDF attachment payloads: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>.
- Upstream `marker/output.py::save_markdown` writes Markdown text and `out_metadata` to separate files, so native WordPress import should preserve attachment review metadata without leaking embedded payloads into visible paragraphs: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py>.
- Relevant PDF parser/dependency constants in pypdf expose catalog `/Names`, FileSpec `/F`, `/UF`, `/EF`, `/RF`, `/Desc`, page `/AF`, and `/OutputIntents` as dictionary keys rather than page text: <https://pypdf.readthedocs.io/en/6.8.0/_modules/pypdf/constants.html>.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php
```

Accepted-base result after adding the fixture and before source changes:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL summarizes PDF/A associated EmbeddedFiles name-tree rows on current xref catalog (lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php)
Values are not identical
Expected: 'pdfa_associated_name_tree'
Actual: NULL

1 test files, 6 assertions, 1 failures
```

## Implementation

`PdfMetadataExtractor` now derives `pdfa_associated_name_tree` during metadata merge, after root catalog PDF/A OutputIntents are known. The summary only appears when the document has a root PDF/A OutputIntent and sanitized `catalog_names_embedded_files` rows with FileSpec `/AFRelationship`.

The summary records review-only PDF/A associated-file provenance from current xref-selected `/Names /EmbeddedFiles` rows:

- root PDF/A OutputIntent identifiers and ICC profile hashes;
- name-tree attachment names, filenames, `Source`/`Schema` relationships, and standard role labels;
- embedded-file payload hashes, declared sizes, checksums, and MIME types without payload bytes;
- FileSpec XMP stream object/hash summaries without XMP text values;
- attachment-local FileSpec `/OutputIntents` identifiers and ICC profile hashes without promoting them to document-root `pdfa`;
- stale appended catalog/name-tree/FileSpec objects and out-of-limits name-tree rows stay excluded.

The WordPress smoke emits a paragraph from page content and review comments for PDF/A name-tree attachments, proving XML/schema payloads, XMP titles, ICC profile bytes, and stale name-tree rows remain review-only.

## Verification

Red/green focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS summarizes PDF/A associated EmbeddedFiles name-tree rows on current xref catalog

1 test files, 39 assertions, 0 failures
```

Focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileSchemaCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
...
6 test files, 1889 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-metadata-pdfa-associated-name-tree-currentbase.php
```

Passed: emitted `markerpdf-pdfa-associated-name-tree-currentbase` with root PDF/A identifier `Current NameTree Root PDF/A`, summary names `["migrate-source.xml","schema.xsd"]`, relationship roles `["original_source","schema_definition"]`, attachment-local PDF/A identifier `Current NameTree Attachment PDF/A`, `payload_content_omitted=true`, `associated_pdfa_not_promoted_to_root=true`, and visible text `Current PDF/A NameTree Body`.

Final local checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-pdfa-associated-name-tree-currentbase.php
php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed with no syntax, JSON, or whitespace errors.

## Status Delta

- Behavior tests move `798 -> 799`.
- WordPress scenarios move `798 -> 799`.
- Expected mapped semantics: one bounded metadata behavior for PDF/A associated files discovered through current catalog `/Names /EmbeddedFiles`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, current xref selection, catalog name-tree walker, FileSpec review parser, stream decoder, embedded-file Params checksum review, XMP stream hash summary, OutputIntent parser/profile hashing, and visible-text exclusion boundaries. Full upstream runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya/OCR, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, benchmark workflows, and live Python/model workers; none were executed for this bounded native PHP slice.

## Non-Overlap

This does not repeat root PDF/A OutputIntent extraction, OutputIntent `/AF` associated-file review, catalog `/AF` associated-file review, catalog Collection schema propagation, Portfolio PieceInfo/OutputIntent provenance, FileSpec related-file `/RF`, page `/AF`, StructTree `/AF`, generic catalog name-tree limits, or the existing name-tree FileSpec PieceInfo/OutputIntent row parsing. The new behavior is specifically the merge-time PDF/A associated-file summary for current catalog `/Names /EmbeddedFiles` FileSpec rows.
