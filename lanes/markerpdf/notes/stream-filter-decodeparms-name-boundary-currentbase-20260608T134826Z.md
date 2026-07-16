2026-06-08 UTC isolated markerpdf-stream-filter-stack-boundary-current-base-20260608T134826Z

Scope:
- Native no-GPU PDF parser stream-filter boundary only.
- Source-truth behavior: PDF stream `/DecodeParms /Name` is a Crypt filter parameter. Ordinary page-content filters such as `/FlateDecode` must not treat `<< /Name /Identity >>` as harmless default DecodeParms and import decoded text.
- Non-overlap: this does not repeat stale/short `/Length`, ASCII85/RunLength/LZW terminator, duplicate DecodeParms parameter, object-stream filter-owner, attachment DecodeParms, CMap filter, image-filter, OCR/model, or AcroForm work.

Implementation:
- `PdfTextExtractor::canApplyDecodeParms()` now rejects top-level DecodeParms `/Name` when the active stream filter is not `/Crypt`.
- Identity `/Crypt` stack stages still pass through, preserving the existing accepted Crypt behavior.
- Direct, indirect, and stacked DecodeParms `/Name` cases on non-Crypt page content fail closed before WordPress-visible text import.

Evidence:
- Red-first before source patch:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterDecodeParmsNameBoundaryCurrentBaseTest.php`
  failed with 2 assertions / 2 failures because `DecodeParms Name Flate Leak`, `Indirect DecodeParms Name Leak`, and `Stacked DecodeParms Name Leak` imported as text.
- After source patch:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterDecodeParmsNameBoundaryCurrentBaseTest.php`
  passed with 1 test file / 21 assertions / 0 failures.
- Adjacent stream-filter stack check:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`
  passed with 1 test file / 437 assertions / 0 failures.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-stream-filter-decodeparms-name-boundary-currentbase.php`
  exited 0 with `non_crypt_decodeparms_name_failed_closed=true`, `crypt_identity_decodeparms_name_preserved=true`, no Python/models, and no external PDF tools.

Status delta:
- Adds 2 focused PASS cases and 21 focused assertions.
- Adds 1 WordPress-relevant smoke scenario.

Dependency closure:
- No new support component is needed. The slice reuses the existing native PDF tokenizer, stream filter stack decoder, and WordPress paragraph smoke path.

Next:
- Continue with non-overlapping native searchable-PDF parser behavior: stream filters outside `/Name`, xref repair, font/CMap handling, metadata/outlines/annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
