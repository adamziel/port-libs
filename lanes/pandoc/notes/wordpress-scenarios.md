# pandoc WordPress Scenario

Document conversion kernel for Data Liberation imports and block-oriented output.

## Current Native Slice

Native Markdown block reader and WordPress block writer for headings,
paragraphs, Pandoc-style inline emphasis/strong/link/code spans, bullet lists,
ordered lists, nested lists, and definition lists. Code spans now preserve
list-marker-looking text such as `- x` and `#. x` inside imported list items.
Pandoc title-block metadata is now available to WordPress import orchestration:
a leading `%` title block is consumed before body parsing, multiline titles
keep a metadata soft break for exact upstream shape, and semicolon or
line-separated authors are exposed as individual author entries that an import
pipeline can map to post title and review/byline metadata without rendering
the title block as stray body paragraphs.
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
The bounded `test/command/tasklist.md` HTML examples are now represented too:
Markdown review checkboxes such as `- [ ]` and `- [x]` become task metadata on
list items, all-task bullet lists render with `class="task-list"`, mixed
task/plain lists stay ordinary lists with checkbox labels only on the task
items, ordered task items keep labels, and loose task items preserve later
paragraphs outside the checkbox label.
The adjacent `markdown-reader-more` consecutive-list boundary is now
represented too: a review handoff can place bullet, decimal, and
one-space-indented lower-alpha queues next to each other, and the WordPress
writer emits separate `<ul>`, decimal `<ol>`, and `type="a"` `<ol>` blocks
instead of nesting the alpha queue under the final decimal item.
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
Structured HTML table imports from `test/tables/nordics.html5` now use the
native table AST when an HTML table exposes `caption`, `colgroup`, `thead`, or
`tfoot` boundaries. This lets WordPress imports preserve caption inline
emphasis, explicit column widths, head/body/foot sections, row-header cells,
soft line breaks, and superscript units while keeping plain non-structured raw
tables on the existing reviewer-inspection HTML path.
Bounded HTML-reader table cases from `test/html-reader.html` now cover inferred
header rows and omitted section end tags: tables whose first row is all `<th>`
cells become WordPress tables with a real `<thead>`, body rows that start with
`<th>` cells keep `rowHeadColumns=1` in the AST and render those cells as
`<th>`, and omitted `</thead>`, `</tbody>`, and `</tfoot>` tags are normalized
into explicit WordPress table sections.
The next HTML-reader table slice now covers upstream colspan/rowspan and
attribute-carrying cases: no-header `colspan` tables parse as native table
nodes instead of raw HTML, headed tables keep `colspan`/`rowspan` metadata, and
Pandoc-style table/section/row/cell attrs are captured in the AST. WordPress
table output preserves table identity attrs and practical cell attrs such as
`abbr`, `valign`, `data-*`, and non-alignment `style` values. The writer now
also emits section and row attrs from the upstream Attributes table, so source
batch classes, `data-part` markers, and foot-row review color markers survive
in WordPress table markup.
The upstream empty-table case is now mapped as well: legacy HTML table shells
with no cells are consumed and omitted instead of becoming empty WordPress
table blocks or raw HTML review blocks.
The upstream multiple-`tbody` HTML-reader cases are now mapped too: segmented
legacy tables keep each body group as a separate `table_body` AST node and the
WordPress writer emits one `<tbody>` per group instead of flattening review
batches into a single body.
The second upstream multiple-`tbody` case also keeps block-level paragraph
content inside a table cell: a direct `<p>` cell becomes a paragraph block
child, so WordPress emits `<td><p>...</p></td>` instead of flattening the cell
to inline text.
The plain `Tables without Headers` cases from `test/html-reader.html` are now
bounded too: td-only body tables, omitted-`tbody` tables, empty-head tables, and
explicit body-plus-foot tables become native headerless table blocks when cell
content is plain scalar text, while Markdown-looking legacy review tables stay
on the raw HTML path for reviewer inspection.
The remaining bounded table-body header-row shapes from `test/html-reader.html`
are now represented as well: leading all-`th` rows inside a `tbody` are kept as
body-local table head rows instead of being flattened into ordinary body rows or
promoted to a top-level `thead`. WordPress output preserves those rows inside
the same `tbody` before the ordinary review rows.
The next bounded non-table HTML-reader paragraph slice is represented too:
standalone HTML paragraphs can now carry Pandoc-style hard line breaks and
inline `<q>` quote semantics through the native AST. Citation metadata from
`<q cite="...">` is kept on a span child and rendered into WordPress-safe inline
HTML, so imported review quotes keep their source URL without invoking Pandoc.
The next HTML-reader inline style slice is now represented as well:
`font-variant: small-caps` spans, `<u>`, `<ins>`, `<s>`, `<strike>`, and
`<del>` map to native inline nodes before WordPress output. This keeps
source-glossary labels, underlined reviewer notes, inserted text, and deleted
legacy-caption markers semantic instead of flattening them to plain text.
The next HTML-reader code-block slice is now represented too: standalone
`<pre><code>` blocks from legacy HTML exports become native `code_block` nodes
instead of paragraphs or raw HTML. Blank lines, indentation, and literal
backslash escapes remain intact, and `language-*` classes render as WordPress
code block language classes for reviewer-friendly migration snippets.
The bounded HTML-reader blockquote container slice is now represented as well:
balanced `<blockquote>` blocks become native quote nodes, nested quotes remain
nested, code blocks and ordered lists inside quotes stay as block children, and
HTML text inside those quote containers keeps HTML-reader apostrophes rather
than receiving Markdown smart punctuation.
The bounded HTML-reader top-level list slice is now represented too: imported
`<ul>` and `<ol>` blocks become native list nodes, tight list items stay inline,
paragraph-wrapped list items stay paragraph-wrapped, multi-paragraph ordered
items stay attached to one item, and ordered-list `type`, class, and
`list-style` metadata render as safe WordPress ordered-list `type` attributes.
The next HTML-reader nested-list slice is now represented as well: HTML
headings around imported list sections keep generated or explicit anchors,
nested `<ul>` audit checklists stay tight when they only contain text plus a
nested list, paragraph-bearing source queues stay loose, and nested decimal,
roman, and alphabetic ordered-list styles render with WordPress-safe
`start`/`type` attributes.
The initial HTML-reader Inline Markup slice is now represented too: ordinary
HTML `<em>` and `<strong>` spans stay semantic, empty strong/emphasis markers
are preserved as empty inline nodes, emphasized links stay nested under the
emphasis node, and the upstream implicit paragraph close before a following
`<p>` no longer swallows the next paragraph.
The remaining bounded HTML-reader Inline Markup nested/code slice is now
represented too: nested `<strong><em>...</em></strong>` source emphasis stays
nested in the AST and WordPress output, and HTML `<code>` spans preserve
literal reviewer/source tokens such as `>`, `$`, `\`, `\$`, and `<html>`
without becoming raw HTML or Markdown code-span re-parses.
The bounded HTML-reader Smart quotes, ellipses, dashes slice is now represented
too: bare self-closing `<hr />` separators become WordPress separator blocks on
the HTML-reader path, while straight quotes, source apostrophes, quoted
HTML code/link punctuation, dash strings, numeric hyphen ranges, and spaced
ellipsis dots stay literal instead of receiving Markdown smart-punctuation
rewrites.
The bounded HTML-reader LaTeX slice is now represented too: source TeX commands,
dollar-delimited math-looking strings, and one-line tabular fragments inside
HTML text stay literal on the HTML-reader path, while explicit HTML `<code>` and
`<em>` markup remains semantic. This keeps legacy source snippets reviewable
without incorrectly turning imported HTML into Markdown math or raw TeX spans.
The bounded HTML-reader Special Characters slice is now represented too:
Unicode list text, decoded entities, comparison punctuation, and
Markdown-sensitive punctuation tokens from imported HTML stay literal on the
HTML-reader path. This prevents legacy source snippets like `*`, `_`, `[`, `]`,
`#`, or comparison operators from turning into Markdown markup while still
escaping them safely for WordPress output.
The bounded HTML-reader Links slice is now represented too: explicit HTML
anchors preserve href/title metadata, empty links remain empty placeholders,
reference-looking text stays literal, and code contexts do not autolink.
The bounded HTML-reader Images slice is now represented too: HTML `<img>` nodes
become native image inline nodes with source/title/alt metadata, standalone
image-only paragraphs keep Pandoc's paragraph-image AST shape, and WordPress
output promotes those standalone images into image blocks while preserving
inline images inside paragraph copy.
The bounded HTML-reader Footnotes slice is now represented too:
footnote-looking HTML anchors remain ordinary `link` nodes, note/back-reference
paragraphs and pre/code continuation blocks stay as normal blocks, invalid
space-containing footnote markers remain literal text, and leading/trailing
spaces around HTML emphasis wrappers move outside the emphasis node to match
Pandoc's native AST shape.
The bounded early HTML-reader full-document slice is now represented too:
complete `<html>` exports keep title/generator metadata on the document AST,
the source title heading keeps its generated id and `class="title"` marker in
WordPress heading output, heading links/emphasis stay semantic, and
HTML-reader paragraphs keep `*` list-marker-looking text literal instead of
falling back through Markdown parsing.
The upstream `test/testsuite.txt` Inline Markup section is now represented for
underscore emphasis/strong and triple-marker nesting: `_import note_` stays
emphasized, `__review flag__` stays strong, and `___urgent media cleanup___`
renders as nested strong emphasis in WordPress block HTML.
The adjacent `Tests.Readers.Markdown` intraword underscore and raw-LaTeX URL
guard cases are now represented too: filename-style reviewer markers such as
`_foot_ball_` preserve the inner underscore inside one emphasized span, while
an incomplete pasted `\begin` source command remains literal text instead of
becoming raw TeX.
The adjacent `Tests.Readers.Markdown` emph-with-strong delimiter cases are now
represented too: reviewer notes like `*x **xx** x*` and `***a**b **c**d*`
render as outer emphasis containing nested strong spans, matching Pandoc's
reader boundary instead of splitting the paragraph at the first inner `**`
delimiter run.
The adjacent alternating emph/strong softbreak case is now represented too:
multi-line reviewer notes keep the physical Markdown paragraph line break as a
softbreak between repeated emphasis and strong-emphasis runs, so WordPress
handoff HTML preserves reviewer line boundaries without splitting the paragraph.
Native Markdown reviewer handoff examples now also cover attributed fenced code
block output: a WP-CLI review snippet can carry stable id/class/data-source
metadata in Pandoc Markdown while preserving a literal nested backtick fence in
the code body. Unattributed code blocks continue to use indented Markdown code,
which keeps legacy plain snippets unchanged.
The rebased Markdown writer Space/SoftBreak/LineBreak slice is now represented
on top of the accepted line-block evidence: reviewer handoff AST packets keep
intentional source spaces, soft paragraph line boundaries, and Pandoc hard-break
markers. The additive rework also covers the same constructors inside nested
emphasis and strong spans, so reviewer notes do not lose line-boundary semantics
when migration comments are styled.
The priority-rework-20260525T080030Z pass adds blockquote coverage for the same
Markdown writer constructors: quoted reviewer packets preserve explicit spaces,
soft reviewer line boundaries, and hard break markers while every emitted
physical line remains inside the quoted handoff context.
Native Markdown reviewer handoff examples now also cover explicit Pandoc
`Space`, `SoftBreak`, and `LineBreak` inline writer nodes: direct AST handoff
packets keep intentional single-word spacing, soft reviewer line boundaries,
and hard line breaks as Pandoc Markdown instead of dropping the explicit space
node or flattening the break markers.
The remaining bounded Inline Markup script/deletion cases are also mapped:
`~~legacy cleanup~~` renders as deletion markup, `a^*draft*^` renders as a
superscript containing emphasis, and `H~2~O` renders as subscript text while
Pandoc's unescaped-space examples stay plain text.
The adjacent MultiMarkdown short script cases are represented too: compact
reviewer annotations such as `O~2` and `x^2` render as subscript/superscript
when followed by spaces, punctuation, or emphasis, while no-nesting forms keep
the marker literal before ordinary emphasis.
The adjacent citation boundary cases are represented too: reviewer notes can
preserve bare Pandoc citations such as `@cita [review-only note]` while still
keeping following footnotes, inline links, reference links, shortcut reference
links, and implicit header links separate when those brackets are real links.
The adjacent figure attribute case is represented too: immediate image
attributes keep `latex-placement` on the standalone figure and use `alt` as the
image alt override without replacing the reviewer-visible caption.
The bounded Smart quotes, ellipses, dashes section is now mapped too: nested
single and double quote spans render as typographic quotes, contractions and
date possessives keep Pandoc's right-apostrophe behavior, quoted code and
one-line reference links stay semantic, `---` becomes an em dash, numeric `--`
ranges become en dashes, and `...` becomes an ellipsis.
The adjacent smart-punctuation unclosed quote case is now represented too:
bold reviewer notes such as `**this should "be bold**` stay strong while the
unmatched opening quote becomes a left double quote in WordPress output.
The adjacent inline-note quote cases from `Tests.Readers.Markdown` are now
represented too: reviewer text such as `'a^['source quote'.] c.'` and
`"a^["review quote".] c."` keeps the outer quote open across the inline note,
while the note body parses its own nested smart quote. WordPress output keeps
the reviewer sentence quoted and emits the note bodies as normal endnotes.
The remaining `Tests.Readers.Markdown` smart-punctuation edge cases are now
represented too: quoted leading ellipses render as smart quoted ellipsis text,
apostrophes before an emphasized French helper phrase stay right apostrophes
instead of opening quotes, and French guillemet-adjacent apostrophes survive in
reviewer notes with Unicode-aware word-boundary handling.
The bounded LaTeX section is now mapped for import-safe preservation: raw TeX
citations render as escaped inline TeX spans, `$...$` and `$$...$$` math render
as WordPress-safe math spans, currency-like dollar examples and escaped dollars
stay plain text, and raw `tabular` blocks render as escaped TeX code blocks
instead of shelling out to Pandoc.
The adjacent `markdown-reader-more` `$ in math` slice is now represented too:
TeX text-group dollars inside `\text{the $n$th root of $y$}` stay inside one
math span, so reviewer formulas do not split into multiple inline math nodes
or stray paragraph text during WordPress handoff.
The adjacent `markdown-reader-more` raw-HTML-before-header and commented-list
slice is now represented too: empty source anchors immediately before imported
headings stay as raw inline HTML boundaries, trailing-space horizontal rules
stay separators, and commented-out list markers remain attached to list-item
text instead of ending the review checklist.
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
The `test/markdown-reader-more.txt` URL-space cases are now represented too:
reference definitions may put the URL and title on following lines, and bare
link destinations with spaces are collapsed and percent-encoded as `%20` while
keeping trailing quoted or parenthesized titles attached.
The same upstream fixture's implicit header reference cases are now represented
too: Markdown headings generate Pandoc-style anchors, duplicate generated ids
receive suffixes, shortcut/collapsed/case-insensitive references resolve to the
first matching heading, explicit `{#id .class key="val"}` attributes are kept on
the heading AST, and explicit reference definitions override implicit heading
targets.
The mid-fixture case-insensitive reference and curly-quote literal cases are
represented too: reviewer shortcuts such as `[FUM]` resolve to `[fum]: /fum`,
while pasted curly quote glyphs stay literal WordPress text rather than being
reinterpreted as Markdown smart quote delimiters.
The adjacent `test/markdown-reader-more.txt` backslash-newline and code-span
cases are now represented too: an explicit trailing backslash before a newline
becomes a hard `linebreak` node, code spans preserve literal trailing
backslashes, multiline code spans normalize their internal newline to a single
space, longer backtick delimiters can contain literal backtick runs, and blank
lines terminate an otherwise unterminated code span as ordinary paragraph text.
The WordPress fixture uses that path for reviewer handoff text that needs a
visible `<br/>` plus a normalized inline source token.
The focused `Tests.Readers.Markdown` inline-code attribute cases are now
represented too: immediate attributes attach to code nodes, while spaced
attribute-looking text remains literal. The WordPress fixture uses this path for
reviewer/source tokens such as `wp_enqueue_script` that need stable id, class,
data, and title metadata without shelling out to Pandoc.
The focused `Tests.Readers.Markdown` autolink attribute cases are now
represented too: immediate attributes attach to autolink nodes, while spaced
attribute-looking text remains literal. The WordPress fixture uses this path for
reviewer source links that need stable id, class, data, and title metadata
without changing ordinary autolink markup.
The focused `Tests.Readers.Markdown` bare URI autolink extension cases are now
represented too: all 41 upstream `bareLinkTests` cases now have local PHP
coverage. Plain http(s), DOI, Git, file, and mailto source URLs become links,
trailing sentence punctuation remains outside the anchor, balanced parentheses
remain inside the destination, uppercase schemes are accepted, bracketed path
text keeps a safe percent-encoded destination, raw HTML anchors pass through
without nested autolinking, and Greek, long encoded, port, tilde, `%20`, and
at-sign path variants stay intact. The WordPress fixture uses this path for
legacy import notes where reviewers pasted source URLs without angle brackets
or Markdown link syntax.
The focused `Tests.Readers.Markdown` no-links-inside-link-label cases are now
represented too: autolink-looking source URLs, nested Markdown link syntax, and
bare URI-looking text remain literal inside the outer reviewer link label. The
WordPress fixture uses this path when import notes need the visible source
notation to stay reviewable without producing nested anchors.
The focused `Tests.Readers.Markdown` raw HTML regression cases are now
represented too: a block-start `<del>test</del>` becomes a raw-open, plain,
raw-close block sequence, invalid tags stay literal, technically invalid
comments stay raw HTML, and split angle-bracket text stays in separate
paragraphs. The WordPress fixture uses this path for legacy raw deletion
boundaries that should not be flattened into visible tag text.
The adjacent GitHub-flavored raw email, emoji, and wiki-link extension cases
are now represented too: `**@user**` remains strong text instead of becoming
link syntax, `:smile:` and `:+1:` become Pandoc-style emoji spans with
`class="emoji"` and `data-emoji` metadata, and `[[title|target]]` wiki links
become classed links with literal label text. The WordPress fixture uses this
path for reviewer reaction shortcodes and legacy wiki shortcuts that should
stay visible without importing external media assets or creating nested inline
markup inside the wiki label.
The next adjacent `test/markdown-reader-more.txt` multilingual URL and
numbered-example cases are now represented too: Unicode URI autolinks, Unicode
inline link destinations, and Unicode e-mail autolinks stay clickable, while
`(@)`/`(@label)` example markers become Pandoc Example-style ordered lists and
inline `(@label)` references render as visible example numbers. The WordPress
fixture uses this path for multilingual source audit contacts and numbered
reviewer handoff steps without shelling out to Pandoc.
The adjacent line-block case from `test/markdown-reader-more.txt` is now
represented as well: pipe-prefixed line blocks become `line_block` AST nodes,
leading spaces after `|` become nonbreaking indentation, blank line-block
entries are preserved, and indented continuation lines fold into the previous
line. The WordPress fixture uses this path for source stanzas and reviewer
handoff text where line boundaries must survive block conversion.
The adjacent indented-code-at-beginning-of-list case from
`test/markdown-reader-more.txt` is now represented as well: list items whose
marker is followed by five spaces start with native `code_block` children,
nested ordered and bullet review queues preserve their code snippets, and the
four-space `-    no code` guard remains ordinary reviewer prose.
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
Malformed rowspanned import grids that exceed their declared Pandoc colspec now
carry source-cell/source-column coordinates in table geometry diagnostics, so a
WordPress review queue can keep the overflow cells visible while pointing back
to the physical source cell that caused the audit warning.
Table section grids now also expose anchor, covered, and missing visual slots
after rowspans and colspans are normalized, so DOCX/ODT/HTML import review
packets can audit sparse or merged table geometry without changing the rendered
WordPress table block.

