# pandoc WordPress Scenario

Document conversion kernel for Data Liberation imports and block-oriented output.

Latest scenario:
`examples/wordpress-native-html-standalone-svg-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pPlain` plus `pSvg`/raw-inline behavior and
`TagCategories` `eitherBlockOrInline` classification for source packets that
start with standalone HTML `<svg>` fragments. It reads imported source-icon
SVG markup and renders WordPress paragraph HTML with raw SVG boundaries
preserved for review instead of treating the packet as an unmapped block. This
remains lane-local and does not invoke upstream Pandoc, live fetching, shelling
out to converters, DOCX package parsing, browser DOM handling, XML/HTML
support rows, package/PDF support rows, citation engines, PlainMath/MathML
conversion, Unicode/charset ports, syntax-highlighting support rows, SVG
sanitization policy, image extraction, arbitrary SVG DOM behavior, or arbitrary
HTML5 tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-applet-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus inline raw-HTML fallback
behavior and `TagCategories` `eitherBlockOrInline` classification for source
packets that start with standalone HTML `<applet>` fragments. It reads legacy
Java applet markup with fallback text and renders WordPress paragraph HTML
with active applet boundaries instead of escaped literal text. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, DOCX package parsing, browser DOM handling, XML/HTML support rows,
package/PDF support rows, citation engines, PlainMath/MathML conversion,
Unicode/charset ports, syntax-highlighting support rows, full plugin
execution semantics, arbitrary applet parameter handling, or arbitrary HTML5
tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-object-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus inline raw-HTML fallback
behavior and `TagCategories` `eitherBlockOrInline` classification for source
packets that start with standalone HTML `<object>` fragments. It reads legacy
interactive embed markup with an `<embed>` fallback and renders WordPress
paragraph HTML with active object/embed boundaries instead of escaped literal
text. This remains lane-local and does not invoke upstream Pandoc, live
fetching, shelling out to converters, DOCX package parsing, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting
support rows, full media/object DOM semantics, arbitrary media/object
fallback, or arbitrary HTML5 tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-video-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus inline raw-HTML fallback
behavior and `TagCategories` `eitherBlockOrInline` classification for source
packets that start with standalone HTML `<video>` fragments. It reads
classic-editor video markup with `<source>` and `<track>` children and renders
WordPress paragraph HTML with active playable media, poster metadata, and
caption-track metadata instead of escaped literal text. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, DOCX package parsing, browser DOM handling, XML/HTML support rows,
package/PDF support rows, citation engines, PlainMath/MathML conversion,
Unicode/charset ports, syntax-highlighting support rows, full media DOM
semantics, arbitrary media/object fallback, or arbitrary HTML5 tree
construction.

Previous scenario:
`examples/wordpress-native-html-standalone-audio-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus inline raw-HTML fallback
behavior and `TagCategories` `eitherBlockOrInline` classification for source
packets that start with standalone HTML `<audio>` fragments. It reads
classic-editor audio markup with `<source>` and `<track>` children and renders
WordPress paragraph HTML with active playable media and caption-track metadata
instead of escaped literal text. This remains lane-local and does not invoke
upstream Pandoc, live fetching, shelling out to converters, DOCX package
parsing, browser DOM handling, XML/HTML support rows, package/PDF support rows,
citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, full media DOM semantics, arbitrary
media/object fallback, or arbitrary HTML5 tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-map-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pPlain` plus inline raw-HTML fallback behavior and
`TagCategories` `eitherBlockOrInline` classification for source packets that
start with standalone HTML `<map>` fragments. It reads classic-editor image-map
hotspots and renders WordPress paragraph HTML with active `<map>`/`<area>`
markup instead of escaped literal text. This remains lane-local and does not
invoke upstream Pandoc, live fetching, shelling out to converters, DOCX package
parsing, browser DOM handling, XML/HTML support rows, package/PDF support rows,
citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, standalone anchor reconciliation, full
image-map DOM semantics, arbitrary inline raw HTML flow, or arbitrary HTML5
tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-del-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pPlain` plus inline dispatch behavior and
`TagCategories` `eitherBlockOrInline` classification for source packets that
start with standalone HTML `<del>` fragments. It reads classic-editor deletion
markup beside inserted replacement copy and renders WordPress paragraph HTML
with active `<del>`/`<u>` markup instead of escaped literal tags or raw block
boundaries. This remains lane-local and does not invoke upstream Pandoc, live
fetching, shelling out to converters, DOCX package parsing, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting
support rows, standalone anchor reconciliation, arbitrary inline raw HTML
flow, or arbitrary HTML5 tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-linebreak-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus `pLineBreak` behavior for
source packets that start with standalone HTML `<br>` fragments instead of a
block wrapper. It reads classic-editor line-break placeholders and renders
WordPress paragraph HTML with active `<br/>` markup instead of escaped literal
tags. This remains lane-local and does not invoke upstream Pandoc, live
fetching, shelling out to converters, DOCX package parsing, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting
support rows, standalone anchor reconciliation, arbitrary inline raw HTML
flow, or arbitrary HTML5 tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-inline-flow-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus `inline` dispatch behavior
for source packets that start with balanced inline HTML fragments instead of a
block wrapper. It reads standalone `<small>`, `span.smallcaps`, `<time>`,
`<q cite>`, and `<cite>` fragments and renders WordPress paragraph HTML where
fine print, small-caps terms, date metadata, quoted-source citation, and cite
boundaries stay reviewable instead of appearing as escaped literal tags. This
remains lane-local and does not invoke upstream Pandoc, live fetching,
shelling out to converters, DOCX package parsing, browser DOM handling,
XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting support
rows, standalone anchor reconciliation, or arbitrary inline raw HTML flow.

Previous scenario:
`examples/wordpress-native-html-cite-wbr-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pRawHtmlInline` fallback behavior for bounded
inline source markup that is not handled by richer semantic branches. It reads
a full HTML source packet where `<cite>` marks the imported source title and
`<wbr>` marks a long slug break, then renders WordPress paragraph HTML with
those source boundaries preserved for review. This remains lane-local and does
not invoke upstream Pandoc, live fetching, shelling out to converters, DOCX
package parsing, browser DOM handling, XML/HTML support rows, package/PDF
support rows, citation engines, PlainMath/MathML conversion, Unicode/charset
ports, syntax-highlighting support rows, or full HTML5 raw inline fallback
parity beyond the bounded tags.

Previous scenario:
`examples/wordpress-native-html-pre-code-breaks-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pCodeBlock`/`tagToText` behavior for preformatted
HTML code exports. It reads a full HTML source packet where `<br>` inside
`<pre><code>` separates classic-editor code lines and a bare `<pre>` carries
source review attributes, then renders WordPress code blocks with those line
breaks preserved. This remains lane-local and does not invoke upstream Pandoc,
live fetching, shelling out to converters, DOCX package parsing, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting support
rows, or broader HTML5 tree-construction parity.

Previous scenario:
`examples/wordpress-native-docx-nested-links-handoff.php` exercises upstream
`test/docx/nested_anchors_in_header.native` plus Pandoc writer `removeLinks`
behavior for DOCX-generated TOC/cross-reference labels. It reads a copied
upstream Native fixture with outer links whose labels contain inner page-number
links, and renders WordPress paragraphs where the outer anchors remain active
while the inner page labels become spans. This remains lane-local and does not
invoke upstream Pandoc, live fetching, shelling out to converters, DOCX package
parsing, browser DOM handling, XML/HTML support rows, package/PDF support rows,
citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, or broader OpenXML support.

Previous scenario:
`examples/wordpress-markdown-gfm-details-list-handoff.php` exercises upstream
command fixture `test/command/9792.md` behavior for GFM writer output around a
nested list inside raw `<details>` boundaries. It builds a WordPress reviewer
packet AST, emits GFM-safe disclosure markup with the blank lines Pandoc adds
around the nested list and closing raw HTML boundary, and also renders active
WordPress list/raw-HTML blocks from the same AST. This remains lane-local and
does not invoke upstream Pandoc, live fetching, shelling out to converters,
browser DOM handling, XML/HTML support rows, package/PDF support rows,
citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, arbitrary raw HTML container parsing, or
broader CommonMark/GFM raw HTML container rules.

Previous scenario:
`examples/wordpress-markdown-details-summary-handoff.php` exercises upstream
command fixture `test/command/6385.md` behavior for Markdown raw
`<details>`/`<summary>` blocks. It imports a disclosure widget from a source
Markdown packet, keeps the `details` and `summary` boundaries as active raw
HTML for review, and parses the details body as editable WordPress paragraph
blocks with emphasis/strong markup. This remains lane-local and does not
invoke upstream Pandoc, live fetching, shelling out to converters, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting
support rows, arbitrary raw HTML container parsing, or broader details
container parsing.

Previous scenario:
`examples/wordpress-native-html-orphan-list-blocks-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` orphan list-block handling around `#9187`.
It reads a full HTML source export with malformed direct block children under
`ul`/`ol`, keeps the leading orphan paragraph as a native list item, attaches
a nested orphan list to the preceding item, and keeps an ordered-list
continuation block inside WordPress list markup. This remains lane-local and
does not invoke upstream Pandoc, live fetching, shelling out to converters,
browser DOM handling, XML/HTML support rows, package/PDF support rows,
citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, or broader malformed-HTML parser parity.

Previous scenario:
`examples/wordpress-native-html-list-item-id-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pListItem` `addId` behavior from
`test/command/3596.md`. It reads a full HTML source export where a tight list
item has an anchor id before a nested list, and a loose paragraph list item
has an anchor id. The handoff keeps the tight anchor as a source span around
the leading inline run, keeps the nested list outside that span, and keeps the
loose anchor as a div wrapper inside the WordPress list item. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, browser DOM handling, XML/HTML support rows, package/PDF support
rows, citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, or the separate orphan list-block slice.

Previous scenario:
`examples/wordpress-native-html-generic-raw-inline-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pRawHtmlInline` fallback behavior for
bounded generic inline source markup. It reads a full HTML source export where
source action markup uses a `button`, source date metadata uses `time`, and a
migration comment sits inside reviewer copy. With raw HTML enabled, those
boundaries/comments remain raw inline HTML around parsed child content; with
raw HTML disabled, the lane drops the raw boundaries/comments and keeps child
text. This remains lane-local and does not invoke upstream Pandoc, live
fetching, shelling out to converters, browser DOM handling, XML/HTML support
rows, package/PDF support rows, citation engines, PlainMath/MathML conversion,
Unicode/charset ports, syntax-highlighting support rows, or full HTML5 raw
inline fallback parity beyond the bounded tags/comments.

Previous scenario:
`examples/wordpress-native-html-smallcaps-class-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pSpan` behavior for
`span class="smallcaps"` source markup. It reads a full HTML source export
where glossary text has neighboring source classes and nested links, maps the
span to native Pandoc `SmallCaps` while dropping the source span metadata like
upstream, and renders WordPress small-caps markup for reviewer handoff. This
remains lane-local and does not invoke upstream Pandoc, live fetching,
shelling out to converters, browser DOM handling, XML/HTML support rows,
package/PDF support rows, citation engines, PlainMath/MathML conversion,
Unicode/charset ports, syntax-highlighting support rows, or broader malformed
HTML parser parity.

Previous scenario:
`examples/wordpress-native-html-checkbox-list-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pCheckbox` behavior for
`input type="checkbox"` controls inside list items. It reads a full HTML source
export with checked, unchecked, mixed non-task, and outside-list controls,
renders list-item checkboxes as WordPress reviewer task labels, keeps the
plain non-task item as ordinary text, and drops outside-list form controls from
the reviewer handoff. This remains lane-local and does not invoke upstream
Pandoc, live fetching, shelling out to converters, browser DOM handling,
XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting
support rows, or full form-control DOM semantics.

Previous scenario:
`examples/wordpress-native-html-mathml-annotation-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pMath`, `extractTeXAnnotation`, and
`MJX_Assistive_MathML` behavior. It reads a full HTML source export with
MathML `annotation encoding="application/x-tex"` payloads, unwraps the
assistive MathML span generated by math renderers, renders native WordPress
math spans once, and keeps MathML without embedded TeX visible as a reviewable
fallback span. This remains lane-local and does not invoke upstream Pandoc,
live fetching, shelling out to converters, browser DOM handling, XML/HTML
support rows, package/PDF support rows, citation engines, PlainMath/MathML
full conversion, Unicode/charset ports, or syntax-highlighting support rows.

Previous scenario:
`examples/wordpress-native-html-doc-noteref-table-handoff.php` exercises
upstream Pandoc command fixture 8770-style footnote placement for
`role="doc-noteref"` anchors in a paragraph, table caption, table header cell,
table body cell, and following paragraph. It reads a full HTML export, imports
each anchor as a native note, and renders WordPress table markup where
figcaption remains after the table but footnote numbering follows Pandoc's
logical caption-before-cell order. This remains lane-local and does not invoke
upstream Pandoc, live fetching, shelling out to converters, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, or syntax-highlighting
support rows.

Previous scenario:
`examples/wordpress-native-html-math-renderer-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pSpan` guards for visual MathJax/KaTeX renderer
output. It reads a full HTML source export with `script type="math/tex"`
equation source plus generated `mjx-chtml`, `MathJax_CHTML`,
`MathJax_Preview`, and exact `katex-html` visual spans, drops the renderer-only
duplicates, and renders WordPress math markup once per equation. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, browser DOM handling, XML/HTML support rows, PlainMath/MathML
conversion, TeX reference conversion, package/PDF support rows, citation
engines, Unicode/charset ports, or syntax-highlighting support rows.

