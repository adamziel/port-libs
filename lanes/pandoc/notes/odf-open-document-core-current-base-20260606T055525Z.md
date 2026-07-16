# Pandoc ODF OpenDocument Table Caption Post-Process 2026-06-06

## Scope

Micro-slice: `pandoc-odf-open-document-core-current-base-20260606T055525Z`.

Accepted base: `cf7ad8dedfdead64d21e5ec92010b21088cacf79`.

This is a native PHP ODF/OpenDocument behavior slice. No Pandoc binary, Cabal
solver/build/test command, Haskell test binary, Word, LibreOffice, `zip` or
`unzip`, external converter, online service, live provider test, or office
tool was executed as progress.

## Source Truth

Static source truth is the pinned Pandoc upstream
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` ODT reader behavior in
`src/Text/Pandoc/Readers/ODT/ContentReader.hs`, where ContentReader applies a
post-processing pass that can attach caption paragraphs following tables.

The local support-library contract is bounded: preserve the format handoff
semantics in the existing Pandoc-like AST, Markdown writer, WordPress writer,
and import report without running Pandoc or an office suite.

## Implemented Behavior

`OdfReader` now post-processes parsed block lists and folds an immediately
following `odf-table-caption` paragraph into the preceding `table` node. The
attached caption preserves:

- plain caption text on the table node;
- caption inline children for Markdown and writer handoff;
- caption block children for table-geometry long-caption packets;
- source `text:p` element and `following-table` position provenance;
- ODF caption style name and WordPress data attributes;
- import-report `tableCaptionCount` for the folded caption.

Standalone ODT `Table`-style caption paragraphs that do not immediately follow
a table remain review `div` blocks, preserving the accepted behavior for loose
caption-style paragraphs.

## WordPress Handoff

The WordPress ODF open-document example now includes a protected review table
followed by a `Table`-style caption paragraph. The self-test checks that the
caption is attached to the table geometry review packet and renders as:

`<figcaption class="wp-element-caption odf-table-caption" data-odf-table-caption-source="following-paragraph" data-odf-table-caption-style-name="Table">...`

The earlier standalone caption paragraph remains a review div so import queues
can still flag loose caption text separately.

## Dependency Closure

No new support component is needed. This slice reuses:

- `OdfReader` content/style parsing;
- `TableGeometry` review packets;
- `MarkdownWriter` table caption output;
- `WordPressBlockWriter` figcaption output;
- in-process ODT package fixtures.

The upstream runner dependency blocker is unchanged: full parity still needs a
hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, Cabal
project/package files, and Haskell Tasty executable builds for `test-pandoc`
and `test-pandoc-lua-engine`.

## Non-Overlap

This patch only changes ODF table-caption post-processing. It deliberately
does not repeat accepted ODF text:tab normalization, paragraph blockquote
style mapping, heading auto identifiers, parent-relative frame image
normalization, standalone table-caption divs, table names/protection metadata,
table cell formulas, linked/protected sections, tracked changes, embedded
objects, or generated index behavior.

## Verification

- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1179 assertions, 0 failures`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1199 assertions, 0 failures`
  - Focused delta: `+1` PASS case / `+20` assertions
- Example smoke after implementation:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- PHP lint:
  `php -l lanes/pandoc/src/OdfReader.php`
  - `No syntax errors detected`
- PHP lint:
  `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `No syntax errors detected`
- PHP lint:
  `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - `No syntax errors detected`
- JSON validity:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - passed with no output
- Root harness: not run - isolated micro-slice.

## Next Task

Keep broader ODF post-processing parity, adjacent figure captions, automatic
caption numbering/list-of-table interactions, localized caption style names,
and full OpenDocument writer round-trip behavior as separate bounded slices.