## Scenario Fixture

- `fixtures/wordpress-import-markdown.md` is a small Data Liberation import
  sample with editorial emphasis, a source archive link, visible shortcode-like
  code spans, a reviewer quote, conversion steps with a multi-paragraph
  reviewer follow-up item, parenthesized source-ID steps with nested roman
  reviewer checkpoints, definition-list import notes, an alternate-marker source
  glossary with nested ordered review tasks, a div-wrapped glossary audit note,
  underscore-delimited reviewer emphasis, nested urgent cleanup emphasis,
  unclosed bold quote audit text, strikeout cleanup notes, superscript draft
  status, subscript chemical/media labels, short O~2/x^2 reviewer annotations,
  smart import-editor quotes, apostrophes, ellipses, date-range en
  dashes, em-dash review notes, HTML entity text that must not double-escape,
  literal comparison characters, reference audit links with WordPress edit-link
  titles, spaced media/manifest URLs that must be `%20`-encoded, autolinked
  audit URLs, importer email contacts, a standalone referenced release image, a
  latex-placement reviewer gallery figure with an imported alt override, an
  inline thumbnail image, reference and inline footnotes for source audit
  trails, raw TeX citations, inline/display math notes, nested TeX text math
  with literal dollars, a raw TeX table source block, and a fenced PHP
  migration snippet.
