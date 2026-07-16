# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260602T234421Z`

Base accepted HEAD: `7daebccdb1e231332676891328ab6455e928870a`

## Behavior Added

- Added native PHP OPC package XML support:
  - `OpcContentTypes` parses/serializes `[Content_Types].xml`, records
    default and override content types, resolves MIME types by override first
    and case-insensitive extension fallback, and rejects duplicate or unsafe
    package part names.
  - `OpcRelationships` parses/serializes package `.rels` XML, maps source parts
    to relationship part names and back, resolves root and part-local internal
    targets, preserves external hyperlinks, filters by relationship type, and
    rejects duplicate relationship ids or malformed namespace/target data.
  - `OpcPackagePath` canonicalizes package part names and rejects path
    traversal, absolute internal relationship URIs, backslashes, NULs, query,
    and fragment components where OPC part names must be package paths.
  - `OpcRelationship` carries relationship id/type/target/TargetMode state.
- Added `wordpress-docx-opc-preflight.php` as a local smoke example for DOCX
  import preflight: it resolves the office document part, core properties,
  styles, footnotes, media parts, and external WordPress reviewer edit link
  without invoking Pandoc, Word, LibreOffice, zip/unzip, or online services.

## Source Truth

- Upstream Pandoc Docx reader at
  `src/Text/Pandoc/Readers/Docx/Parse.hs` reads `_rels/.rels`, locates the
  `officeDocument` relationship by type, and uses the relationship `Target` as
  the document XML path.
- OPC package XML namespaces used:
  - `http://schemas.openxmlformats.org/package/2006/content-types`
  - `http://schemas.openxmlformats.org/package/2006/relationships`

## Verification

- `php -l lanes/pandoc/src/OpcPackagePath.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/OpcRelationship.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/OpcRelationships.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/OpcContentTypes.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 88 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `2 test files, 2405 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php | rg -n 'document|rIdHero|hasReviewerEditLink|/word/media/hero.PNG'`
  - Result: expected document part, media part, and reviewer edit link fields found.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

This slice adds the native PHP OPC XML support component needed by richer
DOCX/OpenXML conversion. It requires no new external service and no shell-outs
to Pandoc, Word, LibreOffice, zip/unzip, template engines, TeX/PDF engines, or
Haskell test binaries. It reuses PHP DOM/libxml, which is already available in
the lane environment. The next dependency gate is wiring these XML helpers to
the ZIP/OPC package primitives owned by the sibling
`pandoc-shared-zip-package-core-*` slices.

## Non-Overlap

This does not edit dashboard/progress files and does not touch the accepted
Markdown writer block-start list-marker escaping, tables, definition lists,
raw Markdown-family, Roman/alpha marker, soft-break, heading-attribute, or
HTML-reader branches. It also does not implement ZIP package storage, DOCX body
XML conversion, YAML metadata, doctemplates, CSL, EPUB/ODT, PDF, or upstream
runner dependency auditing.
