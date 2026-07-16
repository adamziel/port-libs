# pandoc-docx-openxml-core-current-base-20260605T093633Z

Accepted base: `4437431c3daa84d1d9239449ed250b0f345e0269`

## Behavior

Added bounded native DOCX/OpenXML embedded object and embedded package
relationship handoff in `DocxReader`.

- `w:object` / `o:OLEObject` runs now resolve `r:id` through
  `word/_rels/document.xml.rels` for OpenXML `oleObject` and `package`
  relationship types.
- The body AST preserves each resolved object as a reviewer `span` placeholder
  with the relationship id/type, resolved target/target part, content type,
  external/missing status, byte count for embedded package parts, OLE metadata,
  and VML shape id/alt/style metadata.
- Missing internal embedded package targets stay visible as placeholders with a
  `missing-in-package` diagnostic instead of silently disappearing.
- `importReport()['embeddedObjects']` inventories reachable OLE/package
  relationships, including counts, package/OLE kind, bytes, usage count,
  placeholder descriptions, and issues.

## Source Truth And Non-Overlap

This is a bounded WordprocessingML/OpenXML relationship behavior needed by
DOCX body import and WordPress review packets. It extends the accepted DOCX
relationship/media/reporting path and the prior paragraph layout slice without
changing styles, numbering, table geometry, tracked changes, comments,
bookmarks, field-code hyperlinks, section geometry, altChunk import, chart or
diagram placeholders, custom XML, smart tags, content controls, ZIP/OPC
package primitives, or relationship preflight.

The rework-note check found no current Pandoc lane rework note:

- `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort`
- no output

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external
office tooling, browser renderer, online sanitizer, or online service was
executed.

## Verification

- Red-first focused test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 922 assertions, 1 failures`
  - Failure: the new embedded object/package case saw only the paragraph text
    node instead of the expected reviewer placeholder spans.
- Focused DOCX test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 996 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - `docx body handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
- JSON validation:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
- Whitespace:
  - `git diff --check -- lanes/pandoc`

Focused delta: one new DOCX/OpenXML PASS case and `+76` focused DOCX
assertions, raising `DocxReaderTest.php` from `33 PASS / 920 assertions` to
`34 PASS / 996 assertions`.

## Dependency Closure

No new support component is required. This reuses the existing native
`ZipPackage`, OPC relationship graph, `DocxReader`, `AstNode`,
`MarkdownWriter`, and `WordPressBlockWriter` span attribute paths.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep embedded OLE/package semantic extraction or embedded package expansion,
style-inherited paragraph layout metadata, table captions/descriptions,
drawing text extraction, tracked formatting changes, footnote/endnote custom
mark metadata, glossary/document settings, and fuller upstream DocxReader
parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