- The fixture also includes a raw import table, an HTML migration audit comment,
  and a custom legacy divider to exercise WordPress HTML block output for
  imported raw HTML boundaries.
- The fixture now includes multilingual Markdown source audit links and
  Pandoc-style numbered examples, exercising Unicode URI/e-mail autolinks plus
  `(@label)` example references in WordPress reviewer handoff text.
- The fixture now includes an attributed inline code source token, exercising
  Pandoc-compatible code attrs and WordPress-safe inline `<code>` id/class/data
  attributes for migration review tooling.
- The fixture now includes an attributed autolink source token, exercising
  Pandoc-compatible autolink attrs and WordPress-safe link id/class/data/title
  attributes for migration review tooling.
- The fixture now includes bare source URL audit notes, exercising
  Pandoc-compatible bare URI autolinks with trailing punctuation and balanced
  parenthesized media paths for pasted migration references.
- The fixture now includes extended bare source URL audit notes, exercising
  Greek source URLs, `%20` paths, and at-sign archive paths from the upstream
  bare URI family.
- The fixture now includes a character-reference audit note, exercising
  Pandoc-compatible named, decimal, and hexadecimal entity decoding in
  paragraph text and link titles before WordPress escaping.
- The fixture now includes link-label boundary audit notes, exercising Pandoc's
  rule that link-looking syntax remains literal inside an ordinary link label
  instead of creating nested anchors.
