# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T010428Z`

Base accepted HEAD: `0ea8dd0772ccf1520f53c121288a94ef07992eca`

## Behavior Added

- Tightened `OpcContentTypes` so `[Content_Types].xml` `ContentType` values
  must be MIME-style `type/subtype` tokens with optional `name=value`
  parameters.
- Preserves valid OPC media types such as
  `application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml`
  and parameterized values such as `image/svg+xml; charset=UTF-8`.
- Rejects malformed content types before package preflight treats parts as
  importable resources:
  - empty subtype or empty type;
  - double slash subtype separators;
  - whitespace inside the base media type;
  - dangling semicolons;
  - missing parameter names or values;
  - unterminated quoted parameter values.
- Updated the WordPress DOCX OPC preflight example to carry a parameterized SVG
  part through relationship summary and media inventory output.

## Source Truth

- OPC packages identify each part's content type as a MIME-style media type in
  `[Content_Types].xml`:
  https://learn.microsoft.com/en-us/previous-versions/windows/desktop/opc/open-packaging-conventions-overview
- The OOXML OPC content-types section records the media-type shape as
  `type/subtype` with optional parameters:
  https://c-rex.net/samples/ooxml/e1/Part2/OOXML_P2_Open_Packaging_Conventions_Content_topic_ID0EJPAG.html
- Existing Pandoc DOCX package loading depends on OPC package relationships:
  read `_rels/.rels`, locate the `officeDocument` relationship, and load the
  referenced source part. This slice stays within native PHP OPC package
  semantics and does not claim Haskell runner parity.

## Red Check

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: failed because malformed content types were
    still accepted; `1 test files, 284 assertions, 1 failures`.

## Verification

- `php -l lanes/pandoc/src/OpcContentTypes.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 293 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4992 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 24 to 25 PASS cases.
- Focused OPC assertions moved from 279 to 293, adding 14 assertions.
- Lane `phpPass` moved from `487` to `488`.
- Manifest mapped native checks moved from `960` to `961`.

## Dependency Closure

No new support component is needed. This slice reuses the accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, and `XmlHtmlDom` helpers.

This slice did not invoke Pandoc, Cabal, Haskell runners, Skylighting,
citeproc, BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`, `tar`, `lz4`,
external template engines, TeX/PDF engines, browser renderers, roff, Typst,
MathJax, KaTeX, online sanitizers, or online services.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
lookup, relationship XML parsing, XML NCName-style Id validation, URI target
decoding, target integrity preflight, package-part orphan/content-type
preflight, relationship-part source validation, external target policy, and
reachable relationship closure traversal.

It does not touch Markdown/HTML reader/writer, doctemplate, YAML metadata,
CSL/BibTeX, DOCX body parsing beyond the OPC preflight example, ODT, EPUB3,
PDF, math, legacy DOC/CFB, archive compression, syntax highlighting, charset,
or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep digital signature origin relationships, embedded package policy, external
relative-reference rewrite policy, full MIME parameter normalization, and any
higher-level DocxReader UI treatment of content-type diagnostics as separate
bounded slices.
