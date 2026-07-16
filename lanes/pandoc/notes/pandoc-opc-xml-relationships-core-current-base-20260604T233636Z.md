# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260604T233636Z`

Base accepted HEAD: `a5e387c4d1094f3921390a1c90f9966afea84bd2`

## Behavior Added

- Added bounded URI-reference handling for internal OPC relationship `Target`
  paths in `OpcPackagePath::resolveInternalTarget()`.
- Internal relationship targets now percent-decode path segments before package
  lookup, so DOCX media targets such as `media/hero%20image.PNG` and UTF-8
  octets resolve to the actual ZIP package part names.
- Malformed percent escapes, network-path/URI-authority internal targets such
  as `//example.test/media.png`, and percent-encoded slash, backslash, or NUL
  path bytes are rejected before graph preflight can treat them as package
  parts.
- Updated the WordPress DOCX OPC preflight smoke so its media inventory proves
  an escaped relationship target reaches the real package entry.

## Source Truth

- Pandoc upstream DOCX parsing reads `_rels/.rels`, locates the
  `officeDocument` relationship by `Type`, and uses its `Target` as the
  document part: <https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/Docx/Parse.hs>
- OPC relationship targets are URI references. Microsoft `PackageRelationship`
  documentation records that internal `TargetUri` values are relative
  references resolved relative to the package or source part, while external
  targets are indicated by `TargetMode`: <https://learn.microsoft.com/en-us/dotnet/api/system.io.packaging.packagerelationship.targetmode>

## Verification

- `php -l lanes/pandoc/src/OpcPackagePath.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 210 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `16 test files, 3,908 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.

Focused OPC assertions moved from `194` to `210`, adding 16 assertions across 2
new PASS cases. Pandoc lane status now records `407` PHP pass / `0` fail and
`871` mapped checks. Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationship`,
`OpcRelationshipGraph`, and `XmlHtmlDom` primitives. It does not invoke Pandoc,
Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines,
Haskell test binaries, bibliography tools, browser renderers, or online
services.

## Non-Overlap

This patch is additive on top of accepted ZIP/OPC package primitives,
content-type parsing, relationship XML parsing, XML NCName-style Id validation,
target integrity preflight, and reachable relationship closure traversal. It
does not edit root dashboard/progress files and does not touch Markdown/HTML
reader/writer, doctemplate, YAML metadata, CSL, DOCX body parsing, ODT, PDF,
math, legacy DOC/CFB, archive compression, syntax highlighting, charset, or
upstream-runner dependency-audit surfaces.

## Follow-Up

Keep package-wide content-type/relationship consistency audits, duplicate target
policy, and higher-level DocxReader import-report surfacing of URI-target
diagnostics as separate bounded slices.