- The fixture now includes a raw Markdown HTML deletion-boundary audit note,
  exercising Pandoc's raw-open/plain/raw-close handling for block-start
  `<del>...</del>` imports.
- The fixture now includes a reviewer emoji shortcode audit note, exercising
  GitHub-flavored Pandoc emoji span output for `:smile:` and `:+1:` without
  shelling out to Pandoc or importing external assets.
- The fixture now includes compact short script annotations, exercising
  Pandoc's MultiMarkdown short subscript/superscript delimiter behavior for
  reviewer notes such as `O~2` and `x^2`.
- The fixture now includes a multi-line softbreak emphasis note, exercising
  Pandoc's alternating emph/strong paragraph case while keeping the reviewer
  note in one WordPress paragraph.
- The fixture now includes an indented list code handoff, exercising Pandoc's
  five-space list-marker code-block rule for migration snippets while keeping a
  four-space nested reviewer note as prose.
- The fixture now includes a citation boundary audit note, exercising Pandoc's
  bare citation suffix behavior while keeping a following reviewer source link
  as an ordinary WordPress link.
- The fixture now includes a latex-placement reviewer image figure, exercising
  Pandoc's immediate image attribute behavior and WordPress-safe
  `data-pandoc-latex-placement` output.
- The fixture now includes a Pandoc-style line block, exercising source stanza
  boundaries, nonbreaking indentation, and continuation-line preservation in
  WordPress paragraph output.
