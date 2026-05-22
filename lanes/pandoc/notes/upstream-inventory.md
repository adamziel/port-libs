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

- ATX headings
- Paragraph joining
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
- Inline code spans
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
  table identity attributes, carries practical cell attributes such as `abbr`,
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

The WordPress writer emits block comments and escaped HTML for the same AST
without calling the upstream `pandoc` binary.

Focused local verification on 2026-05-22: the pandoc-local test file passed with
119 tests, 921 assertions, and 0 failures. The required repo-wide
`php tools/run-tests.php` command was run after this slice and passed with
140 test files, 12,431 assertions, and 0 failures.
