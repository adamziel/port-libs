# pandoc Upstream Test Inventory

Inventory source: blob-filtered shallow clone at `.upstream-cache/pandoc`.

- Upstream commit: `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
- Main suite declared by `pandoc.cabal`: `test-suite test-pandoc`
- Runner shape: Haskell Tasty executable `test/test-pandoc.hs`
- License: GPL-2.0-or-later, with GPL-compatible exceptions documented in
  `COPYRIGHT`

## Counted Static Denominator

- `test/` files/artifacts: 1,974
- `test/Tests/**/*.hs` Haskell test modules: 62
- Reader test modules: 34
- Writer test modules: 22
- Shared/support test modules: 6
- `test/command` artifacts: 1,155
- `test/command/*.md` command fixture files: 1,064
- `.native` expected artifacts under `test/`: 252
- `test/testsuite.txt` top-level Markdown sections: 14
- `test/testsuite.native` rendered native AST lines: 2,238
- `test/testsuite.native` `BlockQuote` nodes in the full rendered suite: 7
- `test/testsuite.native` `CodeBlock` nodes in the full rendered suite: 11
- `test/testsuite.native` `BulletList`/`OrderedList` nodes in the full rendered
  suite: 36
- `test/testsuite.txt` Lists section slice inspected in this run: 163 Markdown
  lines from `# Lists` through the start of `# Definition Lists`
- `test/testsuite.txt` Definition Lists section slice inspected in this run: 93
  Markdown lines through the start of `# HTML Blocks`
- `test/testsuite.native` Definition Lists rendered native AST slice inspected
  in this run: 163 lines
- `test/testsuite.txt` HTML Blocks section slice inspected in this run: 102
  Markdown lines through the start of `# Inline Markup`
- `test/testsuite.native` HTML Blocks rendered native AST slice inspected in
  this run: 161 lines, including 22 `RawBlock` markers, 9 `Div` markers, and 4
  `CodeBlock` markers
- `test/testsuite.txt` Inline Markup section slice inspected in this run: 30
  Markdown lines through the start of `# Smart quotes, ellipses, dashes`
- `test/testsuite.native` Inline Markup rendered native AST slice inspected in
  this run: 168 lines, including 9 `Emph` markers, 6 `Strong` markers,
  1 `Strikeout` marker, 3 `Superscript` markers, and 3 `Subscript` markers
- `test/testsuite.txt` Smart quotes, ellipses, dashes section slice inspected
  in this run: 21 Markdown lines through the start of `# LaTeX`
- `test/testsuite.native` Smart quotes, ellipses, dashes rendered native AST
  slice inspected in this run: 154 lines, including 14 `Quoted` markers plus
  smart apostrophe, em-dash, en-dash, and ellipsis code points in `Str` nodes
- `test/testsuite.txt` LaTeX section slice inspected in this run: 30 Markdown
  lines through the start of `# Special Characters`
- `test/testsuite.native` LaTeX rendered native AST slice inspected in this
  run: 152 lines, including 6 `InlineMath` markers, 1 `DisplayMath` marker,
  1 TeX `RawInline`, and 1 TeX `RawBlock`
- `test/testsuite.txt` Special Characters section slice inspected in this run:
  54 Markdown lines through the start of `# Links`, including five Unicode
  bullet-list item lines, one HTML entity line, sixteen punctuation
  backslash-escape lines, and a dashed horizontal rule
- `test/testsuite.native` Special Characters rendered native AST slice
  inspected in this run: 86 lines, including one `BulletList`, 45 `Str`
  markers, 22 `Para` markers, and one `HorizontalRule`
- `test/testsuite.txt` Links section slice inspected in this run: 86 Markdown
  lines through the start of `# Images`, covering explicit links, reference
  links, ampersand URL/text cases, URI/email autolinks, and no-autolink code
  contexts
- `test/testsuite.native` Links rendered native AST slice inspected in this
  run: 290 lines, including 25 `Link` nodes plus the code-block and code-span
  cases where autolinks must not fire
- `test/testsuite.txt` Images section slice inspected in this run: 12 Markdown
  lines through the start of `# Footnotes`, covering a standalone collapsed
  reference image with title metadata and an inline image inside a paragraph
- `test/testsuite.native` Images rendered native AST slice inspected in this
  run: 48 lines, including 2 `Image` nodes and 1 `Figure` node
- `test/testsuite.txt` Footnotes section slice inspected in this run: 28
  Markdown lines through end of file, covering reference notes, inline notes,
  quote-contained notes, list-contained notes, multi-block definitions,
  whitespace-separated termination, and an invalid spaced footnote label
- `test/testsuite.native` Footnotes rendered native AST slice inspected in this
  run: 305 lines, including 4 `Note` nodes
- `test/markdown-reader-more.txt` inspected in this run: 366 Markdown lines in
  Pandoc's additional Markdown reader fixture.
- `test/markdown-reader-more.native` inspected in this run: 1,715 rendered
  native AST lines.
- `test/markdown-reader-more.txt` blank-reference and URL-space slice inspected
  in this run: 44 Markdown lines covering two reference definitions whose
  targets/titles live on following lines, four inline link destinations with
  spaces or multiline spaces, and three reference link destinations with spaces
  plus one parenthesized title.
- `test/markdown-reader-more.native` corresponding rendered AST slice inspected
  in this run: 100 lines showing two split reference-definition links and
  seven space-containing link destinations normalized with `%20`.
- `test/markdown-reader-more.txt` implicit-header-reference slice inspected in
  this run: upstream lines 169-189 cover shortcut, collapsed, and
  case-insensitive implicit references, an explicit reference definition that
  overrides an implicit heading reference, and explicit heading attributes.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 329-450 show Pandoc's duplicate
  generated heading id behavior (`my-header-1` after an earlier
  `my-header`), `#my-header` links for the implicit forms, `/foo` for the
  explicit override, and `#foobar` plus class/key metadata for the attributed
  heading.
- `test/markdown-reader-more.txt` backslash-newline and code-span slice
  inspected in this run: upstream lines 101-117 cover an explicit trailing
  backslash hard break, a code span ending in a literal backslash, a multiline
  code span, a longer backtick-delimited code span containing four literal
  backticks, and a blank-line-terminated unterminated code span.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 235-249 show `LineBreak`, three
  `Code` nodes with normalized code text, and two literal paragraph strings for
  the blank-line-terminated unterminated code span.
- `test/markdown-reader-more.txt` multilingual URL and numbered-example slice
  inspected in this run: upstream lines 119-135 cover one Unicode URI
  autolink, one inline link whose destination and title include Unicode source
  text, one Unicode e-mail autolink, two initial numbered examples, two inline
  references to example labels, and a later labeled example.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 249-295 show three `Link` nodes, an
  `OrderedList (1, Example, TwoParens)` with two items, inline paragraph text
  where `(@foo)` and `(@bar)` have become `(2)` and `(3)`, and a later
  `OrderedList (3, Example, TwoParens)` with one item.
- `test/markdown-reader-more.txt` line-block slice inspected in this run:
  upstream lines 191-201 cover one pipe-prefixed line block with four
  indentation levels, one empty line entry, and two continuation-line cases
  where indented non-pipe lines fold into the previous line.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 451-516 show one `LineBlock` with
  seven line entries, including nonbreaking-space indentation counts of 4, 8,
  12, and 2 before the visible text.
- `test/markdown-reader-more.txt` raw TeX environment and macro slices
  inspected in this run: upstream lines 20-37 cover Raw ConTeXt and Raw LaTeX
  environments, and lines 136-140 cover a `\newcommand` macro followed by math
  using the macro.
- `test/markdown-reader-more.native` corresponding rendered AST slices
  inspected in this run: upstream lines 61-94 show one `\placeformula
  \startformula` `RawBlock`, one paragraph ending with a `\stopformula`
  `RawInline`, one nested `\start[a2]`/`\stop[a2]` `RawBlock`, and one nested
  LaTeX `center`/`tikzpicture` `RawBlock`; upstream lines 296-300 show a
  `\newcommand{\tuple}[1]{\langle #1 \rangle}` `RawBlock` and later math
  expanded to `\langle x,y \rangle`.
- `test/markdown-reader-more.txt` rectangular grid-table slice inspected in
  this run: 74 mapped Markdown lines from the Grid Tables section cover the
  simple headed table, headless table, aligned headed table, aligned headless
  table, trailing-space table, East Asian width table, zero-width German and
  Persian text cases, and empty cells.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: 642 mapped native AST lines show Pandoc `Table`
  nodes with `ColWidth` values derived from grid widths divided by 72, default
  and right/left/center alignments, `TableHead []` for headless cases,
  `SoftBreak` entries inside multiline scalar cells, Unicode text cells, and
  empty `Cell ... []` bodies.
- `test/markdown-reader-more.txt` grid-table multiple-block cell case
  inspected in this run: upstream lines 252-261 cover a rectangular grid table
  whose cells contain Markdown headings, paragraph-separated text caused by an
  empty interior cell line, bullet-list items, and scalar multiline text.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 987-1087 show a headless `Table` whose
  first body row contains `Header` plus `Para` block children in each cell, and
  whose second body row contains two `Para` blocks, one `BulletList`, and one
  scalar `Plain` cell with `SoftBreak` entries.
- `test/markdown-reader-more.txt` remaining grid-table span cases inspected in
  this run: upstream lines 290-313 cover a row/column-span table plus a complex
  multi-row header table.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 1260-1492 show a header `Cell` with
  `ColSpan 2`, a body `Cell` with `RowSpan 3`, and a complex two-row
  `TableHead` whose `Location` header has `RowSpan 2` and whose temperature
  header has `ColSpan 3`.
- `test/markdown-reader-more.txt` post-grid reference-link edge slice inspected
  in this run: upstream lines 337-358 cover a backslash-containing link label,
  an unresolved reference-looking fallback pair, a shortcut reference followed
  by a citation marker, and an empty reference definition before ordinary
  paragraph text.
- `test/markdown-reader-more.native` corresponding rendered AST slice inspected
  in this run: upstream lines 1549-1649 show a `Link` whose label contains
  `Str "*"` plus `RawInline "\\a"`, bracketed fallback text retaining emphasized
  contents, a `Link` immediately followed by a `Cite`, and an empty-destination
  `Link` after the intervening `bar` paragraph.
- `test/markdown-reader-more.txt` wrapping/bracketed-span tail slice inspected
  in this run: upstream lines 360-366 cover one long bullet item ending in
  `2015.` and one bracketed span with `.class`, `#id`, and `key=val`
  attributes.
- `test/markdown-reader-more.native` corresponding rendered AST slice inspected
  in this run: upstream lines 1650-1715 show the heading id
  `wrapping-shouldnt-introduce-new-list-items`, one tight `BulletList` item
  whose `2015.` suffix remains plain text, and one `Span` containing nested
  `Emph` plus a `Link`.
- `test/pipe-tables.txt` pipe-table fixture inspected in this run: 82 Markdown
  lines covering 11 upstream pipe tables, including captioned, uncaptioned,
  headerless, side-less, one-column, no-body, relative-width, and tricky
  escaped-pipe/code-span cell cases
- `test/pipe-tables.native` pipe-table rendered native AST inspected in this
  run: 927 lines, including 11 `Table` nodes, 88 `Cell` nodes, two headerless
  `TableHead []` shapes, one `Code` node containing a literal pipe, and three
  relative-width `ColWidth` entries
- `test/tables.markdown` simple/multiline table fixture inspected in this run:
  76 Markdown lines covering seven gridless tables; all seven simple and
  multiline gridless table cases are now mapped
- `test/tables.native` rendered native AST inspected in this run: 964 lines,
  including seven `Table` nodes and two headerless `TableHead []` shapes
- `test/command/short-caption.md` command fixture inspected in this run: one
  LaTeX reader example whose native output is a `Table` with
  `Caption (Just [Str "short", Space, Str "caption"]) [Plain [...]]`, two
  left-aligned columns, no table head, and one body row.
- `test/command/table-with-cell-align.md` command fixture inspected in this
  run: 105 lines covering a DocBook `informaltable` reader example whose
  native output keeps per-cell `AlignCenter`, `AlignLeft`, `AlignRight`, and
  `AlignDefault` cell alignment while leaving table-level column alignments
  default.
- `test/command/table-with-column-span.md` command fixture inspected in this
  run: 385 lines covering a DocBook `informaltable` reader example with 16
  `colspec` entries, `ColWidth 6.25e-2`, strong emphasis inside spanned cells,
  and `namest`/`nameend` entries that become `ColSpan 8` cells.
- `test/command/rst-writer-gridtable-if-rowspans.md` command fixture inspected
  in this run: 246 lines covering Pandoc native table input rendered to RST
  grid tables. The native AST includes `RowSpan 2` cells in body, head, and
  foot sections; the bounded PHP slice maps those row-span and section shapes
  through DocBook `morerows`, `thead`, `tbody`, and `tfoot` input and
  WordPress `rowspan`/`tfoot` output.
- `test/command/nested-table-to-asciidoc-6942.md` command fixture inspected in
  this run: 82 lines covering HTML input rendered to AsciiDoc, including a
  two-level nested table that Pandoc renders as a nested table and a separate
  three-level case where the AsciiDoc writer warns because that target format
  only supports two table levels. The bounded PHP slice maps both the
  two-level nested-table AST shape and the full HTML document third-level case
  at the WordPress boundary; WordPress output preserves the third nested table
  rather than applying the AsciiDoc-specific downgrade.
- `test/html-reader.html` table section inspected in this run: 366 HTML lines
  from the upstream HTML reader fixture covering table head/body/foot sections,
  omitted section tags, row headers, colspan, rowspan, two tables with multiple
  `<tbody>` sections, plain tables without headers, and empty tables. This run
  used it as bounded reader context without claiming full HTML reader parity.
- `test/html-reader.native` table section inspected in this run: 1,393 native
  AST lines covering 18 `Table` nodes from the upstream HTML reader fixture.
  The inspected HTML slice contains 19 `<table` starts, 47 `<th` cells, 10
  `<thead` starts, 17 `<tbody` starts, 5 `<tfoot` starts, 20 native
  `TableBody` nodes, two native tables with multiple `TableBody` sections, one
  `Cell ... [ Para [ Str "2" ] ]` paragraph-bearing table cell in the second
  multiple-body case, one native `RowHeadColumns 1` body shape, and four native
  `TableBody` nodes with body-local head rows before ordinary body rows. The
  bounded PHP mapping now includes the two native colspan/rowspan table shapes,
  the attribute-carrying table shape, the two multiple-body table shapes, the
  paragraph-bearing cell from the second multiple-body case, the four
  body-local `TableBody` head-row cases, the four plain `Tables without
  Headers` body-only/body-omitted/empty-head/body-plus-foot shapes, plus the two
  empty table inputs omitted from the upstream native output.
- `test/html-reader.html` upstream Attributes table inspected in this run:
  lines 766-786 include table id metadata, `thead` class metadata, a head-row
  class, `tbody` class plus `data-part`, body-row `data-part`, practical cell
  attrs, `tfoot` class metadata, and a foot-row `bgcolor` marker.
- `test/html-reader.native` upstream Attributes table rendered AST inspected in
  this run: lines 3202-3272 show those fields as Pandoc native attributes on
  `Table`, `TableHead`, `Row`, `TableBody`, `TableFoot`, and `Cell` nodes.
- `test/html-reader.html` full-document head, intro, Headers, and Paragraphs
  slice inspected in this run: upstream lines 1-35 cover title/generator
  metadata, the title heading class, intro paragraph, early horizontal rules,
  generated heading identifiers, inline links/emphasis in headings, paragraphs
  immediately following headings with no blank line, a hard-wrapped paragraph
  whose middle sentence looks list-like, a literal bullet-looking paragraph,
  and a hard line break.
- `test/html-reader.native` full-document head, Headers, and Paragraphs rendered
  AST slice inspected in this run: upstream lines 1-230 show two `Meta` fields,
  ten early `Header` nodes including the title heading with class `title`, six
  early `Para` nodes, two `HorizontalRule` nodes before the hard-line-break
  case, one heading `Link`, two heading `Emph` shapes, and the `LineBreak`
  node in the final paragraph.
- `test/html-reader.html` paragraph and inline-quote slice inspected in this
  run: upstream lines 33-86 cover a paragraph hard line break and two `<q>`
  examples, one with a `cite` attribute and one without.
- `test/html-reader.native` paragraph and inline-quote rendered native AST
  slice inspected in this run: upstream lines 213-228 show `LineBreak` between
  text nodes, and lines 360-405 show two `Quoted DoubleQuote` nodes, including
  one citation-bearing `Span` child with the source URL preserved in native
  key-value attributes.
- `test/html-reader.html` inline style slice inspected in this run: upstream
  lines 323-325 cover one `font-variant: small-caps` span, `<u>` and `<ins>`
  underline inputs, and `<s>`, `<strike>`, and `<del>` strikeout inputs.
- `test/html-reader.native` inline style rendered native AST slice inspected in
  this run: upstream lines 922-958 show one `SmallCaps`, two `Underline`, and
  three `Strikeout` nodes for those HTML inputs.
- `test/html-reader.html` Code Blocks slice inspected in this run: upstream
  lines 88-102 cover two standalone `<pre><code>` blocks, one with blank lines
  and one with literal backslash escapes.
- `test/html-reader.native` Code Blocks rendered native AST slice inspected in
  this run: upstream lines 408-420 show those two inputs as `CodeBlock`
  nodes whose text removes the final closing-tag newline while preserving
  internal blank lines, four-space indentation, and literal `\$`, `\\`, `\>`,
  `\[`, and `\{` escapes.
- `test/html-reader.html` Block Quotes slice inspected in this run: upstream
  lines 36-83 cover eight `<blockquote>` containers, including a simple
  paragraph quote, a quote with `<pre><code>` and an ordered list, two nested
  sibling quotes, a box-style code quote, a list-only quote, and a nested quote
  inside another quote.
- `test/html-reader.native` Block Quotes rendered native AST slice inspected in
  this run: upstream lines 231-355 show eight `BlockQuote` nodes, two
  `CodeBlock` nodes inside quotes, and two `OrderedList` nodes inside quotes.
  The bounded PHP mapping now preserves the same quote/container shape and
  keeps HTML text-node apostrophes as straight HTML-reader text rather than
  applying Markdown smart punctuation inside imported HTML.
- `test/html-reader.html` top-level Lists slice inspected in this run:
  upstream lines 104-198 cover the `Lists` heading through the `List styles`
  cases, including six unordered tight/loose list examples, five ordered
  tight/loose/multiple-paragraph examples, and six empty ordered-list style
  metadata examples.
- `test/html-reader.native` top-level Lists rendered native AST slice
  inspected in this run: upstream lines 421-541 show six top-level
  `BulletList` nodes and 11 top-level `OrderedList` nodes. The six style cases
  map to DefaultStyle, LowerRoman via `type="i"`, LowerRoman via class,
  DefaultStyle for bare `style="lower-roman"`, and LowerRoman via
  `list-style` and `list-style-type` declarations.
- `test/html-reader.html` Nested/Tabs/Fancy list slice inspected in this run:
  upstream lines 199-302 cover the `Nested`, `Tabs and spaces`, and
  `Fancy list markers` sections immediately after the top-level list-style
  examples. The slice includes three HTML headings, nested `ul` levels, ordered
  lists with nested unordered children, paragraph-bearing list items, nested
  decimal/lower-roman/upper-alpha/upper-roman/lower-alpha ordered-list styles,
  and a nested default-style autonumbering shape.
- `test/html-reader.native` Nested/Tabs/Fancy list rendered native AST slice
  inspected in this run: upstream lines 542-764 show three `Header` nodes,
  seven `BulletList` nodes, and 11 `OrderedList` nodes. The bounded PHP mapping
  now preserves Pandoc's tight `Plain` shape for list items whose only block
  child is a nested list, keeps paragraph-bearing HTML list items loose, and
  preserves start/style metadata through the nested ordered-list chain.
- `test/html-reader.html` Definition slice inspected in this run: upstream
  lines 303-311 cover one `<dl>` with two term groups, including consecutive
  `<dt>` aliases (`Cello` and `Violoncello`) before a shared definition body.
- `test/html-reader.native` Definition rendered native AST slice inspected in
  this run: upstream lines 765-790 show one `DefinitionList`, two term groups,
  three definition bodies, and a `LineBreak` between the consecutive
  `Cello`/`Violoncello` terms. The bounded PHP mapping now preserves that term
  grouping and emits WordPress-safe `<dl>` output.
- `test/html-reader.html` initial Inline Markup slice inspected in this run:
  upstream lines 313-317 cover the `Inline Markup` heading, two emphasis nodes,
  two strong nodes, an implicitly closed paragraph with empty `<strong>` and
  `<em>` nodes, and an emphasized link paragraph immediately after it.
- `test/html-reader.native` initial Inline Markup rendered native AST slice
  inspected in this run: upstream lines 792-846 show the `inline-markup`
  header, two `Emph` nodes, two non-empty `Strong` nodes, empty `Strong []` and
  `Emph []` nodes, and an `Emph [ Link ... ]` shape. The bounded PHP mapping
  now preserves those nodes and handles the implicit paragraph close without
  swallowing the following emphasized-link paragraph.
- `test/html-reader.html` remaining Inline Markup nested/code slice inspected
  in this run: upstream lines 318-322 cover four nested
  `<strong><em>...</em></strong>` paragraphs plus one paragraph with five
  `<code>` spans containing `>`, `$`, `\`, `\$`, and `<html>`.
- `test/html-reader.native` remaining Inline Markup rendered native AST slice
  inspected in this run: upstream lines 847-921 show four nested
  `Strong [ Emph ... ]` paragraph shapes and five `Code` inline nodes. The
  bounded PHP mapping now preserves the nested strong/emphasis shape and code
  span literal text through WordPress output.
- `test/html-reader.html` Smart quotes, ellipses, dashes slice inspected in
  this run: upstream lines 326-336 cover two bare self-closing `<hr />`
  separators, the section heading, four straight quote/apostrophe paragraphs,
  one quoted HTML `<code>`/`<a>` paragraph, two dash/hyphen paragraphs, and one
  spaced ellipsis paragraph.
- `test/html-reader.native` Smart quotes, ellipses, dashes rendered native AST
  slice inspected in this run: upstream lines 961-1118 show two
  `HorizontalRule` nodes, one `Header`, and eight `Para` nodes. Unlike
  Pandoc's Markdown reader smart-punctuation section, the HTML reader keeps
  straight quotes, apostrophes, dash strings, numeric hyphen ranges, and
  ellipsis dots as literal `Str` text while preserving the quoted code/link
  span boundaries.
- `test/html-reader.html` LaTeX slice inspected in this run: upstream lines
  337-357 cover the `LaTeX` heading, nine TeX/math-looking list items, a
  "These shouldn't be math" paragraph, three not-math list items with
  `<code>` and `<em>` children, a LaTeX table-introduction paragraph, a
  one-line `\begin{tabular}` paragraph, and a self-closing `<hr />` separator.
- `test/html-reader.native` LaTeX rendered native AST slice inspected in this
  run: upstream lines 1119-1297 show the section as literal `Str` text, `Code`
  for the explicit HTML code spans, `Emph` for the explicit HTML emphasis
  spans, and a final `HorizontalRule`. Unlike Pandoc's Markdown reader LaTeX
  section, the HTML reader does not produce `Math` or TeX `RawInline` nodes for
  dollar-delimited or backslash-command source text.
- `test/html-reader.html` Special Characters slice inspected in this run:
  upstream lines 358-388 cover the `Special Characters` heading, one intro
  paragraph, five Unicode list items, five entity/comparison paragraphs,
  sixteen punctuation-token paragraphs, and a self-closing `<hr />` separator.
- `test/html-reader.native` Special Characters rendered native AST slice
  inspected in this run: upstream lines 1298-1385 show one `Header`, one
  `BulletList` with five `Plain` list items, 22 `Para` nodes, and one
  `HorizontalRule`. Unlike the Markdown-reader Special Characters section, the
  HTML reader gets already-decoded text from the HTML parser and does not treat
  `*`, `_`, `[`, `]`, `#`, or other punctuation tokens as Markdown syntax.
- `test/html-reader.html` Links slice inspected in this run: upstream lines
  389-430 cover the `Links` heading, explicit link paragraphs with href/title
  metadata, an empty href, reference-shaped link text that is already HTML,
  ampersand-bearing href/title/text cases, explicit autolink-looking anchors,
  link-looking code spans and code blocks, and mixed HTML flow lines where bare
  text is immediately followed by `<p>` or `<blockquote>`.
- `test/html-reader.native` Links rendered native AST slice inspected in this
  run: upstream lines 1386-1687 show four headers, 24 `Link` nodes, two
  link-free e-mail-text paragraphs, two code contexts where
  `<http://example.com/>` stays literal, one `BlockQuote`, one `BulletList`,
  and the closing `HorizontalRule`. The bounded PHP mapping now keeps the same
  HTML-reader path without invoking Markdown reference or autolink parsing.
- `test/html-reader.html` Images slice inspected in this run: upstream lines
  431-435 cover the `Images` heading, a source-credit paragraph, one
  standalone `<img>` paragraph with `src`, `title`, and `alt`, one inline
  `<img>` paragraph with `src` and `alt`, and a self-closing `<hr />`
  separator.
- `test/html-reader.native` Images rendered native AST slice inspected in this
  run: upstream lines 1688-1728 show one `Header`, two `Para` nodes with
  ordinary text, two `Image` nodes, and one closing `HorizontalRule`. The
  bounded PHP mapping keeps HTML `<img>` nodes on the HTML-reader path as
  image inline AST nodes instead of re-parsing them through Markdown image
  syntax.
- `test/tables/nordics.html5` fixture inspected in this run: 59 HTML lines
  from the upstream table writer artifacts, including caption inline emphasis,
  four `colgroup` widths, a `thead`, one `tbody`, one `tfoot`, row-header
  cells, `<br>` line breaks, and a superscript unit. The bounded PHP slice maps
  this structured HTML table shape into the native table AST and WordPress
  table output.
- `Tests.Readers.Markdown` definition-list cases: 8, all of which are now
  mapped by focused PHP tests
- `Tests.Readers.Markdown` smart apostrophe-after-math regression: 1 focused
  case, now mapped by a PHP test
- `Tests.Readers.Markdown` footnote edge cases: 3 focused cases, now mapped by
  PHP tests for whitespace-only indented separator termination, indented
  continuation after a blank line, and recursive references left literal inside
  note bodies
- `Tests.Readers.Markdown` inline-code attribute cases: 2 focused cases, now
  mapped by PHP tests for immediate attribute attachment and spaced
  attribute-looking text remaining literal
- `Tests.Readers.Markdown` autolink attribute cases: 2 focused cases, now
  mapped by PHP tests for immediate link attribute attachment and spaced
  attribute-looking text remaining literal
- `Tests.Readers.Markdown` no-links-inside-link-label cases: 3 focused cases,
  now mapped by PHP tests for autolinks, inline links, and bare URI-looking
  text staying literal inside ordinary link labels
- Focused `# Lists` fancy-marker mappings from `test/testsuite.txt`: 4 local
  checks covering parenthesized decimal starts, lower/upper roman numerals,
  upper/lower alphabetic markers, and Pandoc autonumbering
- Markdown fixture files under `test/`: 1,096
- Office/archive fixtures (`docx`, `odt`, `epub`, `pptx`, `xlsx`, `rtf`): 309
- HTML/XML/JATS fixtures: 29
- `pandoc-lua-engine/test/**/*.hs` modules: 5
- `pandoc-lua-engine/test/` artifacts: 54
- `benchmark/` files: 1
- `data/` files: 247

The dashboard denominator is now 2,028 inspected upstream test files/artifacts:
1,974 under `test/` plus all 54 tracked artifacts under
`pandoc-lua-engine/test/`. This replaces the earlier 1,979 count that only
included the five Lua-engine Haskell test modules.

## Runner Blocker

The full upstream suite was not executed in this run. Pandoc's `test-pandoc` and
`test-pandoc-lua-engine` suites must be built as Haskell Tasty executables from
a full checkout before they can run command, golden, HUnit, QuickCheck, and Lua
tests. `ghc` 9.10.3 and `cabal` 3.12.1.0 are now on PATH, while `stack` is not.
The current upstream cache is blob-filtered/no-checkout with mass working-tree
deletions, and a Cabal run would require hydrating the broad checkout plus
downloading and building Pandoc's dependency graph. The defensible denominator
used for this lane is therefore the cloned static `git ls-tree` inventory plus
targeted `git show` reads from the upstream object database, not upstream
runner parity.

## Native PHP Mapping Added

The current PHP slice maps a narrow part of `Tests.Readers.Markdown` semantics:

- ATX headings, including the `test/markdown-reader-more.txt` implicit header
  reference slice: generated identifiers, duplicate generated-id suffixes,
  shortcut/collapsed/case-insensitive implicit links, explicit heading
  attributes, and explicit reference definitions overriding implicit heading
  targets.
- Paragraph joining, including the `test/markdown-reader-more.txt`
  backslash-newline slice where an unescaped trailing backslash before a line
  boundary becomes a `LineBreak` node instead of a soft line wrap.
- Bullet and ordered list blocks
- Inline emphasis with `*text*`
- Inline strong with `**text**`
- Inline underscore emphasis/strong from the `# Inline Markup` section of
  `test/testsuite.txt`, cross-checked against `test/testsuite.native`: `_is
  this_` maps to `Emph`, `__is this__` maps to `Strong`, and triple `***`/`___`
  delimiters map to Pandoc's `Strong [Emph [...]]` shape.