- The fixture now includes empty legacy HTML table shells, documenting the
  upstream-aligned import policy to omit tables with no cells.
- The fixture now includes a nested legacy HTML audit table to exercise nested
  table-cell block children and WordPress nested table rendering.
- The fixture now also includes a third-level nested legacy HTML audit table,
  documenting the WordPress-specific policy to preserve deep review matrices
  rather than applying Pandoc's AsciiDoc-only two-level table downgrade.
- The fixture now includes a structured HTML import table based on the upstream
  `test/tables/nordics.html5` shape, exercising caption emphasis, colgroup
  widths, thead/tbody/tfoot section preservation, row-header cells, soft line
  breaks, and superscript units in WordPress table block output.
- The fixture now includes a segmented HTML import table based on the upstream
  multiple-`tbody` reader cases, exercising separate body groups for published
  and media-review batches, section and row metadata attrs, plus
  paragraph-bearing table cells in WordPress table block output.
- The fixture now includes a plain td-only HTML reader import table, exercising
  the upstream headerless table body path without changing Markdown-looking raw
  review tables.
- The fixture now includes a body-headed HTML reader import table, exercising
  upstream body-local `TableBody` head rows for migration review queues that
  carry headers inside `tbody` plus a table footer.
- The fixture now includes an HTML reader quote import paragraph with a
  citation-bearing `<q>` and a hard `<br />` line break, exercising non-table
  HTML reader inline semantics for migration reviewer source notes.
- The fixture now includes a legacy HTML `<pre><code class="language-php">`
  snippet, exercising upstream HTML-reader code-block behavior and WordPress
  code block language output without shelling out to Pandoc.
- The fixture now includes a legacy HTML `<blockquote>` import note containing
  a PHP code block, ordered checklist, and nested approval quote, exercising
  upstream HTML-reader quote container behavior and WordPress quote block
  output without shelling out to Pandoc.
- The fixture now includes top-level HTML reader list imports, exercising a
  reviewer checklist `<ul>` with nested media-review bullets plus a roman
  ordered review queue that preserves start/style metadata in WordPress list
  output without shelling out to Pandoc.
- The fixture now includes nested/fancy HTML reader list imports, exercising a
  heading-anchored source checklist, a three-level nested unordered audit list,
  paragraph-bearing ordered items, and nested decimal, roman, and alphabetic
  review queues without shelling out to Pandoc.
- The fixture now includes an HTML reader definition-list import, exercising
  glossary/FAQ `<dl>` content with multiple definitions and consecutive term
  aliases that need to stay grouped in WordPress output without shelling out to
  Pandoc.
- The fixture now includes an HTML reader inline-markup import, exercising
  empty strong/emphasis markers and an emphasized WordPress edit link after an
  implicitly closed paragraph without shelling out to Pandoc.
- The fixture now includes nested HTML reader strong/emphasis review text and
  HTML `<code>` source tokens, exercising preservation of urgent review marks,
  block-comment source snippets, PHP variable names, and literal dollar escapes
  without shelling out to Pandoc.
- The fixture now includes HTML reader special-character import text, exercising
  Unicode list items, entity-decoded organization names, comparison operators,
  and Markdown-sensitive punctuation tokens that must remain literal text in
  WordPress output without shelling out to Pandoc.
- The fixture now includes a complete HTML reader document export, exercising
  title/generator metadata capture, source title-heading class preservation,
  generated heading ids, and literal HTML-reader paragraph handling without
  shelling out to Pandoc.
- The fixture now includes pipe-table import metrics and relative-width review
  note summaries with aligned numeric counts, emphasized status text, code
  spans, a caption with a reference link and code span, and colgroup widths,
  exercising the native table AST and WordPress table block writer.
- The fixture now also includes legacy simple-table source totals with a
  caption, plus a wrapped multiline review-note table with colgroup widths,
  exercising gridless table imports from older Pandoc-compatible exports that
  do not use pipe-table syntax.
