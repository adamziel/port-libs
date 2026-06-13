# XML/HTML DOM JATS Figure Permissions Slice

## Scope

- Added bounded JATS/BITS figure and figure-media permission summaries in `XmlHtmlDom`.
- Captured license, license-ref, license-p, and copyright-statement metadata without exposing media payload bytes.
- Preserved existing caption and media target diagnostics, `directReaderParity=false`, and metadata-only media behavior.

## Evidence

- Figure-level review packets now expose `figurePermissionSummaries`, `figureMediaTargets`, and duplicate/missing license issue codes.
- Per-figure and per-media entries retain `payloadBytesExposed=false`/`permissionPayloadBytesExposed=false`.
- Existing `figureMediaReferences[*].issues` target diagnostics remain separate from permission diagnostics.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 1 file, 3135 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` -> 46 files, 78557 assertions, 0 failures.

## Remaining Work

- Continue bounded Pandoc parity slices across XML/HTML5 DOM, JATS/BITS, EPUB3, DOCX/OpenXML, ZIP/OPC, JSON/native, citation, table, CSV/TSV, IPYNB, PDF/Typst, and format-registry surfaces while keeping `phpFail` at zero.