- Inline strikeout, superscript, and subscript from the same `# Inline Markup`
  slice: `~~This is *strikeout*.~~` maps to a `Strikeout` node containing
  nested emphasis, `a^bc^d`/`a^*hello*^`/`a^hello\ there^` map to
  superscripts with escaped spaces normalized to non-breaking spaces,
  `H~2~O`/`H~23~O`/`H~many\ of\ them~O` map to subscripts, and the upstream
  unescaped-space examples remain plain text rather than script spans.
- Inline code spans, including the `test/markdown-reader-more.txt` cases where
  a trailing backslash is literal inside code, embedded newlines normalize to
  spaces, longer backtick delimiters permit literal backticks, and a blank line
  terminates an otherwise unterminated code span into ordinary paragraphs.
- Inline code attributes from `Tests.Readers.Markdown`: immediate
  `{.javascript}` after a closing backtick run attaches class metadata to the
  Code node, while a space before `{.haskell .special x="7"}` keeps that
  attribute-looking text literal.
- Autolink attributes from `Tests.Readers.Markdown`: immediate
  `{#i .j .z k=v}` after `<http://foo.bar>` attaches id/class/key metadata to
  the Link node and replaces the default `uri` class, while a space before the
  attribute spec keeps it as literal text after the autolink.
- Link-label recursion boundaries from `Tests.Readers.Markdown`: autolinks,
  nested inline links, and bare URI-looking text inside an ordinary link label
  remain literal label text, while non-link inline markup such as emphasis still
  parses inside the label.