- The fixture now includes a Markdown grid-table span import queue based on the
  upstream row/column-span shape, exercising colspan and rowspan preservation in
  WordPress table block output without shelling out to Pandoc.
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
- Raw Markdown HTML deletion boundaries now preserve Pandoc's block boundary:
  the opening and closing `<del>` tags stay raw HTML while the contained text
  renders as ordinary WordPress paragraph content, avoiding literal visible tag
  text in migrated review notes.
- GitHub-flavored Pandoc emoji aliases now render as safe inline WordPress
  spans with `class="emoji"` and `data-emoji` metadata for reviewer reaction
  notes, while unsupported aliases remain literal source text.
- Empty legacy HTML table shells are omitted without shelling out to Pandoc,
  avoiding empty WordPress table blocks in migrated content.
- Nested legacy HTML audit tables render as nested table HTML inside the
  containing WordPress table block, preserving old reviewer matrices that used
  inner tables for grouped import status.
- Third-level nested legacy audit tables are preserved as nested WordPress
  table HTML, making the migration policy explicit for source documents that
  would trigger Pandoc's AsciiDoc depth warning.
- Structured HTML import tables render as core WordPress table blocks with
  preserved `<thead>`, `<tbody>`, `<tfoot>`, `<colgroup>`, caption inline
  markup, row-header `<th>` cell treatment, inferred header rows, omitted
  section-end normalization, and superscript units without invoking Pandoc.
- HTML reader table Attributes imports render as core WordPress table blocks
  with preserved table ids, section classes/data attributes, row
  classes/data/bgcolor attributes, and practical cell attrs without invoking
  Pandoc.
- HTML reader quote/cite paragraphs render as WordPress paragraph blocks with
  Pandoc-style typographic quotes, preserved citation metadata, and hard
  `<br/>` line breaks without invoking Pandoc.
- HTML reader blockquote containers render as core WordPress quote blocks while
  preserving nested quote structure, embedded code blocks, and ordered review
  checklists without invoking Pandoc.
- HTML reader top-level lists render as core WordPress list blocks while
  preserving nested media-review bullets, paragraph-bearing ordered items,
  start values, and roman ordered-list style metadata without invoking Pandoc.
- HTML reader nested/fancy lists render as core WordPress heading and list
  blocks while preserving generated heading anchors, tight nested checklist
  items, paragraph continuations, decimal starts, and nested roman/alpha queue
  styles without invoking Pandoc.
- HTML reader definition lists render as WordPress-safe glossary/FAQ `<dl>`
  markup while preserving consecutive `<dt>` aliases and multiple `<dd>` bodies
  without invoking Pandoc.
- HTML reader inline emphasis/strong markup renders as normal WordPress inline
  HTML, preserving empty source markers and emphasized edit links without
  invoking Pandoc.
- HTML reader literal punctuation imports render as source-preserving WordPress
  paragraphs and separator blocks: straight quotes, apostrophes, quoted
  code/link punctuation, dash strings, hyphen ranges, and spaced ellipses stay
  literal instead of receiving Markdown smart punctuation.
- HTML reader LaTeX-looking source imports render as ordinary WordPress text and
  list markup: `\cite`, `$x \in y$`, and one-line `\begin{tabular}` fragments
  remain literal reviewer source instead of becoming math spans or raw-TeX
  preservation spans.
- HTML reader special-character imports render as ordinary WordPress-safe text,
  list, and separator markup: Unicode source text, decoded `AT&amp;T` entities,
  comparison operators, and punctuation tokens such as `*`, `_`, `[`, `]`, and
  `#` stay literal instead of becoming Markdown syntax.
- HTML reader link imports render as WordPress-safe paragraph links while
  preserving empty `href` placeholders, decoded title entities, ampersand URLs,
  and reference-looking source text such as `[legacy-source]` as literal HTML
  reader content instead of Markdown references. Bare source text immediately
  followed by a `<p>` or `<blockquote>` starts its own paragraph, matching the
  upstream Links fixture's mixed HTML flow shape.
- HTML reader image imports render standalone image-only paragraphs as core
  WordPress image blocks with preserved `src`, `alt`, `title`, and caption
  text, while inline `<img>` nodes remain inside normal paragraph copy for
  reviewer context. This maps the upstream HTML-reader Images fixture without
  invoking Pandoc or treating imported HTML as Markdown image syntax.
- HTML reader footnote exports render footnote-looking anchors as ordinary
  WordPress links, not native Markdown notes, matching the upstream HTML reader
  fixture. Continuation pre/code blocks remain code blocks, and boundary spaces
  around emphasis are normalized outside `<em>` so reviewer copy round-trips
  like Pandoc's native AST.
- Full HTML document exports preserve document title/generator metadata and
  title-heading classes while rendering body content as normal WordPress blocks,
  keeping legacy exporter context available for review without invoking Pandoc.
- Segmented HTML import tables preserve multiple `<tbody>` groups without
  invoking Pandoc, keeping source batches visually grouped for reviewer scans
  with body and row metadata attrs intact.
- Paragraph-bearing cells inside segmented HTML import tables stay as block
  paragraphs inside their table cells without invoking Pandoc.