Previous scenario:
`examples/wordpress-native-html-span-strikeout-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pStrikeout` handling for exact
`<span class="strikeout">` source markup plus the adjacent `del`/`ins` edit
branches. It reads a full HTML source export, maps the legacy strikeout span
to a native Pandoc `Strikeout` node instead of a generic span, and renders a
WordPress paragraph where deletion and insertion marks remain reviewable. This
remains lane-local and does not invoke upstream Pandoc, live fetching,
shelling out to converters, browser DOM handling, package/PDF/XML support
rows, citation engines, Unicode/charset ports, or syntax-highlighting support
rows.

Previous scenario:
`examples/wordpress-native-html-line-block-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pLineBlock` behavior. It reads a full HTML source
export with `div class="line-block"`, preserves hard `<br>` line splits, empty
lines, NBSP indentation, and source edit links, and renders a WordPress
paragraph handoff instead of a generic div. This remains lane-local and does
not invoke upstream Pandoc, live fetching, shelling out to converters, browser
DOM handling, package/PDF/XML support rows, citation engines,
Unicode/charset ports, or syntax-highlighting support rows.

Previous scenario:
`examples/wordpress-native-html-raw-disabled-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` raw HTML extension guard behavior around
`pRawHtmlBlock`, `pRawHtmlInline`, and `ignore`. It reads a full HTML source
export with `htmlRawHtml` disabled, skips migration `<style>`, generic
`<script>`, and `<textarea>` raw payloads, and still renders
`script type="math/tex"` as native WordPress math markup. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, browser DOM handling, package/PDF/XML support rows, citation
engines, Unicode/charset ports, or syntax-highlighting support rows.

Previous scenario:
`examples/wordpress-native-html-script-block-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` generic script handling through `pRawHtmlBlock` and
`pHtmlBlock "script"`. It reads a full HTML source export with a body-level
migration script, preserves the `<script>` element as a native raw HTML block,
and renders it as a WordPress core HTML block instead of paragraph-wrapped
inline HTML. Body-level `script type="math/tex..."` remains routed to native
math rather than a raw block. This remains lane-local and does not invoke
upstream Pandoc, live fetching, shelling out to converters, browser DOM
handling, or activating package, PDF, XML/HTML DOM, citation, Unicode/charset,
math, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-style-block-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` block-level style handling through
`pRawHtmlBlock` and `pHtmlBlock "style"`. It reads a full HTML source export
with a body-level migration stylesheet, preserves the `<style>` element as a
native raw HTML block, and renders it as a WordPress core HTML block instead
of paragraph-wrapped inline HTML. This remains lane-local and does not invoke
upstream Pandoc, live fetching, shelling out to converters, browser DOM
handling, or activating package, PDF, XML/HTML DOM, citation, Unicode/charset,
math, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-doc-noteref-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `replaceNotes`, `eFootnotes`, and `eNoteref`
behavior for full HTML imports. It reads a source export with a
`role="doc-noteref"` anchor and a `role="doc-endnotes"` section, converts the
reference into a native Pandoc `Note`, strips the original backlink, skips the
source endnotes container, and renders a clean WordPress endnotes block. This
remains lane-local and does not invoke upstream Pandoc, live fetching,
shelling out to converters, browser DOM handling, or activating package, PDF,
XML/HTML DOM, citation, Unicode/charset, math, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-textarea-handoff.php` exercises the upstream
`Text.Pandoc.Readers.HTML` `pRawHtmlBlock` branch for block-level
`<textarea>`. It reads a legacy source packet from a body-level HTML export,
preserves the textarea as a native raw HTML block, and renders it as a
WordPress core HTML block so the payload remains literal during Data
Liberation review. This remains lane-local and does not invoke upstream
Pandoc, live fetching, shelling out to converters, browser DOM/form handling,
or activating package, PDF, XML/HTML DOM, citation, Unicode/charset, math, or
syntax support rows.

Previous scenario:
`examples/wordpress-native-html-style-script-handoff.php` exercises the
upstream `Text.Pandoc.Readers.HTML` inline `<style>` raw HTML branch and
`<script type="math/tex...">` `pScriptMath` branch. It reads an HTML export
with source CSS and TeX equations, keeps the CSS as a raw HTML inline for
review, and renders inline/display script math through native Pandoc math
nodes in the WordPress handoff. This remains lane-local and does not invoke
upstream Pandoc, PlainMath/MathML conversion, live fetching, shelling out to
converters, or activating package, PDF, XML/HTML DOM, citation, Unicode/
charset, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-svg-raw-handoff.php` exercises the upstream
`Text.Pandoc.Readers.HTML` raw-HTML-enabled SVG path, where `pSvg` is bypassed
by `Ext_raw_html` and the generic raw inline branch preserves SVG markup. It
reads an HTML export with an inline source icon, keeps the SVG as a
`raw_html_inline` node instead of rewriting it to a data image, and renders the
WordPress paragraph with the source SVG still visible for review. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, or activating package, PDF, XML/HTML DOM, citation, math,
Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-spanlike-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pSpanLike` behavior and
`Text.Pandoc.Shared` `htmlSpanLikeElements`. It reads an HTML export with a
keyboard shortcut, publish-highlight text, and source terminology, maps
`<kbd>`, `<mark>`, `<dfn>`, and `<abbr>` to Pandoc spans with tag-name classes
and preserved source metadata, and keeps `<kbd>` distinct from code-like
`code`/`tt`/`samp`/`var` imports. This keeps source-review controls and terms
visible during Data Liberation imports without invoking upstream Pandoc, live
fetching, shelling out to converters, or activating package, PDF, XML/HTML
DOM, citation, math, Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-bdo-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pBdo` behavior. It reads an HTML export with a
bidirectional source title fragment, maps `<bdo dir="RTL">` to a Pandoc span
with lowercased `dir` metadata, preserves nested strong inline content, and
lets no-dir `<bdo>` contents pass through as plain inline text. This keeps
direction-sensitive source copy visible during Data Liberation imports without
invoking upstream Pandoc, live fetching, shelling out to converters, or
activating package, PDF, XML/HTML DOM, citation, math, Unicode/charset, or
syntax support rows.

Previous scenario:
`examples/wordpress-native-html-small-inline-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pSmall` behavior. It reads an HTML export with
source fine print, maps `<small>` to a Pandoc span with class `small`, drops
source id/class attributes to match upstream `B.spanWith ("",["small"],[])`,
preserves nested emphasis/strong inline content, and renders a WordPress
paragraph where the fine print remains reviewable. This keeps source caveats
visible during Data Liberation imports without invoking upstream Pandoc, live
fetching, shelling out to converters, or activating package, PDF, XML/HTML
DOM, citation, math, Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-svg-disabled-raw-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pSvg` behavior when raw HTML is
disabled. It reads an HTML export with a source SVG icon, maps the SVG to a
Pandoc image with a base64 `data:image/svg+xml` URL, preserves the source
id/classes, carries the Font Awesome width hint as `width=1em`, and renders a
WordPress paragraph where the icon remains reviewable inline. This keeps
source SVG status markers visible during Data Liberation imports without
invoking upstream Pandoc, external renderers, live fetching, shelling out to
converters, or activating package, PDF, XML/HTML DOM, citation, math,
Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-iframe-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pIframe` behavior with local-only resource
injection. It reads an HTML export with a base URL and embedded iframe
resources, maps a text/html frame into reviewable nested blocks, maps an
image frame into a safe image preview inside a Pandoc `iframe` div, and keeps
a generic MIME frame as an empty native iframe container. This keeps embedded
source packets reviewable during Data Liberation imports without invoking
upstream Pandoc, live URL fetching, browser tooling, shelling out to
converters, or activating package, PDF, XML/HTML DOM, citation, math,
Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-html-writer-remove-links-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `Link` label handling and
`Text.Pandoc.Writers.Shared` `removeLinks` behavior for WordPress review HTML.
It builds a native review link whose label contains a nested source-note link,
renders an HTML preview where the nested label becomes a metadata-preserving
span instead of an invalid nested anchor, and wraps the preview in a WordPress
HTML review block. This keeps source-note identity reviewable during Data
Liberation imports without invoking upstream Pandoc, shelling out to
converters, fetching media, or activating package, PDF, XML/HTML DOM,
citation, math, Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-html-writer-raw-inline-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `RawInline` handling for WordPress review HTML. It
builds native raw inline nodes for trusted HTML and HTML5 snippets plus a
non-HTML TeX citation payload, renders an HTML preview where only the
HTML-family raw inline snippets pass through, and wraps the preview in a
WordPress HTML review block. This keeps source badges and editorial markups
reviewable during Data Liberation imports without invoking upstream Pandoc,
shelling out to converters, fetching media, or activating package, PDF,
citation, math, or syntax support rows.

Previous scenario:
`examples/wordpress-html-writer-softbreak-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `SoftBreak`/`LineBreak` handling for WordPress
review HTML. It builds native inline break nodes, renders a compact preview
where soft line folds become spaces, renders a source-line-preserving preview
when `writerWrapText=wrap-preserve`, and wraps the preserved preview in a
WordPress HTML review block. This keeps source excerpts and reviewer
checklists readable during Data Liberation imports without invoking upstream
Pandoc, shelling out to converters, fetching media, or activating package,
PDF, citation, math, or syntax support rows.

Previous scenario:
`examples/wordpress-html-writer-spanlike-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `Span` class lowering for WordPress review HTML. It
builds native span nodes for a keyboard shortcut, marked publish-preview text,
and abbr/dfn source terminology, renders an HTML preview with Pandoc-style
`kbd`, `mark`, `dfn`, `abbr`, `u`, and `span.smallcaps` lowering, and wraps
that preview in a WordPress HTML review block. This keeps imported editorial
source notes reviewable without invoking upstream Pandoc, shelling out to
converters, fetching media, or activating package/PDF/citation/math/syntax
support rows.

Previous scenario:
`examples/wordpress-html-writer-media-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` image media-category output for WordPress review
HTML. It builds native image nodes for video, audio, and PDF media, renders an
HTML preview with Pandoc-style `<video>`, `<audio>`, and `<embed>` output, and
wraps that preview in a WordPress HTML review block. This keeps imported media
handoffs reviewable without invoking upstream Pandoc, fetching media, shelling
out to converters, or activating PDF/package/rich document-format support.

Previous scenario:
`examples/wordpress-native-html-figure-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pFigure`/`pImage` figure and figcaption reader
branches for WordPress review HTML. It reads a source export with a media
figure id, source classes, image alt/title metadata, list body context, and a
rich figcaption containing emphasis and a source-edit link, then renders a
WordPress image block that preserves the reviewable media identity and caption
without invoking upstream Pandoc, a browser, converter shell-outs,
ZIP/package parsing, or broader XML/HTML support-library expansion.

Previous scenario:
`examples/wordpress-native-html-section-aside-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` native div-like `section` and `aside` branches for
WordPress review HTML. It reads an HTML export with a `main` article wrapper,
a source-review `section`, and a migration-note `aside`, then renders a
WordPress HTML block that preserves wrapper id/class/data metadata and clears
the duplicated first heading id when it matches the section wrapper. This keeps
source sections and editorial side notes reviewable during Data Liberation
imports without invoking upstream Pandoc, a browser, converter shell-outs,
ZIP/package parsing, or broader XML/HTML support-library expansion.

Previous scenario:
`examples/wordpress-html-writer-math-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` MathJax, KaTeX, WebTeX, and GladTeX math output branches for
WordPress review HTML. It builds a native AST with inline and display TeX
equations, then renders a MathJax-style preview with `\(...\)`/`\[...\]`
delimiters, a KaTeX-style preview with raw TeX payloads, a WebTeX image URL
preview with encoded TeX payloads, a GladTeX `eq` preview, and matching
WordPress block handoff markup. This keeps equation source reviewable during
Data Liberation imports without invoking upstream Pandoc, TeXMath/MathML
conversion, image fetching, browser tooling, converter shell-outs,
ZIP/package parsing, or a broader math support library.

Previous scenario:
`examples/wordpress-html-writer-citation-role-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` citation and footnote accessibility role branches
for WordPress review HTML. It builds a native AST with a bounded citation
packet, a bibliography link targeting `#ref-source-audit`, an ordinary
WordPress source-review link, a reviewer footnote, and a CSL-style refs block.
The HTML preview emits `data-cites`, `role="doc-biblioref"`,
`role="doc-noteref"`, and `role="doc-backlink"` where upstream does; the
matching WordPress block handoff keeps the citation payload and bibliography
source packet reviewable without invoking upstream Pandoc, citeproc/CSL
processing, browser tooling, converter shell-outs, ZIP/package parsing, or
rich document-format support.

Previous scenario:
`examples/wordpress-html-writer-csl-wrapper-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` wrapper `Div` and CSL bibliography `Div` branches
for WordPress review HTML. It builds a native AST with a wrapper div around
the source-review intro and a `refs`/`csl-entry` bibliography packet, then
renders an HTML preview where wrapper attributes move to the paragraph,
`role="list"` and `role="listitem"` are emitted for citation accessibility,
and paragraphs inside CSL entries render as plain bibliography lines. The
matching WordPress block handoff keeps the same source packet as reviewable
HTML blocks without invoking upstream Pandoc, a CSL processor, browser
tooling, converter shell-outs, ZIP/package parsing, or rich document-format
support.