- Multilingual URI/e-mail links from `test/markdown-reader-more.txt`: Unicode
  URI autolinks keep the URL as both text and destination, inline links keep
  Unicode destination text plus title metadata, and Unicode e-mail autolinks
  become `mailto:` links.
- Numbered examples from `test/markdown-reader-more.txt`: `(@)` and
  `(@label)` markers become Pandoc Example-style ordered lists with
  two-parentheses delimiters, and inline `(@label)` references render as the
  visible example numbers.
- Inline links with `[label](url)`
- Indented fenced code blocks from `test/command/indented-fences.md`, including
  Pandoc's opening-fence indentation stripping and both bare language and
  `{.class}` info strings
- Indented code blocks from the `# Code Blocks` section of `test/testsuite.txt`,
  cross-checked against `test/testsuite.native`: blank lines stay inside the
  block, a one-tab indent starts a block with no remaining indent, two leading
  tabs leave one expanded four-space indent in the code text, and literal
  backslashes are preserved rather than unescaped.
- Block quote cases from the `# Block Quotes` section of `test/testsuite.txt`,
  cross-checked against `test/testsuite.native`: simple quoted paragraphs,
  quote-contained indented code, ordered lists, nested block quotes, and the
  lazy-continuation case where `> 1.` stays inside a paragraph instead of
  starting a quote.
