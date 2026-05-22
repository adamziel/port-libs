# pandoc WordPress Scenario

Document conversion kernel for Data Liberation imports and block-oriented output.

## Current Native Slice

Native Markdown block reader and WordPress block writer for headings,
paragraphs, Pandoc-style inline emphasis/strong/link/code spans, bullet lists,
ordered lists, nested lists, and definition lists. Code spans now preserve
list-marker-looking text such as `- x` and `#. x` inside imported list items.
Definition lists now cover Pandoc-style loose first definitions, lazy
continuation lines, blank-before-second definitions, and indented continuation
paragraphs, which keeps imported FAQ, glossary, and release-note metadata
grouped under the intended term.
The remaining upstream `Tests.Readers.Markdown` definition-list case is now
covered too: a definition list nested inside an HTML `<div>` becomes a `div`
AST node containing the parsed definition list.
Fenced code blocks map the upstream `test/command/indented-fences.md`
indentation-stripping behavior and render as WordPress code blocks. Block quotes
now map Pandoc's `test/testsuite.txt` block quote section, including quoted
paragraphs, nested quotes, ordered lists, and indented code inside a quote.
Indented code blocks from the `test/testsuite.txt` Code Blocks section now also
preserve blank lines, literal backslashes, and Pandoc's tab-expanded remaining
indentation, which matters for older Markdown exports that used tab-indented PHP
or template snippets instead of fenced code.
Horizontal rules from the `test/testsuite.txt` Code Blocks and Lists sections
now map to `horizontal_rule` AST nodes and WordPress separator blocks. This
keeps archive section breaks while avoiding the common import bug where a spaced
asterisk divider such as `*   *   *   *   *` becomes an empty-looking bullet
list.

## Scenario Fixture

- `fixtures/wordpress-import-markdown.md` is a small Data Liberation import
  sample with editorial emphasis, a source archive link, visible shortcode-like
  code spans, a reviewer quote, conversion steps, definition-list import notes,
  a div-wrapped glossary audit note, and a fenced PHP migration snippet.
- `examples/wordpress-import-markdown.php` converts that fixture to WordPress
  block comments and HTML without shelling out to pandoc.
- Definition-list support maps Pandoc `Tests.Readers.Markdown` glossary-style
  cases into `<dl>` output inside a WordPress HTML block, which is useful for
  imported FAQs, term lists, release-note metadata, and migration checklists.
- Div-wrapped definition lists preserve legacy import wrappers around glossary
  or FAQ notes as a WordPress HTML block instead of flattening the wrapper into
  text.
- Quote support maps imported reviewer notes, citations, and legacy editorial
  callouts into core WordPress quote blocks instead of flattening them into
  paragraphs.
- Tab-indented legacy snippets render as core WordPress code blocks with the
  remaining tab indentation expanded to spaces, matching Pandoc's native AST.
- Spaced-asterisk and underscore section dividers render as WordPress separator
  blocks, preserving migration-era article breaks without turning them into list
  markup.

## Next Task

Continue the bounded Lists section from `test/testsuite.txt` by mapping
tight/loose list paragraph shape and continuation-line semantics.
