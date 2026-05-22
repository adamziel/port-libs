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
The two-level nested table shape from
`test/command/nested-table-to-asciidoc-6942.md` now has a WordPress-specific
boundary as well: nested HTML tables become table AST nodes inside table cells
and render as nested table HTML in a core table block, while simple non-nested
raw HTML tables remain raw HTML for reviewer inspection.
The same upstream fixture's third-level nested table case is mapped separately
from Pandoc's AsciiDoc warning behavior: AsciiDoc downgrades because that target
only supports two table levels, but the WordPress writer preserves the full
third-level nested table HTML for migration reviewers.
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
The bounded LaTeX section is now mapped for import-safe preservation: raw TeX
citations render as escaped inline TeX spans, `$...$` and `$$...$$` math render
as WordPress-safe math spans, currency-like dollar examples and escaped dollars
stay plain text, and raw `tabular` blocks render as escaped TeX code blocks
instead of shelling out to Pandoc.
The bounded Special Characters section is now mapped for import-safe text
round-tripping: Unicode text stays literal, `AT&amp;T` decodes once before the
WordPress writer escapes output, literal comparison characters stay text, and
Pandoc's punctuation backslash escapes collapse to visible characters without
starting emphasis, links, headings, block quotes, or lists.
The bounded Links section is now mapped for import-safe link preservation:
explicit links keep empty destinations, pointy-brace destinations, and
double/single-quoted titles; reference links keep collapsed and shortcut
forms, nested brackets in link text, and up-to-three-space reference
definitions; ampersands stay intact in URLs, link text, and titles; URI and
email autolinks work inside paragraphs, lists, and quotes; and code spans or
indented code blocks keep angle-bracket URLs as literal code.
The bounded Images section is now mapped for import-safe media preservation:
standalone reference images become WordPress image blocks with caption/title
metadata, and inline image spans remain inside paragraph text with escaped alt
and title attributes.
The bounded Footnotes section is now mapped for import-safe note preservation:
reference footnotes are collected from anywhere in the document and rendered at
the reference point as `note` AST nodes, inline notes handle nested emphasis,
links, code spans containing `]`, and bracketed text, quote/list-contained
notes stay attached to their parent blocks, multi-block note definitions retain
paragraph and code-block bodies, and recursive note references inside note
bodies remain literal text instead of expanding forever.
The bounded `test/pipe-tables.txt` pipe-table fixture is now fully represented
for import-safe batch summaries: captioned and no-caption tables preserve their
captions and left/right/center/default alignment metadata, headerless,
header-less one-column, side-less, indented-left-column, one-column, and
no-body forms retain the expected table head/body shape, relative column-width
metadata stays on the AST, and cells containing escaped pipes or code-span pipes
stay in the intended cell. The WordPress writer renders these as core table
blocks with escaped inline emphasis, code spans, links, caption inline markup,
and optional `<colgroup>` width styles.
All seven gridless simple/multiline table cases from `test/tables.markdown` are
now mapped for older Markdown exports: captioned and uncaptioned simple tables
infer Pandoc-style alignment from header spacing, the two-space-indented table
shape is recognized before indented-code parsing, no-column-header simple
tables use opening and closing delimiter rows, multiline header/body rows keep
wrapped lines as soft breaks inside cells, 80-column `ColWidth` fractions render
as WordPress `<colgroup>` widths, and the headed-vs-headerless final-column
alignment distinction is preserved.
The upstream `test/command/short-caption.md` fixture is now represented for a
narrow LaTeX table slice: optional short captions are kept separately from the
visible long caption on the AST, and the WordPress table figure preserves the
short label in `data-pandoc-short-caption` for reviewer handoff, search, or
later export tooling.
The upstream `test/command/table-with-cell-align.md` and
`test/command/table-with-column-span.md` fixtures are now represented for a
narrow DocBook table slice: `informaltable` fragments keep colspec widths,
per-cell left/right/center/default alignment, strong emphasis inside cells, and
colspan metadata. The WordPress table writer preserves those as core table
markup with safe `style` and `colspan` attributes.
The upstream `test/command/rst-writer-gridtable-if-rowspans.md` row-span shape
is now represented as well: DocBook `morerows` imports become AST row spans,
table head/body/foot sections remain distinct, and WordPress table output keeps
`rowspan` plus `<tfoot>` markup for reviewer-audit tables.

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
  dashes, em-dash review notes, HTML entity text that must not double-escape,
  literal comparison characters, reference audit links with WordPress edit-link
  titles, autolinked audit URLs, importer email contacts, a standalone
  referenced release image, an inline thumbnail image, reference and inline
  footnotes for source audit trails, raw TeX citations, inline/display math
  notes, a raw TeX table source block, and a fenced PHP migration snippet.