- Horizontal rules from the `# Code Blocks` and `# Lists` sections of
  `test/testsuite.txt`, cross-checked against `test/testsuite.native`: the
  underscore divider before the Lists section and the indented spaced-asterisk
  divider after `B. Williams` both become `HorizontalRule` nodes instead of
  paragraphs or bullet lists.
- Tight/loose list item shape and continuation paragraphs from the
  `# Lists` section of `test/testsuite.txt`, cross-checked against
  `test/testsuite.native`: blank lines between list items mark the list loose
  and turn item text into paragraph blocks, tab/space-indented continuation
  lines remain inside the current list item, multi-paragraph ordered items keep
  both paragraphs under the same item, and loose nested lists keep the parent
  item paragraph before the nested `BulletList`.
- Fancy ordered-list markers from the same `# Lists` section: `(2)` and `(3)`
  produce a decimal `OrderedList` starting at 2 with a two-parentheses
  delimiter, `iv.`/`v.` produce a lower-roman nested list starting at 4,
  `(A)`/`(B)` produce an upper-alpha nested list, `A.`/`I.`/`(6)`/`c)` produce
  the nested upper-alpha, upper-roman, decimal, and lower-alpha shape shown in
  `test/testsuite.native`, and `#.` produces Pandoc-style autonumbered lists.