Previous scenario:
`examples/wordpress-html-writer-raw-div-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `RawBlock` and `Div` output for WordPress review
HTML. It builds a native AST with a source review wrapper, trusted raw HTML,
non-HTML raw TeX, and a nested div, then renders an HTML preview where the
wrapper becomes a section element, trusted HTML raw content passes through,
the nested div stays grouped, and non-HTML raw blocks are omitted from the
HTML writer output. The matching WordPress block handoff keeps the raw source
packets reviewable inside the wrapper instead of executing converters or
activating ZIP/package/rich document-format support.

Previous scenario:
`examples/wordpress-html-writer-table-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` table output for WordPress review HTML. It builds a
native AST table with a caption, explicit column widths, section metadata,
row-head cells, colspan/rowspan cells, and escaped review text, then renders an
HTML preview preserving the table element attributes, `caption`, `colgroup`,
`thead`, `tbody`, `tfoot`, alignment styles, and spans. The matching WordPress
block handoff keeps the same packet as a core table block for import review.
This covers migration fragments where tabular source audits need reviewable
HTML output without shelling out to Pandoc, invoking a browser/converter, or
activating ZIP/package/rich document-format support.

Previous scenario:
`examples/wordpress-html-writer-figure-line-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` structural block output for WordPress review HTML.
It builds a native AST with a source figure from `test/testsuite.native`, a
reviewer line-block stanza, and a section break, then renders an HTML preview
where `<hr />`, `div.line-block`, `figure`, `figcaption`, and alt-equivalent
`aria-hidden` caption behavior are preserved. The matching WordPress block
handoff keeps the same packet as an image block, paragraph with line breaks,
and separator block. This covers migration fragments where imported media,
line-preserved reviewer notes, and section separators need to survive review
output without shelling out to Pandoc, invoking a browser/converter, or
activating ZIP/package/rich document-format support.

Previous scenario:
`examples/wordpress-html-writer-code-block-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `CodeBlock` fallback output for WordPress review
HTML. It builds a native AST with a source-review code snippet, stable source
id, and `data-source` marker, then renders an HTML preview where code text is
escaped inside `pre > code` and source attributes stay on `pre`. The matching
WordPress block handoff keeps the same snippet as a core code block for import
review. This covers migration fragments where legacy shortcode/filter snippets
need to survive review output without shelling out to Pandoc, invoking a
browser/converter, or activating the broader syntax-highlighting support gate.

Previous scenario:
`examples/wordpress-latex-ordered-list-handoff.php` exercises upstream
`Text.Pandoc.Writers.LaTeX` ordered-list label, counter, and tight-list
behavior for WordPress source review exports. It builds a native AST with a
lower-roman source checklist starting at iv and a nested upper-alpha review
subqueue, then renders LaTeX with Pandoc-style `\def\label...`,
`\setcounter`, `\tightlist`, and nested `enumii` output. The matching
WordPress block handoff keeps ordered-list `start` and `type` metadata,
covering review packets where legacy ordered-list numbering must survive in
both printable reviewer output and block-editor HTML without shelling out to
Pandoc, invoking TeX/PDF, using templates, or activating rich package/document
conversion support.

Previous scenario:
`examples/wordpress-latex-quote-hr-handoff.php` exercises upstream
`Text.Pandoc.Writers.LaTeX` block quote and horizontal-rule behavior for
WordPress source review exports. It builds a native AST with a reviewer quote,
Pandoc section separator, and following publish checklist paragraph, then
renders LaTeX where the quote becomes a `quote` environment and the separator
becomes Pandoc's centered rule. The matching WordPress block handoff keeps a
core quote block, separator block, and paragraph, covering review packets where
source caveats and section breaks need printable reviewer output without
shelling out to Pandoc, invoking TeX/PDF, using templates, or activating rich
package/document conversion support.

Previous scenario:
`examples/wordpress-latex-raw-tex-handoff.php` exercises upstream
`Text.Pandoc.Writers.LaTeX` raw TeX passthrough behavior for WordPress source
review exports. It builds a native AST with a raw citation inline and tabular
TeX block, then renders LaTeX where both raw TeX fragments pass through
literally while the matching WordPress block handoff keeps the citation as a
`pandoc-raw-tex` inline span and the table source as review-safe TeX code.
This covers migration-review packets where source TeX needs printable reviewer
output without shelling out to Pandoc, invoking TeX/PDF, using templates, or
activating rich package/document conversion support.

Previous scenario:
`examples/wordpress-latex-highlighted-strikeout-code-handoff.php` exercises
upstream `Tests.Writers.LaTeX` highlighted inline-code behavior inside
strikeout for WordPress source review exports. It builds a native AST with a
Haskell import helper marked as deleted/stale reviewer text, then renders
LaTeX where the code span is protected as
`\st{\mbox{\VERB|\NormalTok{renderBlocks}|} ...}` while the matching
WordPress block handoff keeps `<del>`, the inline `code` class, and
`data-source` metadata. This covers migration-review packets where code-like
source snippets need printable deletion marks without shelling out to Pandoc,
invoking TeX/PDF, using templates, or activating a syntax-highlighting engine.

Previous scenario:
`examples/wordpress-latex-listing-code-handoff.php` exercises upstream
`Tests.Writers.LaTeX` IdiomaticHighlighting code-block listing branches for
WordPress source review exports. It builds a native AST with a labelled PHP
shortcode snippet, then renders LaTeX reviewer text where the snippet is a
`lstlisting` with `label=shortcode-audit` while the matching WordPress block
handoff keeps the code-block id, language class, and `data-source` metadata.
This covers migration-review packets where source snippets need stable
print-review labels without shelling out to Pandoc, invoking TeX/PDF, using
templates, or activating a syntax-highlighting engine.

Previous scenario:
`examples/wordpress-latex-underline-strikeout-note-handoff.php` exercises the
remaining upstream `Tests.Writers.LaTeX` inline-note styling branches for
WordPress source review exports. It builds a native AST with inserted source
context and deleted/stale shortcode text, then renders LaTeX reviewer text
where multi-paragraph notes split outside `\ul{}` and `\st{}` while the
matching WordPress block handoff keeps `<u>`/`<del>` markup and endnotes. This
covers migration-review packets where editorial insertion/deletion marks need
printable reviewer output without changing the block-editor handoff or
shelling out to Pandoc, TeX/PDF, templates, or syntax-highlighting engines.

Previous scenario:
`examples/wordpress-latex-top-level-division-handoff.php` exercises upstream
`Tests.Writers.LaTeX` top-level division writer options for WordPress source
book review exports. It builds a native AST with a legacy handbook heading and
an import-checklist subheading, then renders LaTeX reviewer text with
`writerTopLevelDivision=chapter`, so the hierarchy becomes `\chapter` plus
`\section` while the matching WordPress block handoff remains ordinary heading
blocks. This covers migration-review packets where a source document's book
hierarchy needs printable reviewer output without changing the block-editor
heading levels or shelling out to Pandoc, TeX/PDF, or syntax-highlighting
engines.

Previous scenario:
`examples/wordpress-latex-unnumbered-heading-note-handoff.php` exercises
upstream `Tests.Writers.LaTeX` unnumbered heading-with-note output for
WordPress source audit exports. It builds a native AST heading with
`class="unnumbered"`, an id, and an inline reviewer note, then renders LaTeX
reviewer text with Pandoc's starred section, `\texorpdfstring` fallback,
`\footnote`, `\label`, and `\addcontentsline` shape. The matching WordPress
block handoff keeps the same source audit as a heading plus endnote. This
covers migration-review packets where reviewer-only context must remain
attached to a section title without polluting PDF bookmark text or shelling
out to Pandoc, TeX/PDF, or syntax-highlighting engines.

Previous scenario:
`examples/wordpress-latex-footnote-code-handoff.php` exercises upstream
`Tests.Writers.LaTeX` code-block-in-footnote output for WordPress source audit
exports. It builds a small native AST paragraph with an inline note whose
body contains reviewer prose and a shortcode code block, then renders LaTeX
reviewer text with Pandoc's `\footnote{...}` plus `Verbatim` code-block
shape. The matching WordPress block handoff keeps the same source audit as an
endnote containing a core code block. This covers migration-review packets
where source snippets must remain attached to editorial footnotes without
shelling out to Pandoc or invoking a TeX/PDF/syntax-highlighting engine.

Previous scenario:
`examples/wordpress-latex-heading-image-handoff.php` exercises upstream
`Tests.Writers.LaTeX` heading-image output for WordPress media review exports.
It builds a small native AST heading whose text includes an imported image,
then renders LaTeX reviewer text with Pandoc's
`\texorpdfstring{\protect\pandocbounded{\includegraphics[...]}}{...}` fallback
shape for PDF strings. The matching WordPress block handoff keeps the same
source hero image inside a heading block with alt text preserved. This covers
migration-review packets where imported heading artwork needs printable
reviewer output and block-editor heading HTML without shelling out to Pandoc
or invoking a TeX/PDF/image engine.

Previous scenario:
`examples/wordpress-latex-figure-handoff.php` exercises upstream
`Tests.Writers.LaTeX` figure placement output for WordPress media review
exports. It builds a small native AST figure with `latex-placement="htbp"`,
an imported image URL, alt text, and a caption, then renders LaTeX reviewer
text as a `figure` environment with `\centering`,
`\pandocbounded{\includegraphics[...]}`, and `\caption{...}` output. The
matching WordPress block handoff keeps the same imported media frame as an
image block with the source placement recorded as reviewer metadata. This
covers migration-review packets where imported media needs printable reviewer
output and block-editor media review HTML without shelling out to Pandoc or
invoking a TeX/PDF/image engine.

Previous scenario:
`examples/wordpress-latex-definition-list-handoff.php` exercises upstream
`Tests.Writers.LaTeX` definition-list output for WordPress review exports. It
builds a small native AST definition list with source-review terms, an
internal checklist link, and a heading-bearing definition body, then renders
LaTeX reviewer text with a `description` environment, `\tightlist`, and
`\hyperref` anchors. The matching WordPress block handoff keeps the same
source review packet as definition-list HTML with links and heading content
preserved. This covers migration-review packets where glossary/status terms
need printable reviewer output and block-editor review HTML without shelling
out to Pandoc or invoking a TeX/PDF engine.

Previous scenario:
`examples/wordpress-latex-heading-handoff.php` exercises upstream
`Tests.Writers.LaTeX` heading defaults for WordPress review exports. It builds
a small native AST outline for migration review, media checks, and reviewer
notes, then renders LaTeX reviewer text with `\section`, `\subsection`, and
`\subsubsection` commands. The matching WordPress block handoff keeps the same
source outline as heading blocks with the review anchor preserved. This covers
migration-review packets where an editorial outline needs both printable
review text and block-editor headings without shelling out to Pandoc or
invoking a TeX/PDF engine.

Previous scenario:
`examples/wordpress-latex-code-handoff.php` exercises upstream
`Tests.Writers.LaTeX` inline-code escaping for WordPress review exports. It
builds a small native AST packet with reviewer command/code spans containing
an apostrophe and backticks, then renders LaTeX reviewer text where those code
spans become `\texttt{dog\textquotesingle{}s}` and
`\texttt{\textasciigrave{}nu?\textasciigrave{}}`. The matching WordPress block
handoff keeps the same source fragments as inline `<code>` elements. This
covers migration-review packets where source commands need literal audit text
without shelling out to Pandoc or invoking a TeX/PDF engine.

