# Pandoc ODF OpenDocument Core Current Base

## Slice

Implemented bounded native ODT `text:notes-configuration` handoff in
`OdfReader`.

The shared upstream Pandoc checkout was not present at
`/home/claude/port-libs/.upstream-cache/pandoc` in this worker environment, so
this slice relied on the accepted Pandoc lane manifest/source-truth record and
ODF/OpenDocument fixture-backed PHP tests. No upstream Haskell runner or
external converter was executed.

## Behavior

- Parses `text:notes-configuration` children from `office:body/office:text`.
- Preserves footnote/endnote `noteClass`, citation style, citation body style,
  default style, master page, start value, numbering format/prefix/suffix,
  `style:num-letter-sync`, footnote placement, restart policy, and continuation
  notices.
- Exposes the data through `contentDeclarations.noteConfigurations`,
  `contentDeclarations.noteConfigurationsByClass`,
  `importReport.contentDeclarations`, and
  `importReport.content.noteConfigurationCount`.
- Attaches the matching note-class configuration to parsed `text:note` AST
  nodes without changing Markdown or WordPress footnote numbering.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 1383 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - Result: `odf open document handoff self-test ok`

## Delta

- Manifest mapped checks: `1880 -> 1881`.
- ODF/OpenDocument core cases: `11 -> 12`.
- ODF/OpenDocument focused assertions: `251 -> 293`.
- Lane `phpPass`: `1462 -> 1463`.

## Non-Overlap

This does not repeat accepted ODF text-tab normalization, paragraph blockquote
mapping, heading auto/source identifiers, table/list/section handling,
conditional/hidden text fields, bibliography marks, table captions, form
controls, tracked changes, MathML objects, embedded objects, encrypted media,
or existing footnote/endnote body rendering. The new surface is only ODT
note-configuration metadata handoff.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `OdfReader`
package/content parsing, content declaration metadata, note AST nodes,
Markdown/WordPress writers, in-memory ODT fixtures, and the WordPress ODF
OpenDocument handoff example.

Full upstream Pandoc/Haskell runner parity, external office validation,
rendered note renumbering/layout policy, LibreOffice refresh behavior,
recursive package conversion, and live-service/provider tests remain out of
scope.