- Definition-list cases from `Tests.Readers.Markdown`: no blank space,
  blank space before the first definition, lazy continuation lines, indented
  continuation paragraphs, blank space before the second definition, first-line
  marker at column zero, a list inside a definition, and the definition list
  nested inside an HTML div.
- Definition-list cases from the `# Definition Lists` section of
  `test/testsuite.txt`, cross-checked against `test/testsuite.native`: terms can
  contain emphasis, indented continuation blocks can add additional paragraphs,
  eight-space-indented continuation lines become code blocks, continuation
  block quotes remain block quotes inside the definition body, alternate `~`
  markers are accepted after a blank term line, and an indented ordered list
  stays nested inside the `orange` definition body.
- HTML-block cases from the `# HTML Blocks` section of `test/testsuite.txt`,
  cross-checked against `test/testsuite.native`: one-line and nested `<div>`
  containers become `div` AST nodes, raw `<table>` blocks preserve their HTML
  boundary while interpreting Markdown in cell text, `<script>` bodies are kept
  raw without interpreting Markdown, HTML comments become raw HTML blocks with
  tabs expanded as Pandoc does, trailing spaces are trimmed from raw comments and
  `<hr>` tags, and tab-indented HTML remains an indented code block.
- The two-level nested HTML table case from
  `test/command/nested-table-to-asciidoc-6942.md` is now represented for the
  WordPress table boundary: balanced nested `<table>` blocks are parsed into
  native table AST nodes inside `table_cell` children, while simple non-nested
  raw HTML tables continue to use the existing raw HTML block path.