Previous scenario:
`examples/wordpress-html-writer-list-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `BulletList` and `OrderedList` output for
WordPress review HTML. It builds a small native AST packet with an ordered
source checklist starting at a non-1 upper-alpha marker, nested bullet-list
evidence, and task-list checkbox labels, then renders both an HTML preview and
the matching `WordPressBlockWriter` block-list handoff. This covers migration
review fragments where source numbering, nested list shape, and task status
need to survive without shelling out to Pandoc or using a browser/converter.

Previous scenario:
`examples/wordpress-html-writer-section-div-footnotes-handoff.php` exercises
upstream `Tests.Writers.HTML` `EndOfSection` plus `writerSectionDivs` output
for WordPress review HTML. It builds a small native AST packet with a
top-level review heading, a source-notes section containing a reviewer note,
and a later publish-checklist section. Rendering with `writerSectionDivs`
keeps the footnote block inside the source-notes section before the checklist
section begins, matching Pandoc's section-div footnote placement semantics
without shelling out to Pandoc.

Previous scenario:
`examples/wordpress-html-writer-footnote-placement-handoff.php` exercises
upstream `Tests.Writers.HTML` footnote placement output for WordPress review
HTML. It builds a small native AST packet with a paragraph note and a
blockquote note, then renders with `referenceLocation=end_of_block` so each
note is emitted after the block that introduced it. This covers migration
review fragments where source edit links and quote-scoped notes need to stay
attached without shelling out to Pandoc.

Previous scenario:
`examples/wordpress-html-writer-highlighted-code-handoff.php` exercises
upstream `Tests.Writers.HTML` highlighted inline-code output for WordPress
review HTML. It builds a small native AST transform-diagnostic packet with a
Haskell-style operator, then renders `sourceCode haskell` code where `>>=`
is wrapped in `span.op`; sample diagnostics are wrapped in `samp` and
post-field variables are wrapped in `var`. This covers migration/review
fragments where source diagnostics need semantic code roles and readable
operator highlighting without shelling out to Pandoc or activating the broader
syntax-highlighting support gate.

Previous scenario:
`examples/wordpress-html-writer-definition-list-handoff.php` exercises
upstream `Tests.Writers.HTML` definition-list output for WordPress review HTML.
It builds a small native AST glossary/status packet with one ordinary source
term and one blank source term, then renders Pandoc-style `<dl>`, `<dt>`, and
`<dd>` output. This covers migration/review fragments where a legacy source
exports an empty glossary term and the review handoff must preserve that fact
without placeholder text or a Pandoc shell-out.

Previous scenario:
`examples/wordpress-html-writer-quote-cite-handoff.php` exercises upstream
`Tests.Writers.HTML` quote-with-cite output for WordPress review HTML. It
builds a small native AST packet where a reviewer quote carries source
citation metadata, then renders with the `htmlQTags` option so the output is
`q cite` rather than a nested span. This covers migration/review fragments
where source notes need semantic citation metadata without shelling out to
Pandoc.

Previous scenario:
`examples/wordpress-html-writer-image-attrs-handoff.php` exercises upstream
`Tests.Writers.HTML` image-alt and heading-attribute output for WordPress
media review. It builds a small native AST packet where formatted image label
inlines stringify to a plain `alt` attribute, the source image title and
`data-source` marker survive, and noisy unsupported heading metadata is
dropped while `lang` remains. This covers WordPress import/review HTML
fragments where media accessibility text and source tracing must survive
without rendering markup inside `alt` or shelling out to Pandoc.

Previous scenario:
`examples/wordpress-html-writer-code-roles-handoff.php` exercises upstream
`Tests.Writers.HTML` code-role output for WordPress reviewer diagnostics. It
builds a small native AST packet where a block name stays ordinary `code`, a
sample migration warning renders as `samp`, and a post-field variable renders
as `var`. This covers WordPress import/review HTML fragments where semantic
diagnostic roles must survive without flattening every code-like token into a
classed `<code>` element or shelling out to Pandoc.

Previous scenario:
`examples/wordpress-native-html-codeblock-attrs-handoff.php` exercises upstream
`Tests.Readers.HTML` `pre > code` attribute behavior for WordPress import
review. It parses a legacy HTML export whose code block carries a source
snippet id, language class, and `data-source` metadata, then renders a
WordPress code block that keeps the id/data metadata on the outer `<pre>` while
the language remains on the inner `<code>`. It also covers Pandoc's upstream
precedence rule where attributes on `<pre>` replace nested `<code>` attributes,
so review wrappers win over stale nested snippet ids without shelling out to
Pandoc.

Previous scenario:
`examples/wordpress-native-html-lang-metadata-handoff.php` exercises upstream
`Tests.Readers.HTML` root `lang` and `xml:lang` behavior for WordPress import
review. It parses a legacy HTML export whose `<html>` element declares
`lang="es"` and renders with metadata review enabled, so the source language
stays attached to the imported content as `lang=es` while the body copy becomes
ordinary WordPress paragraph output. This covers Data Liberation imports where
source language metadata is needed for editorial routing, accessibility review,
and translation workflows without shelling out to Pandoc.

Previous scenario:
`examples/wordpress-native-html-inline-code-handoff.php` exercises upstream
`Tests.Readers.HTML` inline `code`, `tt`, `samp`, and `var` behavior for
WordPress import review. It parses a legacy HTML export whose diagnostics
mention a block name, a shortcode-like `tt` fragment, a sample reviewer
message, and a variable name. The WordPress output keeps all four as inline
code and preserves Pandoc's `sample`/`variable` classes on the `samp` and
`var` branches. This covers Data Liberation imports where source HTML uses
semantic inline code roles and migration tooling must not flatten them into
literal tags or ordinary paragraph text.

Previous scenario:
`examples/wordpress-native-html-header-handoff.php` exercises upstream
`Tests.Readers.HTML` native-div `<header>` behavior for WordPress import
review. It parses a legacy HTML export whose `<main>` content contains an
article `<header>` and uses the opt-in `htmlNativeDivs` reader path to keep
that header as a native div with `class="header"`, id metadata, and
review-facing data attributes. This covers Data Liberation imports where the
source export's article title/deck region belongs to the post body and must
not be flattened into an ordinary paragraph or dropped with surrounding site
chrome.

Previous scenario:
`examples/wordpress-native-html-main-handoff.php` exercises upstream
`Tests.Readers.HTML` native-div `<main>` behavior for WordPress import review.
It parses a legacy HTML export with header, nav, main, and footer regions and
uses the opt-in `htmlNativeDivs` reader path to keep only the first main
document body. The WordPress output preserves the main wrapper's id, class,
data-source, and generated `role="main"` metadata while dropping surrounding
export boilerplate. This covers Data Liberation imports where a source HTML
dump contains navigational chrome that must not become post content, without
shelling out to Pandoc.

Previous scenario:
`examples/wordpress-native-html-anchor-image-attrs-handoff.php` exercises
upstream `Tests.Readers.HTML` anchor and image-attribute behavior for
WordPress import review. It parses HTML exported with legacy `<a name>` and
id-only jump targets plus an externally sourced image marked
`data-external="1"`. The WordPress output keeps the anchors as span targets
instead of empty links and carries the external image metadata through to the
rendered image tag. This covers Data Liberation imports where old HTML
bookmarks and externally hosted media need reviewer-visible boundaries without
shelling out to Pandoc.

Previous scenario:
`examples/wordpress-native-html-base-media-handoff.php` exercises upstream
`Tests.Readers.HTML` base-tag behavior for WordPress import review. It parses
HTML exported with `<base href>` and resolves relative media, relative audit
links, and root-relative media to absolute WordPress URLs before block output.
This covers Data Liberation imports where legacy HTML was exported from a
document package or staging directory and media references must stay attached
without shelling out to Pandoc.

Previous scenario:
`examples/wordpress-markdown-abbrev-handoff.php` exercises upstream
`test/command/md-abbrevs.md` and `data/abbreviations` behavior for WordPress
import review. It parses known unescaped abbreviations before following
letters as nonbreaking groups, preserving `Mr. Bob`, `Dr. Rivera`, and
`e.g. examples` in paragraph output, while escaped source periods such as
`Mr\. Bob` keep ordinary spacing. This covers migration paths where editorial
titles, honorifics, and glossary abbreviations must stay visually grouped in
block-editor review packets without shelling out to Pandoc.

Previous scenario:
`examples/wordpress-markdown-raw-attribute-handoff.php` exercises upstream
`test/command/parse-raw.md`-style Markdown raw-attribute output for WordPress
import review. It parses code-span raw attributes such as `{=latex}` and
`{=html}`, plus fenced raw blocks, through MarkdownReader. Raw HTML is
preserved literally in block output when the source format allowed raw HTML,
while latex/opml-style raw payloads remain visible as `data-pandoc-raw-format`
review spans or code blocks. This covers migration paths where a prior Pandoc
stage or trusted Markdown source carries format-specific raw fragments that
must not silently become ordinary code or literal `{=format}` text.

Previous scenario:
`examples/wordpress-native-docx-table-gridbefore-handoff.php` exercises a
bounded upstream-derived `test/docx/table_gridbefore.native` slice for
WordPress import review. It parses a DOCX Native table packet with scientific
column widths, explicit blank gridBefore/gridAfter cells, spacer rows, and
wide colspans, then renders a WordPress core table with the source blank cells
preserved. The example enables `markEmptyTableCells`, adding
`data-pandoc-empty-cell="true"` markers to nineteen empty table cells so
migration reviewers can distinguish intentional DOCX grid placeholders from
missing data without activating DOCX ZIP/OpenXML package parsing.

Previous scenario:
`examples/wordpress-native-empty-paragraphs-handoff.php` exercises upstream
`test/command/empty_paragraphs.md` semantics for WordPress import review. It
parses a Native packet containing `Para []` separators and renders with
`preserveEmptyParagraphs` enabled, producing four WordPress paragraph blocks
including the two empty `<p></p>` blocks that Pandoc's
`html5+empty_paragraphs` branch preserves. The default WordPress handoff still
drops empty paragraphs like Pandoc `html5`, so migration tooling can opt into
blank paragraph evidence only for source formats or reviewer workflows that
need it.

Previous scenario:
`examples/wordpress-native-odt-multi-header-table-handoff.php` exercises an
upstream-shaped ODT Native multi-header table packet for WordPress import
review. It parses the bounded
`test/odt/native/simpleTableWithMultipleHeaderRows.native` slice through
NativeReader and renders a WordPress table whose two source header rows stay in
`<thead>`, whose three body rows keep the empty cells visible, and whose
default-width ODT columns do not invent a `<colgroup>`. The trailing upstream
empty `Para []` is dropped from block output. This covers spreadsheet-like ODT
imports where source tables carry stacked header bands that must remain
reviewable in the block editor without activating OpenDocument ZIP/XML package
parsing.

Previous scenario:
`examples/wordpress-native-docx-track-changes-decision-handoff.php` exercises
upstream-shaped DOCX Native accepted/rejected insertion and deletion packets
for WordPress import review. It parses the bounded
`test/docx/track_changes_insertion_accept.native`,
`test/docx/track_changes_insertion_reject.native`,
`test/docx/track_changes_deletion_accept.native`, and
`test/docx/track_changes_deletion_reject.native` slices through NativeReader
and renders four reviewer sections. Accepted insertion keeps `two exciting`,
rejected insertion omits those inserted words, accepted deletion omits
`an excessively modified`, and rejected deletion retains that deleted text.
This covers Word/DOCX review handoffs where upstream Pandoc has already
applied an accept/reject choice and WordPress output must not retain stale
`<ins>`/`<del>` review markup.

Previous scenario:
`examples/wordpress-markdown-spanlike-handoff.php` exercises upstream
`test/command/nested-spanlike.md` semantics for WordPress Markdown import
review. It parses `[test]{.foo .underline #bar .smallcaps .kbd}` through
MarkdownReader and renders the upstream HTML-writer wrapper shape
`<kbd id="bar"><u><span class="smallcaps">test</span></u></kbd>`. This covers
keyboard/editorial source markers where Pandoc consumes spanlike marker
classes for HTML output, keeps the source id on the outer wrapper, and avoids
leaking consumed marker classes into WordPress block markup.

Previous scenario:
`examples/wordpress-native-docx-paragraph-change-decision-handoff.php`
exercises upstream-shaped DOCX Native paragraph insertion/deletion
accept/reject packets for WordPress import review. It parses the bounded
`test/docx/paragraph_insertion_deletion_accept.native` and
`test/docx/paragraph_insertion_deletion_reject.native` slices through
NativeReader and renders two reviewer sections: the accepted decision keeps
the source paragraph split as `This is a` then `split Paragraph.`, while the
rejected decision emits `This is a split` then `Paragraph.`. This covers
Word/DOCX review handoffs where upstream Pandoc has already applied an
accept/reject choice and WordPress output must not retain stale paragraph
change metadata.

Previous scenario:
`examples/wordpress-native-docx-track-changes-move-decision-handoff.php`
exercises upstream-shaped DOCX Native moved-text accept/reject packets for
WordPress import review. It parses the bounded
`test/docx/track_changes_move_accept.native` and
`test/docx/track_changes_move_reject.native` slices through NativeReader and
renders two reviewer sections: the accepted decision keeps the moved paragraph
between its surrounding context paragraphs, while the rejected decision leaves
the later context before the moved paragraph. This covers Word/DOCX review
handoffs where upstream Pandoc has already applied an accept/reject choice and
WordPress output must not retain stale insertion/deletion markup.

Previous scenario:
`examples/wordpress-native-docx-overlapping-targets-handoff.php` exercises an
upstream-shaped DOCX Native overlapping-target packet for WordPress import
review. It parses the bounded `test/docx/overlapping_targets.native` slice
through NativeReader and renders two same-fragment links plus the shared empty
`#Fizz` target span marked with `data-pandoc-anchor="empty-target"`. This
covers Word/DOCX handoffs where multiple cross-reference names point at one
target and migration reviewers need the preserved in-page anchor to remain
visible in block output without invoking upstream Pandoc or activating DOCX
ZIP/OpenXML package parsing.

Previous scenario:
`examples/wordpress-native-docx-scrubbed-metadata-handoff.php` exercises an
upstream-shaped DOCX Native scrubbed review metadata packet for WordPress
import review. It parses the bounded
`test/docx/track_changes_scrubbed_metadata.native` slice through NativeReader
and renders author-only deletion, insertion, and comment spans with explicit
missing-date metadata status. This covers Word/DOCX handoffs where upstream
Pandoc scrubbed review dates and migration reviewers still need visible change
and comment boundaries without fake `datetime` values, raw upstream
`author`/`date` attributes, or DOCX ZIP/OpenXML package parsing.

Previous scenario:
`examples/wordpress-native-docx-track-changes-move-handoff.php` exercises an
upstream-shaped DOCX Native moved-text review packet for WordPress import
review. It parses the bounded `test/docx/track_changes_move_all.native` slice
through NativeReader and renders the moved-to and moved-from text as paired
`<ins>`/`<del>` spans with `data-pandoc-change-author`,
`data-pandoc-change-date`, and `datetime` metadata, while avoiding raw upstream
`author` or `date` attributes. This covers Word/DOCX handoffs where migration
reviewers need moved text visible in the block editor without invoking
upstream Pandoc or activating DOCX ZIP/OpenXML package parsing.

Previous scenario:
`examples/wordpress-native-docx-image-textbox-caption-handoff.php` exercises an
upstream-shaped DOCX Native image textbox caption packet for WordPress import
review. It parses the bounded `test/docx/image_with_textbox_caption.native`
slice through NativeReader and renders the captioned EMF image with source
dimensions, `data-pandoc-source-format="emf"`, a figcaption, and caption-derived
image alt text marked with `data-pandoc-alt-source="figure-caption"`. This
covers Word/DOCX handoffs where captions stored in textboxes must remain useful
for media review without pretending the original source supplied image alt text.

Previous scenario:
`examples/wordpress-native-docx-diagram-handoff.php` exercises an
upstream-shaped DOCX Native unsupported diagram packet for WordPress import
review. It parses the bounded `test/docx/diagram.native` slice through
NativeReader and renders the upstream `[DIAGRAM]` placeholder as a visible
review span with `data-pandoc-diagram="unsupported-docx-diagram"`. This covers
Word/DOCX handoffs where SmartArt or diagram content survives as an explicit
review marker instead of becoming an ordinary CSS class span or disappearing
from the block editor.

Previous scenario:
`examples/wordpress-native-jats-figure-alt-handoff.php` exercises an
upstream-shaped JATS/XML Native figure packet for WordPress import review. It
parses the bounded `test/jats-reader.native` slice through NativeReader and
renders a WordPress image block whose nested paragraph Image target becomes
`src="foo.png"` and whose source figure body text becomes
`alt="alternative-decription"`. This covers article/XML import handoffs where
source figure alt text must survive as media metadata instead of becoming a
visible paragraph or an empty placeholder image.

Previous scenario:
`examples/wordpress-native-docx-vml-object-image-handoff.php` exercises an
upstream-shaped DOCX Native VML/object image packet for WordPress import
review. It parses the bounded `test/docx/image_vml_as_object.native` slice
through NativeReader and renders a WordPress image block whose EMF source is
tagged with `data-pandoc-source-format="emf"` while browser-native image
formats remain unflagged. This covers Word/DOCX handoffs where Office vector
or object images need a later media conversion decision without invoking
upstream Pandoc or activating DOCX ZIP/OpenXML package parsing.

Previous scenario:
`examples/wordpress-native-html-row-header-table-handoff.php` exercises an
upstream-shaped HTML-reader Native row-header table packet for WordPress import
review. It parses a bounded `test/html-reader.native` slice through
NativeReader and renders a WordPress core table where `RowHeadColumns 1`
promotes first-column body cells to `<th>` while ordinary data cells and
spanning summary cells stay `<td>`. This covers comparison, glossary, and
audit tables where source row headers must stay navigable in the block editor
without invoking upstream Pandoc at import time.

Previous scenario:
`examples/wordpress-native-odt-nested-list-continuation-handoff.php` exercises
an upstream-shaped ODT Native nested continued-list packet for WordPress import
review. It parses a bounded `test/odt/native/listContinueNumbering2.native`
slice through NativeReader and renders WordPress ordered-list blocks that
preserve split top-level `start` values, nested lower-alpha sublists,
interleaved text paragraphs, and opt-in source list-style/delimiter metadata
while dropping Pandoc's empty `Para []` separators. This covers ODT import
handoffs where legal, policy, or documentation lists continue across prose and
still need nested source marker details visible to block-editor reviewers
without activating OpenDocument ZIP/XML package parsing.

Previous scenario:
`examples/wordpress-native-odt-table-spans-handoff.php` exercises an
upstream-shaped ODT Native table-span packet for WordPress import review. It
parses a bounded `test/odt/native/tableWithSpans.native` slice through
NativeReader and renders a WordPress core table that preserves multi-row table
headers, header/body `rowspan` and `colspan` boundaries, and the combined
row+column body span while dropping Pandoc's trailing empty `Para []` packet.
This covers ODT import handoffs where spreadsheet-like merged cells must remain
reviewable in the block editor without activating OpenDocument ZIP/XML package
parsing.

Previous scenario:
`examples/wordpress-native-epub-default-list-handoff.php` exercises an
upstream-shaped EPUB Native styling packet for WordPress import review. It
parses a bounded `test/epub/formatting.native` slice through NativeReader,
preserves Pandoc's `DefaultStyle`/`DefaultDelim` ordered-list markers through
NativeWriter read-back, and renders the list as a plain WordPress `<ol>`
without inventing a concrete HTML `type` attribute. This covers EPUB handoffs
where source default-list semantics should stay distinct from decimal-list
semantics in reviewer packets without activating EPUB ZIP/package parsing.

Previous scenario:
`examples/wordpress-native-epub-math-handoff.php` exercises an
upstream-shaped EPUB Native MathML packet for WordPress import review. It
parses a bounded `test/epub/features.native` slice through NativeReader and
renders an opt-in metadata review block, the source XHTML marker, source EPUB
section divs, three display math spans, and one inline math span. This covers
EPUB import handoffs where source MathML equations must remain visibly
distinguishable as display or inline math in WordPress without invoking
upstream Pandoc or activating EPUB ZIP/package parsing.

Previous scenario:
`examples/wordpress-native-epub-section-handoff.php` exercises an
upstream-shaped EPUB Native section packet for WordPress import review. It
parses a bounded `test/epub/wasteland.native` slice through NativeReader and
renders an opt-in metadata review block, a cover image block, a source XHTML
marker, and nested section divs whose source ids/classes survive as safe HTML
attributes. This covers EPUB import handoffs where source spine/chapter
boundaries must remain reviewable in WordPress without invoking upstream
Pandoc or activating EPUB ZIP/package parsing.

Previous scenario:
`examples/wordpress-native-odt-reference-anchor-handoff.php` exercises
upstream-shaped ODT Native same-document reference packets for WordPress import
review. It parses the `test/odt/native/referenceToText.native` and
`test/odt/native/referenceToListItem.native` slices through NativeReader and
renders WordPress paragraph/list blocks with valid fragments such as
`#an-anchor` while preserving whitespace-containing source anchors in
`data-pandoc-source-id` and `data-pandoc-source-href`. This covers ODT import
handoffs where source anchors and list-item references must remain reviewable
without emitting invalid whitespace-containing HTML fragment ids.

Previous scenario:
`examples/wordpress-native-odt-list-continuation-handoff.php` exercises an
upstream-shaped ODT Native continued-list packet for WordPress import review.
It parses the `test/odt/native/listContinueNumbering.native` slice through
NativeReader and renders WordPress ordered-list blocks that preserve Pandoc's
continued `start` values while dropping empty Native paragraph separators from
the block output. This covers ODT import handoffs where list numbering should
survive conversion without adding blank paragraph artifacts to the editor.

Previous scenario:
`examples/wordpress-native-docx-table-caption-anchor-handoff.php` exercises an
upstream-shaped DOCX Native table-caption packet for WordPress import review.
It parses the `test/docx/table_captions_with_field.native` slice through
NativeReader and renders WordPress table blocks that keep Word-generated
`_Ref...` caption anchors as inline spans inside figcaptions while preserving
the surrounding "See Table" links. This covers DOCX import handoffs where
table fields and cross-references need stable in-page targets after conversion
to WordPress blocks without invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-native-docx-image-dimensions-handoff.php` exercises an
upstream-shaped DOCX Native image packet for WordPress import review. It parses
the `test/docx/image_no_embed.native` slice through NativeReader and renders a
WordPress image block that keeps the source media target, title, alt text, and
DOCX-derived `width`/`height` attributes visible as `data-pandoc-*` metadata
plus sanitized CSS dimensions. This covers DOCX import handoffs where source
image sizing should remain reviewable without emitting invalid raw HTML
dimension attributes or invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-native-docx-table-header-rowspan-handoff.php` exercises an
upstream-shaped DOCX Native table packet for WordPress import review. It parses
a bounded `test/docx/table_header_rowspan.native` slice through NativeReader
and renders a WordPress core table that keeps scientific DOCX column widths as
`colgroup` percentages while preserving multi-row header structure with
`rowspan`, `colspan`, strong header text, and inherited column alignment. This
covers DOCX import handoffs where Word tables use grouped header rows and
small relative widths that upstream Pandoc emits in scientific notation.

Previous scenario:
`examples/wordpress-native-docx-index-field-handoff.php` exercises
upstream-shaped DOCX Native empty index-field packets for WordPress import
review. It parses `test/docx/empty_field.native` through NativeReader and
renders WordPress paragraphs that keep the source index entry visible as
`data-pandoc-index-entry` while preserving imported links and decoded Haskell
string escapes. This covers DOCX import handoffs where migration reviewers
need source index terms before deciding whether they map to taxonomy terms,
custom fields, editorial notes, or dropped print-only artifacts.

Previous scenario:
`examples/wordpress-native-docx-document-properties-handoff.php` exercises
upstream-shaped DOCX Native document-property packets for WordPress import
review. It parses `test/docx/document-properties.native` through NativeReader
and renders an opt-in WordPress metadata review block that keeps title, author,
custom properties, keyword lists, nested custom maps, and raw HTML metadata
visible while escaping source HTML rather than executing it. This covers DOCX
import handoffs where migration reviewers need document properties and custom
metadata before deciding how they map to post fields, custom fields, taxonomy
terms, or audit notes.

Previous scenario:
`examples/wordpress-native-docx-custom-style-handoff.php` exercises
upstream-shaped DOCX Native custom-style packets for WordPress import review
packets. It parses `test/docx/custom_style.native` through NativeReader and
renders WordPress paragraphs plus a reviewer HTML block that keep Word inline
and block style names visible through `data-pandoc-custom-style` without
emitting raw upstream `custom-style` attributes. This covers DOCX import
handoffs where migration reviewers need to preserve source style boundaries
before deciding whether styles map to blocks, classes, or cleanup rules.

Previous scenario:
`examples/wordpress-native-docx-paragraph-change-handoff.php` exercises
upstream-shaped DOCX Native paragraph insertion/deletion markers for WordPress
import review packets. It parses `test/docx/paragraph_insertion_deletion_all.native`
through NativeReader and renders WordPress paragraphs that keep empty
paragraph-boundary `paragraph-insertion` and `paragraph-deletion` spans visible
through `data-pandoc-paragraph-change`, `data-pandoc-change-*`, and `datetime`
metadata without emitting raw upstream `author` or `date` attributes. This
covers DOCX import handoffs where split/merge paragraph review state needs to
remain inspectable in WordPress without invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-native-docx-raw-openxml-handoff.php` exercises
upstream-shaped DOCX Native raw OpenXML packets for WordPress import review
packets. It parses `test/docx/raw-bookmarks.native` and
`test/docx/raw-blocks.native` shapes through NativeReader and renders
WordPress paragraphs that keep bookmark boundary ids/names as
`data-pandoc-bookmark-*` attributes while rendering RawBlock OpenXML table
fragments as escaped reviewer code blocks. This covers DOCX import handoffs
where anchors and raw table fragments need to stay inspectable in WordPress
without executing or silently dropping source OpenXML.

Previous scenario:
`examples/wordpress-native-docx-review-spans-handoff.php` exercises
upstream-shaped DOCX Native review-span fixtures for WordPress import review
packets. It parses `test/docx/comments.native`,
`test/docx/track_changes_insertion_all.native`, and
`test/docx/track_changes_deletion_all.native` shapes through NativeReader and
renders WordPress paragraph blocks that keep comment ids/authors/dates as
`data-pandoc-comment-*` attributes while rendering tracked insertions and
deletions as `<ins>`/`<del>` with `data-pandoc-change-*` metadata. This covers
DOCX import handoffs where reviewers need comments and tracked edits visible
in WordPress without invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-native-docx-inline-formatting-handoff.php` exercises an
upstream-shaped DOCX Native fixture for WordPress import review packets. It
parses the `Pandoc (Meta {unMeta = fromList []}) [...]` wrapper used by
`test/docx/inline_formatting.native` and renders WordPress paragraph blocks
that preserve emphasis, strong/emphasis nesting, small caps, strikeout,
underline, superscript/subscript, and hard line breaks without invoking
upstream Pandoc. This covers DOCX import handoffs where a PHP migration
pipeline receives deterministic Native packets from earlier tooling and needs
to validate inline formatting before block conversion.

Previous scenario:
`examples/wordpress-native-upstream-structure-handoff.php` exercises an
upstream-shaped Native fixture for WordPress import review packets. It parses
DefinitionList, RawBlock, nested Div, and parenthesized table-section
constructors through `NativeReader` and renders WordPress definition-list HTML,
grouped raw HTML, and a core table block without invoking upstream Pandoc.
This covers Native packets produced from older HTML/DOCX-style imports where
table sections appear as `(TableHead ...)` and `(TableFoot ...)` constructor
arguments.

Previous scenario:
`examples/wordpress-native-string-escape-handoff.php` exercises Native packet
read-back for WordPress import review packets that contain Haskell numeric
escape separators before source IDs. It parses `\160\&42`-style Native strings
through `NativeReader` and renders WordPress paragraphs with the intended
nonbreaking spaces, so migration tooling does not corrupt bibliography years or
batch identifiers while validating a deterministic Native handoff without
invoking upstream Pandoc at import time.

Previous scenario:
`examples/wordpress-native-reader-handoff.php` exercises Native packet
read-back for WordPress import review packets. It emits a standalone Pandoc
Native AST, parses it through `NativeReader`, and renders WordPress heading,
paragraph/link, and table blocks, so migration tooling can validate a
deterministic Native handoff without invoking upstream Pandoc at import time.

Previous scenario:
`examples/wordpress-native-table-handoff.php` exercises Pandoc-style Native
writer output for WordPress import review packets that need deterministic table
structure. It emits a standalone `Pandoc` Native AST with metadata, a
captioned `Table`, column alignment/width specs, row-head columns, spanned
cells, a footer row, and nested block-cell review notes, so migration tooling
can compare table boundaries without invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-native-citation-metadata-handoff.php` exercises bounded
Pandoc Native citation packets from `test/markdown-citations.native`. It reads
a copied upstream-shaped Native fixture and emits WordPress citation spans with
visible citation text plus `data-pandoc-citation-*` metadata for ids, modes,
note numbers, prefixes, suffixes, grouped citation records, non-ASCII ids, and
note-contained citations, so citation-aware migration tooling can keep review
metadata without invoking upstream Pandoc or citeproc.

Previous scenario:
`examples/wordpress-native-citation-figure-handoff.php` exercises Pandoc-style
Native writer output for WordPress import review packets that need media and
citation boundaries. It emits a standalone `Pandoc` Native AST with metadata,
a source-media `Figure` carrying short and long captions plus image attributes,
and a `Cite` node with author-in-text and suppress-author citation records, so
a migration pipeline can capture deterministic figure/citation fixtures without
invoking upstream Pandoc or citeproc.

Previous scenario:
`examples/wordpress-native-review-packet-handoff.php` exercises Pandoc-style
Native writer output for WordPress import review-oracle packets. It emits a
standalone `Pandoc` Native AST with metadata, a source archive link, checklist
blocks, and escaped PHP code-block fixture text, so a migration pipeline can
capture deterministic Native fixtures without invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-plain-template-pipe-partial-fixture-handoff.php` exercises
Pandoc-style PlainText custom-template pipe recursion and `.txt` partial
resolution for WordPress import audit packets. It renders reviewer checks
through `pairs/reverse` before applying a `check-row.txt` partial, chomps and
uppercases owner metadata without blank leakage, romanizes milestone lists, and
keeps the PlainText body from leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-brace-partial-handoff.php`
exercises Pandoc-style PlainText custom templates with braced `${...}`
delimiters and indented bare partials for WordPress import audit packets. It
renders reviewer rows from metadata, emits a literal budget dollar with `$$`
plus braced interpolation, nests every line of an indented checklist partial,
and keeps the PlainText body from leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-diagnostics-handoff.php`
exercises Pandoc-style PlainText custom-template compile diagnostics for
WordPress import audit packets. It reports a malformed reviewer partial with a
derived partial path and line/column before the source body renders, so failed
notification, excerpt, search, or audit output does not leak source admin URLs.

Previous scenario: `examples/wordpress-plain-template-object-loop-handoff.php`
exercises Pandoc-style PlainText custom-template loops over nested object
fields for WordPress import audit packets. It emits reviewer routing rows from
`audit.reviewers`, resolves nested `it.name` fields, and keeps
PlainText-rendered body text from leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-pad-handoff.php`
exercises Pandoc-style PlainText custom-template multiline alignment for
WordPress import audit packets. It emits a fixed-width reviewer table whose
multiline notes stay aligned across rows, missing metadata cells remain visibly
blank but width-preserving, over-wide notes are not truncated, and
PlainText-rendered body text does not leak source admin URLs.

Previous scenario: `examples/wordpress-plain-template-loop-guard-handoff.php`
exercises Pandoc-style PlainText custom-template partial recursion handling
for WordPress import audit packets. It emits the upstream `(loop)` sentinel
when reviewer partials include each other, and still keeps PlainText-rendered
body text from leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-final-newline-handoff.php`
exercises Pandoc-style PlainText custom-template scalar newline handling for
WordPress import audit packets. It emits newline-terminated review fields
without adding spurious blank lines, preserves one intentional blank from a
double-newline review field, renders true/false metadata visibly, and keeps
PlainText-rendered body text from leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-branching-handoff.php`
exercises Pandoc-style PlainText custom-template branch directives for
WordPress import audit packets. It emits a standalone `$elseif$` escalation
block that selects the workflow queue without adding a blank line before the
selected branch, plus PlainText-rendered body text without leaking source admin
URLs.

Previous scenario: `examples/wordpress-plain-template-nesting-handoff.php`
exercises Pandoc-style PlainText custom-template nesting for WordPress import
audit packets. It emits a `$^$`-nested multiline review description with an
internal blank line, an aligned owner continuation line, an automatically
indented multiline summary variable, a nested multiline legal-hold partial, a
blank-line separated legal-hold conditional, and PlainText-rendered body text
without leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-align-handoff.php`
exercises Pandoc-style PlainText custom-template parameterized alignment pipes
for WordPress import audit packets. It emits padded batch metadata, a centered
workflow queue, fixed-width reviewer rows using left/right/center pipes, and
PlainText-rendered body text without leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-numbered-toc-handoff.php`
exercises Pandoc-style PlainText default-template numbered table-of-contents
handoff for WordPress import audit packets. It emits generated source section
numbers in TOC labels, keeps numbered `unlisted` audit headings visible, keeps
unnumbered appendix headings visible without numbering, preserves explicit
legacy section numbers, and leaves body headings plain for reviewer-facing
excerpts.

Earlier scenario: `examples/wordpress-plain-toc-handoff.php` exercises
Pandoc-style PlainText default-template table-of-contents handoff for WordPress
import audit packets. It emits a nested TOC before plain body text, respects a
bounded TOC depth, and strips source edit URLs, generated TOC anchors, source
link attributes, and code ticks from reviewer-facing TOC labels.

Earlier scenario: `examples/wordpress-plain-template-include-handoff.php`
exercises Pandoc-style PlainText template handoff for WordPress import audit
packets. It emits header-includes and include-before reviewer metadata ahead of
plain body text, then emits a metadata-derived include-after footer after the
body. Writer-variable values remain raw template text, while metadata block
values are rendered through PlainText semantics so source edit URLs and code
ticks do not leak into reviewer-facing excerpts.

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
The PlainText default-template table-of-contents slice is now represented too:
WordPress import audit packets can emit a TOC before body text when a plain
template requests one, nested headings are bounded by `tocDepth`, source edit
links and generated `toc-*` anchors are stripped from labels, code spans lose
backticks, and unlisted private headings stay out of the reviewer-facing TOC
unless a later numbering slice explicitly maps numbered unlisted behavior.
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
- Native Markdown reviewer handoff exports now also preserve Pandoc-style
  attributed inline code and bracketed spans. The handoff example writes
  source-review metadata as `[...]{#migration-span .review-span ...}`, emits
  reviewer/source code tokens as `` `wp_enqueue_script`{#enqueue-call .php
  data-source="batch-42"}``, keeps emoji spans as `:smile:`, and escapes a
  literal bang before following link/span syntax so source-review prose cannot
  turn into an unintended image.
- Native Markdown reviewer handoff exports now preserve Pandoc-style
  strikeout, superscript, subscript, math, raw TeX, and raw-attribute inline
  output. The handoff example writes reviewer cleanup text as
  `~~legacy TeX screenshot~~`, `H~2~`, `^*draft*^`, `$x \in y$<!-- -->2`,
  `\cite[22-23]{smith.1899}`, and `` `<outline .../>`{=opml}``, keeping
  editorial packets parseable by Pandoc-compatible Markdown tooling without
  shelling out to the upstream binary.
- Native Markdown reviewer handoff exports now also preserve Pandoc-style
  quoted, underline, and small-caps inline writer output. The handoff example
  writes source excerpts with Markdown quote delimiters and sends editorial
  style markers as `[manual underlines]{.underline}` and
  `[source glossary]{.smallcaps}`, so reviewer packets keep source styling
  hints without inline HTML.
- Native Markdown reviewer handoff exports now preserve Pandoc-style mark spans.
  The handoff example writes highlighted source-review copy as
  `==verify source caption==` while escaping literal `==audit tokens==`, so
  imported reviewer packets can distinguish intentional highlights from source
  text that merely contains equal-sign markers.
- Native Markdown reviewer handoff exports now preserve Pandoc-style citation
  writer output. The handoff example writes author-in-text review citations as
  `@migration-audit [p. 12; see @source-log ch. 4]` and suppress-author
  entries as `[-@{legacy key}, appendix]`, so exported editorial packets keep
  citeproc-ready review markers without shelling out to Pandoc.
- Native Markdown reviewer handoff exports now also map Pandoc's raw HTML
  fallback for attributed links and images when Markdown link attributes are
  disabled. `examples/wordpress-markdown-raw-html-fallback.php` demonstrates a
  reviewer edit link and media preview emitted as raw `<a>`/`<img />` HTML with
  id, class, title, alt, and `data-source` metadata, preserving WordPress review
  context for downstream Markdown profiles that cannot carry Pandoc attribute
  tuples.
- Native Markdown reviewer handoff exports now also map Pandoc's raw
  HTML/native-span fallback for attributed spans when bracketed span attributes
  are disabled. `examples/wordpress-markdown-raw-html-fallback.php` now
  demonstrates a scoped reviewer span emitted as raw `<span>` HTML beside the
  edit link and media preview, so WordPress migration packets can preserve
  source-scope ids/classes/data attributes for Markdown profiles without
  Pandoc bracketed spans.
- Native Markdown reviewer handoff exports now map Pandoc's underline and
  small-caps fallback toggles too. When downstream Markdown profiles disable
  bracketed spans, the raw-HTML fallback example emits reviewer underline as
  `<u>...</u>` and source-glossary small caps as
  `<span class="smallcaps">...</span>`; when raw HTML/native spans are both
  unavailable, the writer falls back to emphasis for underline and Pandoc-style
  uppercase `Str` text for small caps while preserving code tokens.
- Native Markdown reviewer handoff exports now follow Pandoc's nested-emphasis
  normalization too. The handoff example collapses a doubled source-review
  emphasis node to plain `source flag` text and drops empty source
  emphasis/strong markers, so review packets do not accidentally turn empty
  editorial placeholders into visible Markdown delimiters.
- Native Markdown reviewer handoff exports now follow Pandoc's disabled
  strikeout fallback as well. When a downstream Markdown profile disables
  strikeout syntax, the raw-HTML fallback example emits deleted source-review
  captions as `<s>legacy caption</s>` if raw HTML is available, and the writer
  can drop the strikeout wrapper to plain rendered content when raw HTML is
  unavailable.
- Native Markdown reviewer handoff exports now follow Pandoc's disabled
  superscript/subscript fallback as well. When a downstream Markdown profile
  disables script syntax, the raw-HTML fallback example emits compact reviewer
  annotations as `H<sub>2</sub>` and `x<sup>2</sup>` if raw HTML is
  available; when raw HTML is unavailable, the writer falls back to Pandoc's
  Unicode script digit/symbol output or parenthesized text for content that
  cannot be represented by the upstream script conversion table.
- Native Markdown reviewer handoff exports now follow Pandoc's smart-disabled
  quoted fallback as well. When a downstream Markdown profile disables smart
  punctuation, the raw-HTML fallback example emits reviewer source quotes as
  `&lsquo;legacy reviewer quote&rsquo;` and
  `&ldquo;migration excerpt&rdquo;` under `preferAscii`, while native Unicode
  curly delimiters are available when ASCII preference is off. Smart-disabled
  handoff text also leaves ordinary quotes, dash ranges, and ellipses literal
  instead of escaping them as smart-punctuation triggers.
- Native Markdown reviewer handoff exports now map Pandoc's `preferAscii`
  behavior for ordinary `Str` text too. The raw-HTML fallback example emits
  non-ASCII reviewer metadata as `R&eacute;sum&eacute;`, `&COPY;`, `&in;`,
  decimal `&#128512;`, `&ldquo;curly excerpt&rdquo;`, and `&mldr;`, so
  WordPress editorial packets can target ASCII-only Markdown channels without
  flattening source-review text or shelling out to Pandoc.
- Native Markdown reviewer handoff exports now map Pandoc's `LineBreak`
  writer option branches too. The review-handoff example emits an escaped
  line-break backslash by default so a source-review line stays attached to the
  editor continuation, while focused tests cover the two-space Markdown
  hard-break fallback and the plain-newline `hard_line_breaks` branch for
  downstream Markdown profiles.
- Native Markdown reviewer handoff exports now map Pandoc's raw-inline
  extension fallback order too. The review-handoff example preserves raw TeX
  citations, OPML source packets, and raw HTML reviewer markers with
  Pandoc-style raw-attribute Markdown by default; focused tests cover
  disabling `raw_attribute` so HTML and TeX can pass through literally when
  their target raw extensions are enabled, or be omitted when those extensions
  are disabled.
- Native Markdown reviewer handoff exports now map Pandoc's block-level
  RawBlock and Div fallbacks too. The review-handoff example emits a
  source-scoped WordPress review packet as fenced Div Markdown with
  `data-source` metadata, preserves an OPML review outline as a fenced
  raw-attribute block, and keeps a TeX review environment literal while tests
  cover raw-HTML/raw-TeX pass-through, raw-attribute fallback, unsupported raw
  block omission, native/raw HTML Div wrappers, and content-only Div fallback
  for constrained downstream Markdown profiles.
- Native Markdown reviewer handoff exports now map Pandoc's Figure fallback
  boundary too. The raw-HTML fallback example emits an attributed source-review
  `<figure>` with id, class, `data-source`, image title/alt, and caption
  metadata when a figure cannot be represented as an implicit Markdown image,
  while focused tests cover implicit figures, raw HTML fallback, fenced figure
  Divs, disabled implicit-figure output, and content-only degradation for
  constrained downstream Markdown profiles.
- Native Markdown reviewer handoff exports now map Pandoc's table fallback
  boundary too. Simple review tables can round-trip as pipe tables with
  alignment delimiters, inline caption content, and Pandoc caption attributes;
  spanned source-review tables fall back to raw HTML with table class,
  `data-source`, caption, colgroup widths, colspan, and cell alignment
  metadata; raw-disabled profiles can still get an approximate pipe table for
  simple spanned content; and fully constrained profiles receive Pandoc's
  `[TABLE]` placeholder plus caption/attrs instead of silently dropping the
  reviewer table.
- Native Markdown reviewer handoff exports now map the bounded Pandoc
  grid-table branch for source-review tables that need Markdown-native output
  but cannot be represented as pipe tables. `examples/wordpress-markdown-grid-table-handoff.php`
  emits a block-rich migration review queue with heading, paragraph, bullet
  list, hard line break, footer total, width-derived grid columns, alignment
  markers, and caption/source attrs without shelling out to Pandoc or falling
  back to raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's multiline-table
  branch for width-bearing simple-cell tables. `examples/wordpress-markdown-multiline-table-handoff.php`
  emits wrapped Data Liberation review notes as Pandoc-style multiline
  Markdown with headed full borders, width-derived alignment, multiline source
  cells, and caption/source attrs, keeping reviewer packets Markdown-native
  without degrading to raw HTML or pipe syntax when multiline tables are
  available.
- Native Markdown reviewer handoff exports now map Pandoc's spanned
  grid-table branch. `examples/wordpress-markdown-spanned-grid-table-handoff.php`
  emits a migration review queue with row-spanned media areas, colspanned
  remediation status cells, partial horizontal-rule gaps, double head/body
  boundaries, caption/classes/source attrs, and no raw HTML fallback while
  `grid_tables` is available. Raw HTML and approximate pipe fallbacks are still
  available for constrained downstream Markdown profiles that disable grid
  tables.
- Native Markdown reviewer handoff exports now map Pandoc's simple-table
  branch for widthless simple-cell tables. `examples/wordpress-markdown-simple-table-handoff.php`
  emits Data Liberation import totals as Pandoc-style simple table Markdown
  with right/left/center/default alignment padding, caption/classes/source
  attrs, and no pipe/raw HTML fallback while `simple_tables` is available.
  Disabling `simple_tables` still gives pipe syntax when `pipe_tables` is
  available, and disabling both falls through to a multiline Pandoc table
  before raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's display-width
  `numChars` branch for widthless simple tables.
  `examples/wordpress-markdown-unicode-table-width-handoff.php` emits
  multilingual WordPress import labels with CJK wide characters plus
  zero-width joiner/non-joiner source tokens as aligned native Pandoc
  simple-table Markdown, so Data Liberation reviewer packets do not need a raw
  HTML table fallback just to preserve readable column alignment.
- Native Markdown reviewer handoff exports now map Pandoc's width-constrained
  pipe-table branch. `examples/wordpress-markdown-pipe-width-handoff.php`
  emits a narrow migration review queue with relative delimiter widths derived
  from source column hints, unpadded over-wide reviewer notes, stable alignment
  markers, caption/classes/source attrs, and no raw HTML fallback while
  `pipe_tables` is available.
- Native Markdown reviewer handoff exports now map Pandoc's positional
  default-width pipe-table branch too.
  `examples/wordpress-markdown-pipe-default-width-handoff.php` emits a narrow
  multilingual WordPress import queue where a default-width label column keeps
  its zero-width delimiter slot and later 25 percent/75 percent reviewer
  columns keep their own relative delimiter widths. This prevents a source
  label column from stealing the review-note column's width hint when
  downstream Markdown profiles need pipe tables instead of raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's table-caption
  `WrapAuto` branch for constrained pipe-table output.
  `examples/wordpress-markdown-pipe-caption-wrap-handoff.php` emits a narrow
  WordPress import review queue where a long caption wraps under
  `writerColumns` while the caption attribute tuple stays attached to the
  caption block. `wrap=none` and `hardLineBreaks` retain no-wrap output, so
  downstream Markdown handoff profiles can choose between readable wrapped
  captions and source-preserving captions without invoking Pandoc.
- Native Markdown reviewer handoff exports now map Pandoc's disabled
  `table_captions` / CommonMark caption-marker boundary too.
  `examples/wordpress-markdown-commonmark-caption-handoff.php` emits a
  CommonMark-flavored WordPress import review table where caption text and
  source attrs stay visible, but the Pandoc-specific leading `: ` marker is
  omitted. This keeps captions reviewable in downstream Markdown profiles that
  support pipe tables but not Pandoc table-caption syntax.
- Native Markdown reviewer handoff exports now map Pandoc's multiline-table
  `WrapAuto` minimum word-width branch. `examples/wordpress-markdown-multiline-wrap-handoff.php`
  emits a narrow import-token review queue where a long WordPress source token
  stays unbroken, normal reviewer notes wrap at word boundaries, caption/source
  attrs survive, and the handoff remains Pandoc-style multiline Markdown
  instead of raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's multiline-table
  `WrapNone` full-line-width branch. `examples/wordpress-markdown-multiline-nowrap-handoff.php`
  emits the same kind of import-token review queue for no-wrap editorial
  packets: source tokens and reviewer notes keep full cell-line widths,
  `hardLineBreaks` uses the same no-wrap table sizing boundary, caption/source
  attrs survive, and the handoff remains Pandoc-style multiline Markdown
  instead of raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's `SoftBreak`
  `WrapPreserve` branch. `examples/wordpress-markdown-wrap-preserve-handoff.php`
  emits a WordPress source-review packet where nonsemantic paragraph line
  boundaries stay visible for editorial audit under `wrap-preserve`, while
  default `WrapAuto`, explicit `WrapNone`, and the `hardLineBreaks` guard use
  Pandoc-compatible spacing instead of forcing preserved source wraps.
- Native Markdown reviewer handoff exports now map Pandoc's heading attribute
  branch. `examples/wordpress-markdown-heading-anchors-handoff.php` emits a
  WordPress review packet with custom Pandoc heading ids, classes, and
  source attrs for intra-document audit links, while duplicate imported
  auto-generated headings stay clean and do not receive redundant `{#...}`
  attributes.
- Native Markdown reviewer handoff exports now map Pandoc's fenced code-block
  attribute branch. `examples/wordpress-markdown-fenced-code-handoff.php`
  emits a shortcode cleanup snippet as fenced PHP code with a stable Pandoc
  block id, language/numbering classes, source batch metadata, and start-line
  metadata so downstream review packets keep snippet provenance without
  shelling out to Pandoc or downgrading to raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's DefinitionList
  writer branch. `examples/wordpress-markdown-definition-list-handoff.php`
  emits editable import glossary/checklist terms as Pandoc-style definition
  list Markdown with repeated tight definitions, loose nested shortcode
  snippet provenance, source attrs, and an adjacent reviewer-packet definition
  list separated by Pandoc's neutral `<!-- -->` block separator, keeping
  reviewer packets Markdown-native before WordPress block conversion without
  merging separate glossary/checklist sections.
- Native Markdown reviewer handoff exports now map Pandoc's adjacent same-type
  list separator branch. `examples/wordpress-markdown-adjacent-list-handoff.php`
  emits separate bullet and ordered reviewer queues with neutral `<!-- -->`
  separators between same-type list blocks, so a downstream Pandoc/WordPress
  import does not merge review phases that should remain separate editorial
  queues.
- Native Markdown reviewer handoff exports now map Pandoc's RawBlock/Plain
  `fixBlocks` tight-boundary branch.
  `examples/wordpress-markdown-raw-boundary-handoff.php` emits plain
  WordPress source-review notes adjacent to raw HTML review cards without
  inserting extra blank Markdown blocks, while a following review heading still
  receives normal block separation. This keeps trusted source-review HTML
  packets attached to surrounding editorial notes for downstream Pandoc or
  WordPress import.
- Native Markdown reviewer handoff exports now map Pandoc's in-list
  Plain-before-fenced-Div `fixBlocks` branch.
  `examples/wordpress-markdown-list-div-handoff.php` emits a WordPress
  reviewer source note as a real list item, then separates the following
  fenced review Div with Pandoc-compatible loose spacing. This prevents source
  review packets from becoming a dangling list marker while keeping the Div
  grouped with the same import checklist item.
- Native Markdown reviewer handoff exports now map the ordered-list
  paragraph-before-fenced-Div boundary.
  `examples/wordpress-markdown-ordered-list-div-handoff.php` emits a
  three-digit review checklist item whose following fenced source-metadata Div
  keeps the paragraph/Div blank block boundary and uses marker-width
  continuation indentation. This keeps long WordPress import step numbers from
  pushing reviewer packet Divs out of the intended checklist item.
- Native Markdown reviewer handoff exports now map Pandoc's Plain/Para
  marker-escaping branch. `examples/wordpress-markdown-plain-marker-handoff.php`
  emits literal WordPress source labels such as `1.`, `(2)`, `-`, and `%` with
  Pandoc-compatible escapes, so downstream Markdown re-read keeps them as
  reviewer paragraphs rather than ordered lists, bullet lists, or title-block
  metadata. Nested reviewer paragraphs inside checklist items receive the same
  guard.
- Native Markdown reviewer handoff exports now map Pandoc's CommonMark
  `RawInline` and `LineBreak` variant behavior.
  `examples/wordpress-markdown-commonmark-raw-handoff.php` emits
  CommonMark-compatible raw inline source spans and raw HTML review markers
  literally, keeps Markdown-only raw inline formats out of the default
  CommonMark handoff, and forces backslash hard breaks even when the generic
  escaped-line-break option is disabled. This keeps WordPress review packets
  compatible with CommonMark-oriented downstream tools while preserving trusted
  source annotations.
- Native Markdown reviewer handoff exports now map Pandoc's CommonMark
  `RawBlock` variant behavior.
  `examples/wordpress-markdown-commonmark-raw-block-handoff.php` emits
  block-level CommonMark-compatible source HTML literally, applies Pandoc's
  raw HTML blank-line escaping so strict CommonMark review packets do not gain
  accidental blank HTML block breaks, and omits GitHub-only raw Markdown unless
  a raw-attribute-capable profile is selected.
- Native Markdown reviewer handoff exports now map Pandoc's Markdown writer
  `LineBlock` branch.
  `examples/wordpress-markdown-line-block-handoff.php` emits a source-review
  stanza as pipe-prefixed Pandoc line-block Markdown, preserving indentation as
  nonbreaking spaces and keeping an empty line entry visible. This gives
  migration reviewers an editable Markdown handoff for poems, addresses, logs,
  and source excerpts before conversion into WordPress paragraph blocks.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  `LineBlock` branch.
  `examples/wordpress-plain-line-block-handoff.php` emits the same kind of
  source-review stanza without Pandoc pipe markers, while preserving
  nonbreaking source indentation and empty line entries. This gives WordPress
  import tools a native plain-text path for excerpts, notification emails,
  search snippets, and audit logs without shelling out to Pandoc.
- Native plain-text reviewer handoff exports now map additional Pandoc
  `writePlain` block branches.
  `examples/wordpress-plain-review-blocks-handoff.php` emits unmarked plain
  headings, source paragraph labels without Markdown link markup,
  two-space-indented quote notes, literal plain raw review packets, and
  writer-column dash separators. This gives WordPress import tools a native
  plain-text packet for excerpts, notification emails, search snippets, and
  audit logs where Markdown markup would leak into reviewer-facing text.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  list and DefinitionList branches.
  `examples/wordpress-plain-definition-list-handoff.php` emits import
  glossary/checklist terms without Markdown emphasis or link markers, uses the
  upstream PlainText two-space definition leader instead of `:` markers, and
  keeps nested shortcode/code and quote review notes visibly indented. This
  gives WordPress import tools a plain-text glossary/audit path for excerpts,
  notification emails, search snippets, and reviewer logs without shelling out
  to Pandoc or leaking Markdown syntax to non-technical reviewers.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  image and note branches.
  `examples/wordpress-plain-media-note-handoff.php` emits plain media-review
  packets with bracketed image captions, numeric note references, stripped
  source edit link text inside note bodies, and indented code-note snippets.
  This gives WordPress import tools a native plain-text media audit path for
  excerpts, notification emails, search snippets, and reviewer logs without
  leaking Markdown image or footnote syntax.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  Gutenberg inline branch too.
  `examples/wordpress-plain-gutenberg-handoff.php` emits Gutenberg-oriented
  plain review text where strong reviewer status becomes Unicode-safe
  uppercase text, source edit links are stripped to their labels, code tokens
  such as `wp_update_post` stay literal, and emphasis remains visible with
  underscore delimiters. This gives WordPress import tools a native plain-text
  path for Gutenberg excerpts, notification emails, search snippets, and audit
  logs where strong review statuses need to stand out without leaking Markdown
  link destinations.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  table cell and caption fallback branch too.
  `examples/wordpress-plain-table-fallback-handoff.php` emits an unsupported
  spanned import table as a visible `[TABLE]` review marker with a plain source
  caption and attrs. Strong/link/code markup and admin URLs are stripped from
  reviewer-facing table text, giving WordPress import tools a plain-text audit
  path for excerpts, notifications, search snippets, and logs when an import
  table cannot be represented faithfully without raw HTML.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  template titleblock branch.
  `examples/wordpress-plain-titleblock-handoff.php` emits a Data Liberation
  import audit packet with title, semicolon-joined authors, and date metadata
  ahead of body text. Metadata inlines use PlainText semantics, so source admin
  links, Markdown emphasis, and code ticks are stripped before excerpts,
  notifications, search snippets, or audit logs reach non-technical reviewers.
- Native plain-text reviewer handoff exports now map Pandoc's default plain
  template table-of-contents branch.
  `examples/wordpress-plain-toc-handoff.php` emits a nested TOC before plain
  body text for import audit packets. TOC labels keep reviewer-visible heading
  text while stripping source edit URLs, generated `toc-*` anchors, source
  link attributes, and code ticks; `tocDepth` keeps private deeper headings
  out of short reviewer packets.
- Native plain-text reviewer handoff exports now map Pandoc's numbered default
  plain template table-of-contents branch too.
  `examples/wordpress-plain-numbered-toc-handoff.php` emits generated source
  section numbers in TOC labels, keeps numbered `unlisted` audit headings
  visible, keeps `unnumbered` appendix headings visible without advancing
  counters, and preserves explicit legacy section numbers while leaving body
  headings plain. This gives WordPress import tools a shell-free audit packet
  for source traceability when reviewers need numbered legacy sections in the
  excerpt, notification, search snippet, or log output.
- Native plain-text reviewer handoff exports now map Pandoc's default plain
  template `body` context override branch.
  `examples/wordpress-plain-body-override-handoff.php` emits a redacted
  WordPress import audit packet where a template-provided body replaces the
  converted source body while metadata `include-after` footer text still
  follows it. Metadata body values render through PlainText semantics when no
  writer body variable is supplied, while writer variables remain raw template
  values and take precedence. This gives WordPress import tools a shell-free
  handoff for approved notification/search/audit text without losing the
  source conversion body in the underlying import record.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template branch syntax slice.
  `examples/wordpress-plain-template-branching-handoff.php` emits a custom
  WordPress plain import branch packet with dotted workflow metadata, a
  fallback `$else$` branch, a standalone `$elseif$` escalation block that
  swallows the branch newline before selecting the workflow queue,
  comma-separated reviewer recipients from `$for$`/`$sep$`, and body text
  rendered through PlainText semantics. This gives WordPress import tools a
  shell-free way to build notification, excerpt, and audit packets from
  structured metadata without leaking admin URLs into reviewer-facing body
  text or adding spurious blank lines to reviewer packets.
- Native plain-text reviewer handoff exports now map Pandoc custom template
  final-newline and boolean scalar rendering.
  `examples/wordpress-plain-template-final-newline-handoff.php` emits a custom
  WordPress reviewer packet where newline-terminated review fields do not add
  spurious blank lines, double-newline fields keep one intentional reviewer
  gap, and true/false metadata values render visibly for downstream audit
  packets. The source body still renders through PlainText semantics, so admin
  edit URLs are stripped before notification, excerpt, search, and audit
  output.
- Native plain-text reviewer handoff exports now map Pandoc custom template
  direct-list interpolation and the `space-in-loop` whitespace boundary.
  Compact reviewer lists or status fragments interpolated directly into custom
  WordPress audit packets concatenate without implicit paragraph gaps, while
  explicit loop bodies still preserve intentional blank lines between rendered
  values. Empty loops with blank bodies emit nothing, which prevents absent
  metadata sections from leaving stray whitespace in notification, excerpt,
  search, or audit output.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template `meta-json` branch.
  `examples/wordpress-plain-meta-json-template-handoff.php` emits a custom
  WordPress import audit packet whose template exposes metadata-only JSON,
  generated titleblock/body values, and a writer-variable preface override.
  Metadata block values inside `meta-json` render through PlainText semantics,
  so source edit links and code ticks are stripped from the JSON audit summary
  while the custom preface can remain raw reviewer text.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template nested-control branch.
  `examples/wordpress-plain-template-nested-handoff.php` emits a custom
  WordPress reviewer packet with scalar label loops through `it`, nested
  phase/reviewer loops, an `elseif` fallback status branch, a literal-dollar
  charge field, and omitted template comments before the PlainText body. This
  gives WordPress import tools a shell-free notification, excerpt, and audit
  packet path for structured migration workflow metadata without leaking
  template comments or admin-only source values.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template partial branch.
  `examples/wordpress-plain-template-partial-handoff.php` emits a custom
  WordPress reviewer packet assembled from a partial map: a reviewer-list
  partial applies a reviewer partial to each metadata entry with bracket
  separators, includes a nested footer partial, renders workflow metadata
  through the anaphoric `it` context, omits final partial newlines before the
  next template line, and leaves the body rendered through PlainText semantics.
  This gives WordPress import tools a shell-free way to share reviewer packet
  subtemplates across notification, excerpt, search, and audit outputs without
  leaking source admin URLs.
- Native plain-text reviewer handoff exports now map Pandoc custom template
  partial recursion guards.
  `examples/wordpress-plain-template-loop-guard-handoff.php` emits a custom
  WordPress reviewer packet where accidentally cyclic partials produce
  Pandoc's `(loop)` sentinel instead of disappearing or exhausting recursion.
  The converted source body still renders through PlainText semantics, so
  admin edit URLs are stripped before notification, excerpt, search, and audit
  output.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template pipe branch.
  `examples/wordpress-plain-template-pipes-handoff.php` emits a custom
  WordPress reviewer packet using MANUAL-documented no-parameter doctemplate
  pipes: status is lowercased, queue text is uppercased, labels are counted,
  sliced with first/last/rest/allbutlast, reversed with a bracket separator,
  and reviewer arrays are converted with `pairs` so one-based keys can be
  rendered through `alpha/uppercase`. The body still renders through PlainText
  semantics, so source edit links are stripped before notification, excerpt,
  search, and audit output.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template parameterized alignment pipe branch.
  `examples/wordpress-plain-template-align-handoff.php` emits a custom
  WordPress reviewer packet using MANUAL-documented `left`, `right`, and
  `center` pipes with positive widths and quoted borders. Batch metadata,
  workflow queue labels, reviewer counts, and status text are padded into
  predictable PlainText columns while the source body still renders through
  PlainText semantics, so admin edit URLs are stripped before notification,
  excerpt, search, and audit output.
- Native plain-text reviewer handoff exports now map Pandoc doctemplates
  `loop-in-object.test` behavior and the upstream partial nesting-depth guard.
  `examples/wordpress-plain-template-object-loop-handoff.php` emits a reviewer
  packet where a nested metadata object (`audit.reviewers`) is looped directly,
  anaphoric `it.name` fields resolve inside each reviewer, and the native
  partial guard follows doctemplates' level-50 boundary before emitting
  `(loop)`. The source body still renders through PlainText semantics, so admin
  edit URLs are stripped before notification, excerpt, search, and audit output.
- Native plain-text reviewer handoff exports now map Pandoc doctemplates
  `pad.test` multiline alignment behavior.
  `examples/wordpress-plain-template-pad-handoff.php` emits a reviewer packet
  table whose multiline notes compose line-by-line with adjacent aligned cells,
  whose blank metadata cells are vertically filled to preserve table shape, and
  whose over-wide legal-hold note is kept intact instead of being truncated.
  The source body still renders through PlainText semantics, so admin edit URLs
  are stripped before notification, excerpt, search, and audit output.
- Native plain-text reviewer handoff exports now map Pandoc custom template
  breakable spaces and the MANUAL `/nowrap` pipe.
  `examples/wordpress-plain-template-nowrap-handoff.php` emits a custom
  WordPress reviewer packet where a breakable editorial summary wraps at a
  narrow PlainText column, a legal-hold source reference stays on one line
  through `/nowrap`, a readiness marker trims trailing breakable space through
  `/chomp`, and the source body still renders through PlainText semantics
  without leaking admin edit URLs.
- Native plain-text reviewer handoff exports now map Pandoc custom template
  nesting.
  `examples/wordpress-plain-template-nesting-handoff.php` emits a custom
  WordPress reviewer packet where `$^$` keeps multiline review descriptions and
  legal-hold partials aligned with their labels, preserves internal blank
  template lines without indentation-only output, keeps a following owner line
  at the same nesting level, and lets an indented multiline summary variable
  nest automatically. The source body still renders through PlainText
  semantics, so admin edit URLs are stripped before excerpts, notification
  emails, search snippets, and audit logs.
- Markdown reviewer handoff exports now map Pandoc wiki-link writer variants.
  `examples/wordpress-markdown-wikilink-writer-handoff.php` emits compact
  `[[target|title]]` and `[[title|target]]` shortcuts for migration runbooks,
  editorial checklist pages, and legacy wiki plugins while preserving regular
  Markdown links when extra source attributes would otherwise be lost. This
  gives WordPress migration tooling a shell-free reviewer Markdown path for
  old wiki-style cross-document shortcuts.
- Markdown reviewer handoff exports now map Pandoc standalone table-of-contents
  writer behavior.
  `examples/wordpress-markdown-toc-handoff.php` emits a standalone reviewer
  Markdown packet for a WordPress migration batch with Pandoc-style TOC links,
  duplicate-safe generated fragments, source/media/publish sections, and
  interior scratch headings kept in the body but omitted from the TOC. This
  gives migration tooling a shell-free outline packet for editorial review
  before content is converted into blocks.
- LaTeX reviewer export now maps Pandoc's bounded LaTeX writer math pipe
  behavior.
  `examples/wordpress-latex-math-handoff.php` emits a WordPress import review
  equation as LaTeX `\(...\)`/`\[...\]` output while also showing the
  WordPress block math spans used by the import handoff. This gives migration
  tooling a shell-free way to preserve inline/display equation source during
  editorial review without activating a PDF or TeX engine dependency gate.
- Native DOCX reviewer handoff exports now map Pandoc DOCX notes and
  links-inside-notes fixtures.
  `examples/wordpress-native-docx-notes-handoff.php` reads copied upstream
  Native fixtures for `notes.native` and `link_in_notes.native`, combines them
  into one document, and emits WordPress endnotes with backlinks while
  preserving the source link inside the final note. This gives document-import
  tooling a shell-free review path for DOCX footnote/endnote handoff before
  the DOCX ZIP/OpenXML dependency gate is opened.
- `examples/wordpress-literate-haskell.php` demonstrates source-documentation
  imports that opt into Pandoc's literate Haskell extension. Bird-track and
  inverse-bird-track snippets become WordPress code blocks with Haskell
  language classes, ordinary indented source remains code, and reviewer notes
  written as one-space-indented block quotes stay WordPress quote blocks instead
  of being misclassified as literate source.
- Native AST reviewer handoff exports now map a bounded Pandoc Native writer
  boundary from `Tests.Writers.Native` plus upstream native figure/citation
  fixtures.
  `examples/wordpress-native-docx-task-list-handoff.php` reads a copied
  upstream DOCX Native `task_list` fixture and emits reviewer-safe WordPress
  task-list checkboxes through an opt-in `taskGlyphsAsCheckboxes` mode while
  leaving default output faithful to Pandoc's source ballot glyph text. This
  gives document-import tooling a shell-free way to turn DOCX checklist glyphs
  into actionable review controls without losing Native read-back evidence.
  `examples/wordpress-native-odt-mixed-list-handoff.php` reads a copied
  upstream ODT Native `orderedListMixed` fixture and emits WordPress ordered
  lists where the default handoff remains clean HTML while reviewer mode
  preserves the source LowerAlpha/OneParen marker as `data-pandoc-list-style`
  and `data-pandoc-list-delimiter`. This gives document-import tooling a
  shell-free way to keep source list marker evidence for editorial review when
  HTML cannot display Pandoc's one-parenthesis delimiter directly.
  `examples/wordpress-native-odt-image-caption-handoff.php` reads a copied
  upstream ODT Native `imageWithCaption` fixture and emits a WordPress image
  block with the source `Pictures/...jpg` target, ODT width/height metadata,
  the original image alt label, and the Native figure caption as the visible
  figcaption. This gives document-import tooling a reviewer-safe ODT image
  handoff without shelling out to Pandoc or activating OpenDocument package
  parsing.
  `examples/wordpress-native-citation-figure-handoff.php` emits a standalone
  Pandoc Native reviewer packet with sorted metadata, a source-media `Figure`
  carrying short/long captions, Image metadata, and `Cite`/`Citation` records
  for author-in-text and suppress-author citation boundaries. This gives
  WordPress migration tooling a deterministic Native AST oracle for media and
  source-citation review without shelling out to Pandoc or citeproc.
  `examples/wordpress-native-review-packet-handoff.php` emits a standalone
  Pandoc Native reviewer packet with sorted metadata, source-link inlines,
  checklist blocks, and escaped PHP code-block fixture text. This gives
  WordPress migration tooling a deterministic Native AST oracle for import
  review without shelling out to Pandoc.
  `examples/wordpress-native-reader-handoff.php` emits a Native packet, parses
  it through `NativeReader`, and renders WordPress heading, paragraph/link, and
  table blocks. This gives WordPress migration tooling a deterministic
  read-back handoff for Native packets without shelling out to Pandoc at import
  time.
  `examples/wordpress-native-upstream-structure-handoff.php` reads a copied
  upstream-shaped Native fixture with DefinitionList, RawBlock, nested Div, and
  parenthesized table sections, then renders reviewer-facing WordPress
  definition-list, raw-HTML grouping, and table blocks without shelling out to
  Pandoc.
- HTML reader reviewer exports now map Pandoc's `pLineBlock` branch.
  `examples/wordpress-native-html-line-block-handoff.php` reads an upstream
  shaped HTML line-block fixture and emits a WordPress paragraph handoff where
  reviewer stanzas keep hard line breaks, an intentionally empty line, NBSP
  indentation, and the source edit link. This gives migration tooling a
  shell-free import path for line-block HTML generated by Pandoc/DocBook-style
  sources without activating broader HTML5 DOM, package, PDF, citation, or
  math-conversion dependency gates.
- HTML writer preview exports now map Pandoc's span-like class lowering branch.
  `examples/wordpress-html-writer-spanlike-handoff.php` emits a reviewer-facing
  HTML preview where keyboard shortcuts, marked publish-preview text, and
  abbr/dfn source terminology lower to real HTML tags before the preview is
  wrapped in a WordPress HTML block. This gives migration tooling a shell-free
  way to preserve semantic editorial source notes without activating package,
  PDF, citation, math-conversion, or syntax-highlighting dependency gates.
- HTML writer preview exports now map Pandoc's styled inline constructor
  branches.
  `examples/wordpress-html-writer-styled-inline-handoff.php` emits a
  reviewer-facing HTML preview where underline, deletion, small-caps,
  subscript, and superscript marks remain visible before the preview is wrapped
  in a WordPress HTML block. This gives migration tooling a shell-free way to
  preserve editorial marks and formula-style annotations during review without
  activating broader DOCX/ODT/PDF, citation, math-conversion, or syntax
  highlighting dependency gates.

## Next Task

Decide whether the next rich-format gate should activate shared ZIP/OpenXML
support for DOCX/EPUB ingestion; otherwise extend another bounded upstream
command or `.native` fixture slice such as DOCX scrubbed metadata, move
tracking accept/reject variants, VML image packets, ODT tableWithContents,
HTML writer span-like class lowering, or another MarkdownWriter extension
branch.