- Plain headerless HTML reader tables render as core WordPress table blocks
  when the cells contain scalar review data rather than Markdown audit markup.
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
- Inline math whose TeX arguments contain literal dollars now remains one
  WordPress-safe math span, matching Pandoc's `markdown-reader-more` `$ in
  math` fixture for reviewer notes such as `\text{the $n$th root of $y$}`.
- Raw TeX macro definitions from Markdown imports now stay as escaped TeX code
  blocks, and subsequent math using a one-argument macro expands before
  WordPress output. This preserves reviewer-visible source definitions while
  making the rendered math handoff match Pandoc's `markdown-reader-more`
  fixture behavior.
- HTML entity text and comparison characters render as normal escaped
  WordPress paragraph text: `AT&amp;T` is decoded into the AST and emitted once
  as `AT&amp;T`, while `<` is emitted as `&lt;` instead of being treated as raw
  HTML.
- Character and numeric Markdown entity references from
  `Tests.Readers.Markdown` now decode before WordPress escaping too:
  reviewer notes containing `&lang; &ouml;`, decimal references, and
  lowercase/uppercase hexadecimal references render as visible Unicode/text,
  and link title attributes receive the same decoded metadata.
- Reference audit links render as normal WordPress paragraph links with title
  attributes preserved, URI autolinks render as escaped clickable URLs, bare
  pasted http(s) source URLs become anchors with trailing punctuation kept
  outside the link, and importer email autolinks render as `mailto:` links
  without invoking Pandoc.
- Reviewer-pasted source URI notes now map the adjacent Pandoc bare URI
  extension cases: DOI identifiers, Git remote URLs, local `file://` export
  paths, and `mailto:` handoff contacts become WordPress-safe links while
  commas and periods remain outside the anchor text.
- Extended reviewer source URL notes now cover the rest of the upstream bare
  URI shape family: Greek source pages, `%20` paths, and at-sign mailing-list
  archives render as WordPress-safe links without requiring angle brackets.
- Legacy media and manifest links with spaces render as WordPress-safe
  `%20`-encoded URLs, including split reference definitions whose title is on a
  following line.
- Legacy source links whose destinations, titles, or autolink text contain
  HTML entities now decode to the same native URL/title/label text Pandoc
  reports, then render through WordPress escaping once. Parenthesized campaign
  URLs and nested parenthesized reference destinations also remain intact, so
  import-review links such as `/hi(there)` and `hi_(there_(nested))` do not get
  truncated at the first closing parenthesis.
- Backslash-heavy source link labels now preserve escaped visible punctuation
  and reviewer-visible raw TeX commands inside the linked text, unresolved
  reference-looking source markers fall back to bracketed emphasized text,
  citation-adjacent shortcut links keep the source link clickable while leaving
  the citation marker visible, and empty reference placeholders render as empty
  `href` links without swallowing the following review paragraph.
- Backslash-escaped source URL/title punctuation now follows Pandoc's reader
  boundary for migration links: escaped closing parentheses remain part of the
  destination, escaped title quotes render as WordPress-safe title attributes,
  and reference definitions can carry escaped `)` or `.` punctuation without
  leaving literal backslashes in reviewer-facing links.
- Bare Pandoc citation imports now keep reviewer citation text visible while
  preserving link boundaries around adjacent source logs. This lets later
  citation-processing passes see `@cita [review-only note]` without turning a
  real migration source link into citation suffix text.
- Bracketed review spans now preserve Pandoc-style id/class/key-value metadata
  in the AST while the WordPress output emits safe span attributes for migration
  review markers around emphasized edit links.
- Attributed inline code spans now preserve Pandoc-style id/class/key-value
  metadata in the AST while the WordPress output emits safe code attributes for
  migration review markers around source tokens.
- Implicit intra-document reviewer links render as WordPress anchor links, and
  attributed Markdown headings preserve stable ids/classes for migration review
  without shelling out to Pandoc.
- ATX headings with closing `#` markers and setext headings from legacy editor
  notes now normalize to stable WordPress heading anchors, so Data Liberation
  imports do not expose trailing Markdown fence characters in block output.
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
- Rectangular Pandoc grid-table import queues render as core WordPress table
  blocks with upstream-style grid widths, header/headless table shapes,
  right/left/center alignment markers, scalar multiline cells, Unicode source
  text, and empty cells preserved without invoking Pandoc.
- Block-rich Pandoc grid-table import queues now preserve headings, paragraphs,
  and bullet lists inside table cells while keeping scalar multiline cells
  compact. This maps the upstream multiple-block cell case and gives migration
  reviewers WordPress table output without flattening cell-level structure.
- Pandoc grid-table span import queues now preserve omitted interior column
  dividers as `colspan` metadata, partial horizontal separators as `rowspan`
  metadata, and the adjacent complex multi-row header shape as a WordPress table
  head with spanning header cells.
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
- Rowspanned malformed import grids keep overflow cells visible in WordPress
  output while diagnostics record source-cell/source-column coordinates for
  reviewer audit and remediation notes.
- Markdown review lists that contain raw HTML controls now stay attached to the
  intended list item. The fixture maps Pandoc's list issue #1154 shape with
  div/button/div children so migration review markup does not escape the list
  and reorder editorial checklist context.
- GitHub-style reviewer task lists now render as WordPress-safe checkbox list
  HTML from native AST metadata, including nested task follow-up items, without
  shelling out to Pandoc or flattening completed/incomplete review state into
  plain bracket text.
- The same task metadata can now be exported through native Markdown and LaTeX
  writer paths for reviewer handoff documents: unchecked/checked WordPress
  review tasks round-trip as Markdown `- [ ]`/`- [x]` markers and as Pandoc's
  LaTeX square/boxtimes item labels without invoking the upstream binary.
- Native Markdown reviewer handoff exports now preserve Pandoc fancy ordered
  list markers too: source-ID queues can leave WordPress review as `(2)`,
  roman `iv.`, alpha `A.`/`c)`, and default autonumbered Markdown lists with
  Pandoc-style marker spacing instead of flattening every ordered list to
  decimal periods.