- The same upstream command fixture's full HTML document with a third-level
  nested table is now represented too. Pandoc's AsciiDoc writer warns and
  flattens the third level because that target format only supports two table
  levels; the PHP WordPress writer records a separate target policy and
  preserves the third-level nested table HTML for reviewer inspection.
- Structured HTML table imports from `test/tables/nordics.html5` are now
  represented for the WordPress table boundary: tables with explicit
  `caption`, `colgroup`, `thead`, or `tfoot` parse into table AST nodes,
  caption inline emphasis is preserved, col widths become `ColWidth`-style
  fractions, table head/body/foot sections remain distinct, row-header cells
  stay marked as headers in the AST, `<br>` becomes a hard `linebreak`, and
  `<sup>`/`<sub>` inline content maps to script nodes. Simple non-structured
  raw HTML tables still use the raw HTML path so legacy import-review markup
  is not over-normalized.
- Bounded non-table HTML-reader paragraph cases from `test/html-reader.html`
  and `test/html-reader.native` are now represented: standalone HTML
  paragraphs parse through the native inline path, `<br />` becomes a
  `linebreak` node matching Pandoc's `LineBreak`, `<q>` becomes a double
  `quoted` node, and q `cite` metadata is preserved as a Pandoc-style `span`
  child. The WordPress writer emits `<br/>` for the hard break and preserves
  citation metadata on the rendered inline span.
- Bounded HTML-reader table cases from `test/html-reader.html` and
  `test/html-reader.native` are now represented: a first all-`th` row without
  explicit `<thead>`/`<tbody>` tags is inferred as `table_head`, bodies whose
  rows begin with `<th>` cells record `rowHeadColumns=1`, omitted `</thead>`,
  `</tbody>`, and `</tfoot>` end tags are normalized into distinct
  head/body/foot AST sections, no-header HTML tables with only `colspan`
  metadata parse through the native table AST, headed tables preserve
  `colspan`/`rowspan`, Pandoc-style table, section, row, and cell attributes
  are captured with `data-*` keys normalized to native-style key-value
  attributes, the two upstream multiple-`tbody` tables stay as distinct
  `table_body` AST nodes instead of being flattened, and the second
  multiple-body table's direct `<p>` cell becomes a paragraph block child
  rather than inline text. Four body-local `TableBody` head-row cases now keep
  leading all-`th` rows in `headRows` metadata before ordinary body rows,
  covering explicit tbody plus foot/details, omitted tbody after a promoted
  top-level header, explicit tbody-only body heads, and empty-thead body heads.
  The plain `Tables without Headers` body-only, tbody-omitted, empty-head, and
  explicit body-plus-foot shapes now parse as native table AST nodes too when
  the cells are plain scalar text. The WordPress writer now emits body
  row-header cells as `<th>` instead of flattening them to `<td>`, renders
  body-local head rows inside `<tbody>` before ordinary body rows, preserves
  table identity attributes, emits section and row attrs on `<thead>`, `<tbody>`,
  `<tfoot>`, and `<tr>`, carries practical cell attributes such as `abbr`,
  `valign`, `data-*`, and non-alignment `style` values, emits one `<tbody>` per
  `table_body` node, preserves paragraph cells as `<td><p>...</p></td>`, and
  emits headerless plain import grids as core table blocks. The upstream
  empty-table section is
  mapped too: the empty `<tbody>` table and the fully empty `<table></table>`
  input are consumed and omitted, matching `test/html-reader.native`.
- Smart-punctuation cases from the `# Smart quotes, ellipses, dashes` section
  of `test/testsuite.txt`, cross-checked against `test/testsuite.native`: nested
  single and double quotes become `quoted` AST nodes, apostrophes inside words
  normalize to Pandoc's right single quotation mark, quoted code spans remain
  code, quoted one-line reference links resolve through collected definitions,
  `---` becomes an em dash, numeric `--` ranges become en dashes, and `...`
  becomes an ellipsis while preserving a fourth trailing dot.
- LaTeX cases from the `# LaTeX` section of `test/testsuite.txt`,
  cross-checked against `test/testsuite.native`: raw TeX citation commands
  become `raw_tex` inline nodes, `$...$` spans become inline `math` nodes,
  `$$...$$` spans become display `math` nodes, `$p$-Tree` keeps the trailing
  word text outside math, currency-like dollar examples and escaped dollars stay
  non-math text, and `\begin{tabular}` through the matching `\end{tabular}`
  becomes a raw TeX block.
- The `Tests.Readers.Markdown` apostrophe-after-math regression is mapped:
  `$x$'s` parses as inline math followed by a right apostrophe text node, and
  the trailing possessive apostrophe in `systems' condition` normalizes to
  Pandoc's right single quotation mark.
- Special Characters cases from `test/testsuite.txt`, cross-checked against
  `test/testsuite.native`: Unicode list item text stays literal, `AT&amp;T`
  decodes to `AT&T` in the inline text node, literal `&`, `<`, and `>` examples
  stay text rather than HTML, Pandoc's punctuation backslash escapes collapse to
  their literal characters, and the dashed divider remains a `HorizontalRule`.
