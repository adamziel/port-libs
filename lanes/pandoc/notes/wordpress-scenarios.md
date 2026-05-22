# pandoc WordPress Scenario

Document conversion kernel for Data Liberation imports and block-oriented output.

## Current Native Slice

Native Markdown block reader and WordPress block writer for headings,
paragraphs, Pandoc-style inline emphasis/strong/link/code spans, bullet lists,
ordered lists, nested lists, and definition lists. Code spans now preserve
list-marker-looking text such as `- x` and `#. x` inside imported list items.
List parsing now also maps the bounded `test/testsuite.txt` loose-list and
continuation-line shape: blank-separated list items become paragraph-bearing
loose items, tab/space-indented continuation lines stay inside the current
item, and multi-paragraph ordered steps render as multiple paragraphs inside
one WordPress list item.
The same upstream Lists section now contributes fancy ordered-list markers:
parenthesized decimal starts, lower/upper roman numerals, upper/lower alphabetic
markers, and Pandoc autonumbering. The AST keeps marker style/delimiter
metadata and the WordPress writer preserves start values for nested ordered
lists.
Definition lists now cover Pandoc-style loose first definitions, lazy
continuation lines, blank-before-second definitions, and indented continuation
paragraphs, which keeps imported FAQ, glossary, and release-note metadata
grouped under the intended term.
The remaining upstream `Tests.Readers.Markdown` definition-list case is now
covered too: a definition list nested inside an HTML `<div>` becomes a `div`
AST node containing the parsed definition list.
The upstream `test/testsuite.txt` Definition Lists section is now represented
for multiple-block bodies and alternate `~` markers: emphasized terms remain
emphasized, additional indented paragraphs stay in the same definition, deeply
indented lines become code blocks, quoted continuation bodies stay block quotes,
and nested ordered review lists stay under the intended glossary term.
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
Raw HTML blocks from the `test/testsuite.txt` HTML Blocks section now preserve
WordPress import boundaries: nested `<div>` wrappers stay structural, raw
tables remain in a WordPress HTML block while Markdown inside table cells is
interpreted, HTML comments can carry migration audit markers, custom `<hr>`
tags stay raw instead of being normalized into core separators, and tab-indented
HTML snippets remain code blocks.
The upstream `test/testsuite.txt` Inline Markup section is now represented for
underscore emphasis/strong and triple-marker nesting: `_import note_` stays
emphasized, `__review flag__` stays strong, and `___urgent media cleanup___`
renders as nested strong emphasis in WordPress block HTML.
The remaining bounded Inline Markup script/deletion cases are also mapped:
`~~legacy cleanup~~` renders as deletion markup, `a^*draft*^` renders as a
superscript containing emphasis, and `H~2~O` renders as subscript text while
Pandoc's unescaped-space examples stay plain text.
The bounded Smart quotes, ellipses, dashes section is now mapped too: nested
single and double quote spans render as typographic quotes, contractions and
date possessives keep Pandoc's right-apostrophe behavior, quoted code and
one-line reference links stay semantic, `---` becomes an em dash, numeric `--`
ranges become en dashes, and `...` becomes an ellipsis.

## Scenario Fixture

- `fixtures/wordpress-import-markdown.md` is a small Data Liberation import
  sample with editorial emphasis, a source archive link, visible shortcode-like
  code spans, a reviewer quote, conversion steps with a multi-paragraph
  reviewer follow-up item, parenthesized source-ID steps with nested roman
  reviewer checkpoints, definition-list import notes, an alternate-marker source
  glossary with nested ordered review tasks, a div-wrapped glossary audit note,
  underscore-delimited reviewer emphasis, nested urgent cleanup emphasis,
  strikeout cleanup notes, superscript draft status, subscript chemical/media
  labels, smart import-editor quotes, apostrophes, ellipses, date-range en
  dashes, em-dash review notes, and a fenced PHP migration snippet.
- The fixture also includes a raw import table, an HTML migration audit comment,
  and a custom legacy divider to exercise WordPress HTML block output for
  imported raw HTML boundaries.
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
- Loose ordered-list support keeps a reviewer follow-up paragraph attached to
  the same conversion step instead of emitting a separate paragraph outside the
  list.
- Fancy ordered-list support keeps imported source-ID sequences and nested
  roman reviewer checkpoints grouped as ordered WordPress list markup with the
  correct `start` values.
- Alternate definition-marker support keeps older Pandoc-style `~` glossary
  notes and their nested ordered review tasks inside one WordPress HTML `<dl>`
  block.
- Tab-indented legacy snippets render as core WordPress code blocks with the
  remaining tab indentation expanded to spaces, matching Pandoc's native AST.
- Spaced-asterisk and underscore section dividers render as WordPress separator
  blocks, preserving migration-era article breaks without turning them into list
  markup.
- Raw HTML tables, comments, and custom dividers render inside WordPress HTML
  blocks without shelling out to Pandoc, preserving legacy import annotations
  and table markup that reviewers may need to inspect.
- Underscore emphasis and nested strong-emphasis render as normal WordPress
  inline HTML, preserving reviewer urgency markers from older Pandoc-compatible
  Markdown exports.
- Strikeout, superscript, and subscript render as normal WordPress inline HTML,
  preserving cleanup annotations and compact metadata labels in imported
  Markdown without shelling out to Pandoc.
- Smart quotes, apostrophes, dashes, and ellipses render as WordPress-safe
  inline text, preserving editor comments and import date ranges without
  shelling out to Pandoc.

## Next Task

Map a bounded `test/testsuite.txt` LaTeX inline math/raw TeX slice.
