# markerPDF RichMedia Embedded Filespec Review Current Base

Session: `port-dev-markerpdf-rich16pdf-20260602T1410Z`
Micro-slice: `richmedia-embedded-filespec-review-currentbase-20260602T1410Z`
Base accepted HEAD: `e92df18f9a63d857eb719b1ac9d04dd454003a3a`

## Source Truth

- Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes visible PDF text through page text/dictionary output boundaries in `marker/pdf/extract_text.py`; this slice preserves that boundary by producing non-rendered review metadata without invoking `pdftext`, pypdfium, Python workers, or model downloads: https://raw.githubusercontent.com/datalab-to/marker/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF rich-media structure uses annotation-scoped RichMediaContent, configurations, and RichMediaInstance assets. The PDF Association ISO 32000-2 errata records that RichMediaInstance `/Asset` is a FileSpec dictionary and that media objects are typically FileSpec dictionaries with embedded file streams: https://pdf-issues.pdfa.org/32000-2-2020/clause13.html
- The existing accepted markerPDF slice already maps RichMediaExecute `/TA`, `/TI`, direct `/C`, `/A`, and legacy `/CMD` command review. This slice is the adjacent FileSpec stream review boundary for the target asset, not another action-chain traversal slice.

## Implemented Behavior

- `PdfRichMediaAnnotationExtractor` now adds `embedded_file_streams` to RichMedia target-instance asset FileSpec rows.
- Each stream row records the EF key, object number, MIME type, declared stream length, decoded size, SHA-256 content hash, `/Params /Size`, `/Params /CheckSum`, computed MD5, checksum match state, and creation/modification dates.
- The review row never returns embedded media bytes, never executes media or JavaScript, and does not promote stale catalog EmbeddedFiles into the current target asset row.
- The WordPress smoke now fails unless the target asset checksum review is present and still confirms media payload text, action JavaScript, appearance text, and stale catalog media payload text are absent from visible paragraphs.

## Evidence

Red-first focused failure after adding the expected stream metadata assertions:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
PHP Warning:  Undefined array key "embedded_file_streams" in lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php on line 838
FAIL reviews rich media execute target instances command arguments and embedded media without execution
count(): Argument #1 ($value) must be of type Countable|array, null given

1 test files, 412 assertions, 1 failures
```

Focused pass after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php
1 test files, 470 assertions, 0 failures
```

Adjacent rich-media, embedded-file, and native text boundary pass:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
3 test files, 1303 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-richmedia-embedded-action-media-currentbase.php
```

The smoke emitted `review_annotation_count=1`, `review_action_count=3`, `target_instance_object=41`, `target_instance_asset=action-video.mp4`, `target_instance_asset_size=57`, `target_instance_asset_sha256=72e32b0202f20557934257f7d25f21bd3036ed712af6120144728aa0fb33b23d`, `target_instance_asset_declared_size=57`, `target_instance_asset_checksum_matches=true`, `target_instance_asset_created_at=D:20260602141000Z`, `target_instance_asset_modified_at=D:20260602141100Z`, `stale_catalog_media_excluded=true`, `payload_text_excluded=true`, and all execution flags false.

## Status Delta

- Behavior tests move `513 -> 514`.
- Mapped markerPDF semantics move `361 -> 362 / 78`.
- Focused rich-media assertions move from the accepted `434` to `470`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted RichMediaExecute target annotation/instance command parsing, RichMedia GoToE attachment actions, Screen/Rendition `/OP` and `/JS` review metadata, playback `/P`/`/SP` policy dictionaries, detached Screen/Movie target boundaries, movie/sound/rendition popup rows, generic annotation action review, or catalog EmbeddedFiles attachment extraction. The new behavior is limited to the target RichMediaInstance `/Asset` FileSpec embedded stream metadata and `/Params` checksum/date review.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, dictionary/array tokenizer, page annotation traversal, FileSpec/EF parsing, scalar decoders, limited stream decoding for review metadata, action-chain walker, and visible text extraction boundary. Full upstream markerPDF Python/pdftext/pypdfium/Surya/Texify/model benchmark parity remains dependency-gated and was not executed.