- Links cases from `test/testsuite.txt`, cross-checked against
  `test/testsuite.native`: explicit links support empty destinations,
  double/single-quoted titles, quote-containing titles, backslash escapes in
  link text, mailto URLs, and pointy-brace destinations; reference links support
  full, collapsed, and shortcut forms, nested brackets in link text, and
  definitions indented by up to three spaces while a four-space definition
  remains an indented code block; ampersands remain intact in URLs, link text,
  and titles; URI and email autolinks work in paragraphs, lists, and block
  quotes; autolinks do not fire inside code spans or indented code blocks.
- Images cases from `test/testsuite.txt`, cross-checked against
  `test/testsuite.native`: `![lalune][]` resolves through an up-to-three-space
  indented reference definition into a standalone `figure` block with image alt,
  source, title, and caption metadata, while `![movie](movie.jpg)` remains an
  inline `image` node inside its paragraph.
- Footnotes cases from `test/testsuite.txt`, cross-checked against
  `test/testsuite.native`: `[^1]` and `[^longnote]` resolve through collected
  footnote definitions into inline `note` nodes, invalid labels containing
  spaces remain literal text, inline notes parse nested emphasis, links, code
  spans containing `]`, and bracketed plain text, notes inside block quotes and
  list items stay attached to their containing block, and multi-block
  definitions preserve paragraphs plus indented code. The three
  `Tests.Readers.Markdown` footnote edge cases are also mapped: whitespace-only
  indented separators terminate a note before flush-left text, indented text
  after a separator remains in the note, and recursive `[^1]` references inside
  their own note body remain literal text.
- All 11 pipe-table cases from `test/pipe-tables.txt`, cross-checked against
  `test/pipe-tables.native`: default and aligned caption/no-caption tables keep
  caption text plus right/left/default/center alignment metadata, headerless
  tables omit the table head, the one-dash `|:-:|` header-less one-column form
  parses as centered, side-less rows split correctly without leading or
  trailing pipes, indented left-column values trim to their intended cells,
  one-column and no-body tables preserve their header/body shape, long
  delimiter rows produce relative column-width metadata, and tricky cells keep
  escaped `\|` pipes as text while `foo` plus a code span containing `bar|baz`
  remains one cell.
- All seven gridless table cases from `test/tables.markdown`, cross-checked
  against `test/tables.native`: captioned and uncaptioned simple tables infer
  right/left/center/default alignment from header spacing, the
  two-space-indented simple-table fixture still parses as a table rather than an
  indented code block, no-column-header simple tables use opening and closing
  delimiter rows with alignment inferred from the first body row, multiline
  header/body rows merge wrapped physical lines into `softbreak`-bearing cell
  content, multiline tables preserve Pandoc's 80-column `ColWidth` fractions,
  multiline captions can span continuation lines, and the no-header multiline
  table preserves the upstream final-column `AlignDefault` distinction while
  the headed multiline examples infer `AlignLeft`.
- Parsed table caption inline content is now mapped for pipe and simple tables:
  the AST keeps the legacy plain caption string but also stores parsed caption
  inline nodes, matching Pandoc's native `Caption ... [Plain [...]]` block
  shape observed in `test/tables.native`, `test/pipe-tables.native`, and the
  short-caption command fixture. WordPress figcaptions now render emphasis,
  links with titles, code spans, and smart punctuation instead of escaping
  Markdown markup as literal caption text.
- The optional short-caption shape from `test/command/short-caption.md` is now
  mapped for a narrow LaTeX table environment slice: `\caption[short
  caption]{long caption}` keeps the long caption as visible table caption
  content and stores the short caption separately on the table AST. The
  WordPress writer preserves that short label as `data-pandoc-short-caption`
  on the table figure while rendering the long caption in the figcaption.
- The DocBook structural-cell shapes from `test/command/table-with-cell-align.md`
  and `test/command/table-with-column-span.md` are now mapped for a narrow
  `informaltable` slice: the native PHP reader uses DOM parsing for bounded
  DocBook table fragments, keeps `colspec` widths, per-cell alignment,
  `namest`/`nameend` column spans, and strong emphasis inside cells. The
  WordPress writer emits core table HTML with escaped `style` and `colspan`
  attributes, so structural cells survive import without shelling out to Pandoc.
- The row-span table-section shape from
  `test/command/rst-writer-gridtable-if-rowspans.md` is now mapped through the
  same bounded table AST: DocBook `morerows="1"` becomes `rowspan=2`,
  `thead`/`tbody`/`tfoot` become `table_head`/`table_body`/`table_foot`, and
  the WordPress writer emits `<thead>`, `<tbody>`, `<tfoot>`, and `rowspan`
  attributes without shelling out to Pandoc.
- The inline style shape from `test/html-reader.html` is now mapped for a
  narrow HTML reader slice: `font-variant: small-caps` spans become
  `small_caps` AST nodes, `<u>` and `<ins>` become `underline`, and `<s>`,
  `<strike>`, and `<del>` become `strikeout`. The WordPress writer renders
  those as safe inline small-caps, underline, and deletion markup without
  invoking Pandoc.
- The standalone pre/code shape from the `test/html-reader.html` Code Blocks
  section is now mapped for a narrow HTML reader slice: `<pre><code>` becomes a
  native `code_block`, internal blank lines and indentation are preserved, the
  closing-tag newline is stripped like Pandoc's native output, and literal
  backslash escapes stay literal instead of being treated as Markdown escapes.
  WordPress output renders the node as a core code block and normalizes
  `language-*` classes for imported legacy snippets.
- The blockquote container shape from the `test/html-reader.html` Block Quotes
  section is now mapped for a narrow HTML reader slice: balanced
  `<blockquote>` blocks become native `blockquote` nodes, nested quotes stay
  nested, paragraph/code/list children stay as block children, ordered lists
  inside quotes stay on the native list path, and HTML text nodes are not
  passed through Markdown smart punctuation.
- The top-level list shape from the `test/html-reader.html` Lists section is
  now mapped for a narrow HTML reader slice: balanced `<ul>` and `<ol>` blocks
  become native list nodes, tight `<li>text</li>` items stay inline/plain-like,
  `<li><p>text</p></li>` items stay paragraph-like, multiple paragraphs remain
  attached to one ordered item, and `type`, class, `list-style`, and
  `list-style-type` metadata preserve ordered-list styles while the upstream
  bare `style="lower-roman"` case remains default. The WordPress writer emits
  safe `type` attributes for roman/alpha ordered lists.