- The fixture also includes a raw import table, an HTML migration audit comment,
  and a custom legacy divider to exercise WordPress HTML block output for
  imported raw HTML boundaries.
- The fixture now includes a nested legacy HTML audit table to exercise nested
  table-cell block children and WordPress nested table rendering.
- The fixture now also includes a third-level nested legacy HTML audit table,
  documenting the WordPress-specific policy to preserve deep review matrices
  rather than applying Pandoc's AsciiDoc-only two-level table downgrade.
- The fixture now includes pipe-table import metrics and relative-width review
  note summaries with aligned numeric counts, emphasized status text, code
  spans, a caption with a reference link and code span, and colgroup widths,
  exercising the native table AST and WordPress table block writer.
- The fixture now also includes legacy simple-table source totals with a
  caption, plus a wrapped multiline review-note table with colgroup widths,
  exercising gridless table imports from older Pandoc-compatible exports that
  do not use pipe-table syntax.
- The fixture now includes a short-caption LaTeX table import that keeps a
  compact reviewer label (`Batch 42`) while rendering the longer handoff
  caption in the WordPress table figcaption.
- `fixtures/wordpress-docbook-table.xml` is a bounded DocBook import-audit
  table with a spanned strong batch heading, aligned status cells, proportional
  colspec widths, spanned remediation summary cells, and a row-spanned media
  review window plus a footer reminder.
- `examples/wordpress-import-markdown.php` converts
  `fixtures/wordpress-import-markdown.md` to WordPress block comments and HTML
  without shelling out to pandoc.
- `examples/wordpress-docbook-table-spans.php` converts the DocBook table
  fixture into WordPress table block HTML without shelling out to pandoc.
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
- Nested legacy HTML audit tables render as nested table HTML inside the
  containing WordPress table block, preserving old reviewer matrices that used
  inner tables for grouped import status.
- Third-level nested legacy audit tables are preserved as nested WordPress
  table HTML, making the migration policy explicit for source documents that
  would trigger Pandoc's AsciiDoc depth warning.
- Underscore emphasis and nested strong-emphasis render as normal WordPress
  inline HTML, preserving reviewer urgency markers from older Pandoc-compatible
  Markdown exports.
- Strikeout, superscript, and subscript render as normal WordPress inline HTML,
  preserving cleanup annotations and compact metadata labels in imported
  Markdown without shelling out to Pandoc.
- Smart quotes, apostrophes, dashes, and ellipses render as WordPress-safe
  inline text, preserving editor comments and import date ranges without
  shelling out to Pandoc.
- Inline math, display math, raw TeX citation commands, and raw TeX table
  source render as escaped WordPress-safe markup, preserving technical import
  notes for later MathJax/KaTeX or citation-processing passes without shelling
  out to Pandoc.
- HTML entity text and comparison characters render as normal escaped
  WordPress paragraph text: `AT&amp;T` is decoded into the AST and emitted once
  as `AT&amp;T`, while `<` is emitted as `&lt;` instead of being treated as raw
  HTML.
- Reference audit links render as normal WordPress paragraph links with title
  attributes preserved, URI autolinks render as escaped clickable URLs, and
  importer email autolinks render as `mailto:` links without invoking Pandoc.
- Referenced import images render as core WordPress image blocks with preserved
  captions/titles, and inline thumbnail images render inside paragraph blocks
  without invoking Pandoc.
- Reference and inline import footnotes render as numbered note references plus
  one appended WordPress HTML endnotes block, preserving reviewer source trails,
  nested links, code spans, continuation paragraphs, and indented code snippets
  without invoking Pandoc.
- Pipe-table import metrics and relative-width review-note tables render as
  core WordPress table blocks with `<thead>`, `<tbody>`, aligned cells,
  optional `<colgroup>` widths, a figcaption where present, escaped emphasis,
  links, and code spans without invoking Pandoc.
- Legacy simple-table source totals render as core WordPress table blocks with
  inferred alignment styles and captions without invoking Pandoc.
- Wrapped multiline review tables render as core WordPress table blocks with
  softbreak newlines inside cells, inferred alignment styles, captions, and
  colgroup widths without invoking Pandoc.
- Short-caption LaTeX tables render as core WordPress table blocks with
  alignment styles, visible long captions, and preserved short-caption metadata
  without invoking Pandoc.
- DocBook import-audit tables render as core WordPress table blocks with
  colgroup widths, per-cell alignment, strong inline cell content, preserved
  `colspan`/`rowspan` structural metadata, and table footers without invoking
  Pandoc.

## Next Task

Map a bounded HTML reader table caption/thead/tfoot fixture from upstream
`Tests.Readers.HTML` or command goldens so full HTML table imports preserve
captions and section structure before broader HTML reader work.
