# XMP Complete Packet Fallback Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T100148Z`

Base accepted HEAD: `0754799f0c08174faccf966c438b59d4201dd77c`

## Source Truth

Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. In the current no-GPU PHP lane, Catalog `/Metadata` XMP is a native parser boundary before WordPress import. XMP packet bytes must not become visible paragraphs, and stale or padding packets must not override the first current packet that contains document metadata.

This slice stays inside native PDF metadata extraction. It does not run OCR, Surya, Texify, Torch, PDFium rendering, Python workers, online services, or external PDF tools.

## Behavior

Some producers leave a complete xpacket body that contains only an XML declaration plus an empty Adobe `x:xmpmeta` wrapper before the current complete packet. Before this patch, `PdfMetadataExtractor` returned bounded root candidates from that first complete packet, parsed no metadata from the empty wrapper, and never considered the later current packet.

`PdfMetadataExtractor` now keeps scanning complete packet bodies while every bounded candidate in the packet is only an empty Adobe `xmpmeta` wrapper. The current non-empty complete packet then supplies document XMP and rejected-stream review summaries, while later decoy packets remain excluded.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpCompletePacketFallbackCurrentBaseTest.php
```

Result before implementation:

- accepted metadata source was `["info"]` instead of `["xmp","info"]`;
- rejected XML stream review had no `xmp_summary.field_names`.

The failing run reported `1 test files, 14 assertions, 2 failures`.

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpCompletePacketFallbackCurrentBaseTest.php
```

Passed: `1 test files, 40 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php
```

Passed: `21 test files, 1728 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-xmp-complete-packet-fallback-currentbase.php
```

Passed. The smoke emits `empty_complete_packet_skipped=true`, `packet_boundary_applied=true`, `trailing_decoy_excluded=true`, `visible_text_excludes_xmp_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and format checks:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpCompletePacketFallbackCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-complete-packet-fallback-currentbase.php` passed.
- `git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Adds 2 focused TestRunner PASS cases and 40 focused assertions.
- Adds 1 WordPress smoke/example.
- Manifest mapped count: `733 -> 734`.
- `phpPass`: `1698 -> 1700`.
- `wordpressScenarios`: `1557 -> 1558`.

## Non-Overlap

This does not repeat accepted direct catalog XMP extraction, packet padding, comment/DOCTYPE skipping, CDATA false closers, DTD/entity rejection, UTF-16 decoding, wrong-namespace wrapper filtering, same-prefix non-Adobe packet filtering, unpaired begin recovery, xpacket instruction validation, empty/self-closing root skipping inside one packet, language alternatives, typed-node parsing, qualified values, nested qualifier suppression, PDF/A schema correlation, encrypted metadata source priority, xref metadata generation repair, or CMap/font/text extraction slices. The bounded behavior is specifically complete metadata-empty xpacket bodies before a later current complete XMP packet.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP packet scanner, DOM-based XMP field extraction, metadata review summary path, text extractor, and WordPress smoke pattern. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.
