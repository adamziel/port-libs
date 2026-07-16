# pandoc-docx-openxml-core-current-base-20260605T100753Z

Accepted base: `f59109b94a1cc6b17840cbe8f0d7e8d59419aa53`

## Behavior

Added bounded native DOCX/OpenXML `word/settings.xml` support in `DocxReader`.

- The main document settings relationship now resolves through
  `word/_rels/document.xml.rels`, reports the settings part content type, and
  records missing/invalid settings diagnostics without shelling out to office
  tooling.
- Parsed settings metadata now includes revision-tracking flags, tracked-move
  and tracked-formatting policy, even/odd header policy, update-fields policy,
  default tab stop, decimal/list separators, proof state, zoom percent,
  document-protection settings, compatibility settings, and attached-template
  relationship provenance.
- Unsafe external attached-template targets remain visible in the import
  report with their external target kind/scheme and
  `external-target-unsafe-scheme` issue instead of being followed.
- `metadata['docxSettings']` and `importReport['settings']` expose the same
  bounded review packet so WordPress import queues can triage policy metadata
  without rendering or executing DOCX templates.

## Source Truth And Non-Overlap

This is a bounded WordprocessingML/OpenXML relationship and settings-part
behavior needed by DOCX body import and WordPress review packets. It extends
the accepted DOCX relationship/import-report path without changing body text
rendering, styles, numbering, table geometry, tracked-change rendering,
comments, bookmarks, field-code hyperlinks, section geometry, altChunk import,
media import, chart/diagram placeholders, embedded OLE/package placeholders,
ZIP/OPC package primitives, or relationship target preflight.

The rework-note check found no current Pandoc lane rework note:

- `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print | sort | tail -20`
- no output

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external
office tooling, browser renderer, online sanitizer, or online service was
executed.

## Verification

- Red-first focused test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 998 assertions, 1 failures`
  - Failure: the new settings case had no `metadata['docxSettings']` /
    `importReport['settings']` packet.
- Focused DOCX test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 1037 assertions, 0 failures`
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

Focused delta: one new DOCX/OpenXML PASS case and `+41` focused DOCX
assertions, raising `DocxReaderTest.php` from `34 PASS / 996 assertions` to
`35 PASS / 1037 assertions`.

## Dependency Closure

No new support component is required. This reuses the existing native
`ZipPackage`, OPC relationship graph, `DocxReader`, XML parser, import report,
and WordPress DOCX body handoff paths.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep glossary documents, style-inherited settings effects, tracked formatting
changes, footnote/endnote custom mark metadata, document variables, and fuller
upstream DocxReader parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