- The next HTML-reader list shape from `test/html-reader.html` is now mapped
  for the `Nested`, `Tabs and spaces`, and `Fancy list markers` sections:
  top-level HTML headings become native heading nodes with generated or
  preserved Pandoc-style identifiers, nested-list-only items remain tight
  `Plain`-shaped list items, paragraph-wrapped items remain paragraph-shaped,
  and decimal/lower-roman/upper-alpha/upper-roman/lower-alpha ordered-list
  styles and starts survive through nested list chains. The WordPress writer
  emits heading anchors and nested ordered-list `start`/`type` attributes
  without invoking Pandoc.
- The HTML-reader definition-list shape from `test/html-reader.html` is now
  mapped for the `Definition` section: balanced `<dl>` blocks become native
  `definition_list` nodes, consecutive `<dt>` terms are joined with a
  Pandoc-style `linebreak`, multiple `<dd>` bodies stay attached to the same
  term, and the WordPress writer emits glossary/FAQ `<dl>` markup without
  invoking Pandoc.
- The HTML-reader Smart quotes, ellipses, dashes shape from
  `test/html-reader.html` is now mapped for a narrow HTML reader slice: bare
  self-closing `<hr />` separators become `horizontal_rule` nodes, the section
  heading gets the Pandoc-style identifier, straight source quotes and
  apostrophes remain literal text, quoted HTML code/link boundaries stay
  semantic, and dash/ellipsis strings are not converted through Markdown smart
  punctuation.
- The HTML-reader LaTeX shape from `test/html-reader.html` is now mapped for a
  narrow HTML reader slice: TeX commands, dollar-delimited math-looking text,
  and `\begin{tabular}` source in HTML text nodes stay literal text, while only
  explicit HTML inline tags such as `<code>` and `<em>` become semantic inline
  nodes. WordPress output preserves the source text without creating math spans
  or raw-TeX spans on the HTML-reader path.
- The HTML-reader Special Characters shape from `test/html-reader.html` is now
  mapped for a narrow HTML reader slice: Unicode list text survives unchanged,
  HTML entities decode once, comparison characters stay ordinary text,
  Markdown-sensitive punctuation tokens remain literal, and the final
  self-closing `<hr />` remains a `horizontal_rule` node.
- The HTML-reader Links shape from `test/html-reader.html` is now mapped for a
  narrow HTML reader slice: explicit anchors preserve href/title metadata,
  empty hrefs stay empty, reference-looking text stays literal, code contexts
  do not autolink, and mixed bare-text-plus-block-tag flow is split before
  block parsing so it stays on the native HTML-reader path.
- The HTML-reader Images shape from `test/html-reader.html` is now mapped for a
  narrow HTML reader slice: `<img>` becomes an `image` inline node with
  `src`/`title`/`alt` metadata, standalone image-only paragraphs retain
  Pandoc's paragraph-image AST shape, and inline image paragraphs keep the
  image between ordinary text nodes.
- The HTML-reader Footnotes shape from `test/html-reader.html` is now mapped
  for a narrow HTML reader slice: the 12-line upstream section and 249-line
  native AST slice preserve four footnote-style anchors as ordinary `Link`
  nodes, keep the invalid space-containing footnote marker as literal text,
  leave footnote-body paragraphs and the pre/code continuation as normal
  paragraph/code blocks, and move leading/trailing whitespace around emphasis
  wrappers outside the emphasis node like Pandoc's native output.
- The early full-document HTML-reader shape from `test/html-reader.html` is now
  mapped for a narrow HTML reader slice: complete `<html>` documents preserve
  title and generator metadata on the document node, body blocks are parsed
  through the native HTML-reader path, source heading classes survive on
  generated heading ids, heading links/emphasis remain inline nodes, and
  HTML-reader paragraphs keep list-marker-looking text literal instead of being
  re-parsed as Markdown lists.
- The post-grid Markdown reader shape from `test/markdown-reader-more.txt` is
  now mapped for a narrow link slice: upstream lines 315-335 and native AST
  lines 1493-1548 cover four entity-decoded link/title cases plus three
  parenthesized URL cases. Inline/reference link destinations decode
  `&uuml;`, titles decode `&ouml;`, URI/e-mail autolinks decode both href and
  visible label text, balanced parentheses stay inside inline URLs, escaped
  closing parentheses survive, and nested parenthesized reference destinations
  remain intact.
- The next post-grid Markdown reader shape from `test/markdown-reader-more.txt`
  is now mapped for a narrow reference-link edge slice: upstream lines 337-358
  and native AST lines 1549-1649 cover backslash/TeX content in link labels,
  unresolved reference-link fallback text, a shortcut reference followed by a
  citation marker, and empty reference definitions. The native PHP reader keeps
  escaped label punctuation and a bare `\a` TeX command inside the link label,
  falls back to bracketed emphasized text when the reference is undefined,
  preserves the `[@mapreduce]` marker as a citation inline adjacent to the
  resolved `Google` link, and leaves the paragraph after `[foo2]:` intact
  before emitting the later empty-destination shortcut link.
- The final `test/markdown-reader-more.txt` tail slice is now mapped too:
  upstream lines 360-366 and native AST lines 1650-1715 cover the wrapping
  regression and bracketed-span extension. The native PHP reader now generates
  Pandoc's apostrophe-free heading id for `shouldn't`, keeps the long bullet
  item as one tight list item instead of treating `2015.` as an ordered marker,
  and builds a `span` AST node preserving id, class, and key/value attributes
  around parsed emphasis and link children. The WordPress writer emits safe
  span id/class/data/title attrs for the fixture's migration-review marker.
- The `Tests.Readers.Markdown` inline-code attribute slice is now mapped too:
  immediate inline code attributes become AST id/class/key-value metadata, and
  the spaced attribute-looking form stays literal text instead of being parsed
  or smart-quoted. The WordPress writer emits safe inline `<code>` attributes
  for reviewer/source tokens.
- The `Tests.Readers.Markdown` autolink attribute slice is now mapped too:
  immediate autolink attributes become AST id/class/key-value metadata on the
  Link node, and the spaced attribute-looking form stays literal text. The
  WordPress writer emits safe link id/class/data/title attrs for reviewer
  source links while keeping ordinary URI/e-mail autolinks visually unchanged.
- The `Tests.Readers.Markdown` no-links-inside-link-label slice is now mapped
  too: `[<https://example.org>](url)`, `[[a](url2)](url)`, and
  `[https://example.org(](url)` each produce one outer Link whose label content
  stays literal text. The helper used for link and image labels keeps recursive
  link parsing disabled while preserving non-link inline markup such as
  emphasis.

The WordPress writer emits block comments and escaped HTML for the same AST
without calling the upstream `pandoc` binary.

Focused local verification on 2026-05-23: the pandoc-local test file passed
with 163 behavior tests, 1,680 assertions, and 0 failures after this slice. The
required repo-wide `php tools/run-tests.php` command was also run after this
slice and passed with 183 test files, 18,129 assertions, and 0 failures.