- `examples/wordpress-markdown-review-handoff.php` demonstrates a native
  Markdown reviewer packet for WordPress editorial handoff: inline notes and
  quote-contained notes are emitted at Pandoc-compatible block boundaries, and
  source-review links can be written as shortcut reference links with their
  definitions beside the relevant block instead of being flattened into inline
  URLs.
- The same reviewer handoff example now covers Pandoc's shortcut-reference
  boundary rules for adjacent source links, repeated labels, bracketed reviewer
  notes, and citation-adjacent references. This keeps exported WordPress review
  packets parseable by Pandoc-compatible Markdown tooling when multiple source
  URLs share a human label like `source`.
- Native Markdown reviewer handoff exports now also follow Pandoc's top-level
  writer boundaries for review packets assembled from the shared AST:
  multi-paragraph ordered review steps write the first paragraph on the marker
  line and continuation paragraphs under the marker content column, a top-level
  indented source snippet after a list is separated with Pandoc's `<!-- -->`
  guard so it does not become a list continuation when re-read, tight nested
  checklists stay compact, and delimiter-adjacent strong/emphasis spacing keeps
  source-review markers parseable by Pandoc-compatible Markdown tooling.
- Native Markdown reviewer handoff exports now escape literal audit tokens using
  Pandoc's Markdown inline writer rules. This keeps source text such as
  heading-looking `#` markers, Markdown emphasis delimiters, code ticks,
  pipe-table separators, TeX/math punctuation, HTML-looking tags, entity
  references, and raw-TeX backslashes visible as reviewer text instead of being
  reinterpreted when the packet is re-imported.
- Native Markdown reviewer handoff exports now emit Pandoc-style URI and e-mail
  autolinks plus link attributes. The reviewer handoff example writes
  `<https://example.test/review-packet>` and `<editor@example.test>` directly,
  and emits a packet reference definition with `{#review-packet .source-link
  data-source="batch-42"}` metadata so WordPress editorial packets can preserve
  source-review ids/classes without falling back to inline HTML.
- Native Markdown reviewer handoff exports now emit Pandoc-style image Markdown
  too. A reviewer media preview can leave WordPress as a shortcut reference
  image with a definition carrying title, id, class, `alt`, and
  `data-source` metadata, while URI-looking alt text is guarded from becoming
  invalid `!<uri>` autolink syntax.
- Native Markdown reviewer handoff exports now emit Pandoc-style inline code
  attributes. Source-review tokens such as `wp_enqueue_script` can carry
  stable ids, classes, and `data-source` metadata in Markdown packets without
  falling back to raw inline HTML.
- Native Markdown reviewer handoff exports now emit Pandoc-style code-span
  delimiters for source tokens that contain literal backticks or boundary
  spaces. The reviewer handoff example writes a source token containing `wp`, `meta`, and literal backticks with a
  two-backtick delimiter plus id/class/data-source attributes, keeping
  WordPress migration packet source keys round-trippable without escaping the
  literal backticks inside the code text.
- Native Markdown reviewer handoff exports now emit Pandoc-style line blocks.
  Source stanzas such as addresses, poem-like captions, and OCR/import review
  lines leave WordPress as pipe-prefixed Markdown line blocks; blank source
  lines remain bare pipes, and indentation captured by the reader is converted
  back to ordinary spaces for Pandoc-compatible re-import.
- Native Markdown reviewer handoff exports now emit Pandoc-style bracketed
  spans for attributed review markers. Migration spans can carry stable ids,
  classes, titles, and `data-source` metadata around emphasized source flags
  and edit links, while un-attributed spans collapse to ordinary inline content
  to match Pandoc's writer behavior.
- Native Markdown reviewer handoff exports now emit Pandoc-style small-caps,
  strikeout, superscript, and subscript inline marks. The reviewer handoff
  example writes `[source glossary]{.smallcaps}`, `~~legacy caption~~`,
  `revision^draft\ 2^`, and `H~2~O`, keeping editorial source marks portable
  through Markdown review packets without using raw inline HTML.
- Native Markdown reviewer handoff exports now emit Pandoc smart quoted
  inlines. The reviewer handoff example writes a quoted source excerpt with a
  nested `wp_insert_post` code token and WordPress edit link, preserving the
  editorial quote boundary without using raw inline HTML.
- Native Markdown reviewer handoff exports now emit Pandoc fenced Div blocks.
  Review packets can wrap grouped WordPress migration notes in `::::`
  containers with ids, classes, and `data-source` attributes, preserving nested
  quotes and paragraph boundaries for Pandoc-compatible review tooling.
- Native Markdown reviewer handoff exports now degrade table spans into
  rectangular Pandoc pipe-table rows. WordPress-sourced tables with `colspan`
  or `rowspan` metadata keep the visible cell content in the first covered
  column and emit empty placeholder cells for covered columns/rows, so reviewer
  packets remain valid Markdown while preserving the audit trail that the
  source table used structural spans.
- `examples/wordpress-literate-haskell.php` demonstrates source-documentation
  imports that opt into Pandoc's literate Haskell extension. Bird-track and
  inverse-bird-track snippets become WordPress code blocks with Haskell
  language classes, ordinary indented source remains code, and reviewer notes
  written as one-space-indented block quotes stay WordPress quote blocks instead
  of being misclassified as literate source.

## Next Task

Map another bounded Markdown writer branch after table span degradation, such
as multi-block table-cell fallback or additional raw block format variants with
native upstream fixture parity.

## Dependency Closure

No new support component is needed for this slice. The existing bounded
Markdown table AST, block renderer, inline renderer, pipe-table
width/alignment logic, and caption renderer are reused for span degradation;
evidence is the focused lane test.
