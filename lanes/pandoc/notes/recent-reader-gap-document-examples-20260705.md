# Recent Reader Gap Document Examples

Scope: recently hardened reader formats only: Markdown, HTML, EPUB, PPTX, XLSX, CSV, TSV, man, and mdoc.

This is a planning inventory, not a new fixture set. The rows below name documents that should be good inputs for upstream Pandoc, but are likely to fail, degrade, or lose important semantics in the current PHP port because the local reader is a bounded/pinned slice, because the data is currently recorded only as review metadata, or because the format option matrix is not fully represented.

## Markdown

- MD-01: `markdown_strict` document with raw HTML blocks, inline HTML, and autolinks that must remain literal. Pandoc applies the strict extension set; the PHP reader uses the shared Markdown path and does not fully prove the strict disabling matrix.
- MD-02: `commonmark_x` document combining attributes, footnotes, tables, task lists, raw HTML, and smart punctuation. Pandoc has a dedicated CommonMark extension profile; the PHP reader covers many slices but not the full option matrix.
- MD-03: GFM document with nested task lists inside block quotes and lazy continuations. Pandoc's GFM reader handles task-list continuation rules; the PHP port has targeted task-list/list slices but not the complete nesting grammar.
- MD-04: MultiMarkdown title block with repeated authors, affiliations, keywords, and date metadata. Pandoc maps MMD metadata into Pandoc metadata; the PHP port has MMD-adjacent slices but no complete MMD metadata model.
- MD-05: MultiMarkdown reference-style image attributes with width, height, id, class, and key/value pairs. Pandoc supports the MMD reference-image attribute behavior; the PHP port has targeted fixture coverage, not the complete MMD profile.
- MD-06: PHP Markdown Extra document with abbreviations, definition lists, fenced divs, and table attributes in the same file. Pandoc composes those extensions; the PHP reader has separate focused slices and can miss cross-extension precedence.
- MD-07: Citation-heavy Markdown with author-in-text citations, suppress-author citations, prefixes, suffixes, locators, and adjacent notes. Pandoc's citation parser and CSL handoff are mature; the PHP AST stores only bounded citation shapes.
- MD-08: Markdown with raw TeX macro definitions that change later math parsing. Pandoc tracks LaTeX macros through the reader; the PHP port handles bounded raw TeX/math cases and does not fully emulate macro expansion state.
- MD-09: Grid table with row spans, column spans, block-level cell contents, and a long caption. Pandoc has full table readers; the PHP reader has many table fixtures but not the full table grammar.
- MD-10: Multiline table whose wrapped cell text contains inline code, links, and escaped pipe characters. Pandoc handles the full multiline/pipe table parser; the PHP reader has table slices but more limited ambiguity coverage.
- MD-11: Markdown-in-HTML document with nested `<section>`, `<div markdown="1">`, and block-level Markdown content. Pandoc has markdown-in-HTML extension behavior; the PHP path delegates through HTML/Markdown bridges with bounded cases.
- MD-12: Literate Haskell document mixing bird tracks, inverse bird tracks, Markdown lists, and fenced code. Pandoc has a literate Haskell reader mode; the PHP reader covers selected LHS fixtures, not full mode parity.
- MD-13: Document using all four link syntaxes plus duplicate reference labels and multiline titles. Pandoc has reference normalization and precedence rules; the PHP reader has targeted reference-link cases, not every collision rule.
- MD-14: Unicode heading identifiers with emoji, combining marks, CJK text, and duplicate auto identifiers. Pandoc's auto-identifier logic is format/profile aware; the PHP port has selected auto-id slices.
- MD-15: East Asian line-break extension document with CJK hard-wrap paragraphs and inline emphasis boundaries. Pandoc has the extension; the PHP reader has profile fixtures but not full layout parity.
- MD-16: Smart punctuation document with French quotes, apostrophes, nested notes, and ellipses. Pandoc's smart parser handles locale-sensitive punctuation; the PHP port has selected smart punctuation tests.
- MD-17: Footnote document with Unicode labels, escaped brackets, nested definitions, and block quotes inside notes. Pandoc handles reference-label balancing broadly; the PHP reader has targeted label-balance slices.
- MD-18: Numbered examples with references crossing lists, block quotes, and definition lists. Pandoc has numbered-example state handling; the PHP reader covers selected numbered-example fixtures.
- MD-19: Raw HTML boundary document with invalid comments, partial tags, and raw HTML splitting across list items. Pandoc has exact raw HTML block rules; the PHP reader has many boundary cases but not the complete state machine.
- MD-20: Mixed subscript/superscript document whose syntax changes across pandoc, MMD, and strict profiles. Pandoc applies per-format extension defaults; the PHP shared reader does not fully prove all dialect toggles.

## HTML

- HTML-01: Full HTML document with complex `<head>` metadata, multiple `meta` names, OpenGraph tags, and language precedence. Pandoc imports selected metadata into Pandoc metadata; the PHP reader records only bounded metadata and microdata summaries.
- HTML-02: HTML page with a large microdata graph using more than 32 items and more than 64 properties per item. Pandoc can ignore or process visible content without those local caps; the PHP reader caps microdata review metadata.
- HTML-03: HTML document with deeply nested `itemref` chains across distant elements. Pandoc's visible-content parse is not constrained by local itemref review limits; the PHP reader may emit missing-itemref/limit diagnostics.
- HTML-04: Complex table using foster parenting, omitted tags, colgroups, row groups, rowspans, colspans, and text outside table sections. Pandoc's HTML reader handles many table repairs; the PHP reader has focused table repair cases, not full semantic parity.
- HTML-05: Form-heavy document with labels, fieldsets, inputs, select/option state, and visible fallback text. Pandoc reads visible text and structure; the PHP path has review-oriented form handling and may not produce the same Pandoc AST.
- HTML-06: Custom elements with attributes and nested flow content. Pandoc can keep raw/native HTML as appropriate; the PHP reader tends toward bounded structural/text extraction.
- HTML-07: SVG document embedded inline with `foreignObject`, HTML descendants, text nodes, and links. Pandoc's raw/foreign-content handling differs by extension; the PHP reader has selected foreign-content coverage.
- HTML-08: MathML document with `semantics`, multiple `annotation-xml` nodes, and fallback TeX annotations. Pandoc has MathML-specific reader logic; the PHP reader has selected MathML fixtures and review metadata.
- HTML-09: Ruby-heavy Japanese document with nested `ruby`, `rt`, `rp`, and inline emphasis. Pandoc handles ruby annotations; the PHP reader covers generated ruby text cases but not every nesting shape.
- HTML-10: Media document with `<picture>`, multiple `source` candidates, `srcset`, `sizes`, and fallback image. Pandoc maps image semantics; the PHP reader flattens picture containers and can lose candidate details.
- HTML-11: Audio/video document with tracks, captions, fallback paragraphs, posters, and local resources. Pandoc can preserve raw/media references; the PHP reader exposes bounded fallback/resource metadata.
- HTML-12: Iframe `srcdoc` document with nested HTML and local links. Pandoc can preserve raw HTML or visible content depending on options; the PHP reader review path makes iframe content inert.
- HTML-13: Nested `template` and declarative shadow DOM document. Pandoc's raw HTML handling can preserve template markup; the PHP reader flattens selected template content for reader visibility.
- HTML-14: Details/summary document with closed disclosure content that should stay visible in Pandoc conversion. Pandoc parses the fallback body; the PHP path has review-oriented disclosure metadata.
- HTML-15: Sectioning document where headings inside native divs interact with `makeSections`. Pandoc has sectioning logic; the PHP reader has targeted native-div cases and filters headers in the local round-trip property.
- HTML-16: HTML with base URL changes and mixed relative, root-relative, and scheme-relative links. Pandoc resolves base URLs in reader logic; the PHP reader has selected base cases but not full URL policy parity.
- HTML-17: Raw-text document with script/style/textarea/xmp/plaintext edge cases and malformed close tags. Pandoc has raw/RCDATA behavior; the PHP reader has bounded fallback containers.
- HTML-18: Accessibility-rich document with ARIA roles, landmarks, hidden/inert content, and translated regions. Pandoc mostly reads visible structure; the PHP reader records many attributes as review metadata rather than equivalent AST structure.
- HTML-19: Document containing comments, declarations, and processing-instruction-looking text in unusual positions. Pandoc's parser behavior is mature; the PHP reader has preflight and sanitizer constraints.
- HTML-20: HTML with citations encoded as CSL classes, `data-cites`, biblioref links, and a references section. Pandoc has citation-aware HTML behavior; the PHP reader handles selected citation spans but not full citation/references parity.

## EPUB

- EPUB-01: EPUB2 package with NCX-only table of contents and no EPUB3 nav document. Pandoc reads NCX navigation; the PHP reader records NCX/nav metadata but prefers nav and has bounded TOC use.
- EPUB-02: EPUB3 package with multiple nav sections: toc, landmarks, page-list, loa, lot, and custom navigation. Pandoc handles navigation documents for conversion; the PHP reader records auxiliary nav metadata, not full content semantics.
- EPUB-03: Package with several non-linear spine items that still contain appendices or notes users expect in output. Pandoc has established spine handling; the PHP reader skips `linear="no"` items.
- EPUB-04: Spine item whose media type is SVG, not XHTML. Pandoc can process more EPUB resource shapes through its reader stack; the PHP reader only directly emits selected image spine media and readable XHTML.
- EPUB-05: XHTML spine with MathML equations and annotation fallbacks. Pandoc's EPUB reader feeds the full Pandoc HTML reader; the PHP reader uses the local bounded HTML path.
- EPUB-06: XHTML spine with inline SVG figures and fallback text. Pandoc can preserve raw/foreign content more faithfully; the PHP reader has selected raw/foreign-content handling.
- EPUB-07: EPUB with media overlays (`media-overlay` plus SMIL) for narrated text. Pandoc handles the book content cleanly; the PHP reader records only bounded package/link metadata and does not model synchronized overlays.
- EPUB-08: OPF metadata with refinements (`meta refines`) for title type, file-as, display-seq, alternate-script, and identifiers. Pandoc imports richer metadata; the PHP reader records bounded package metadata fields.
- EPUB-09: OPF package with multiple `dc:creator` entries and role refinements. Pandoc maps creator metadata into document metadata; the PHP reader has limited creator/metadata semantics.
- EPUB-10: EPUB with remote resources and a content `<base>` element changing link resolution per chapter. Pandoc resolves content resources through its EPUB/HTML pipeline; the PHP reader has selected base/link rewrites.
- EPUB-11: EPUB with `epub:type` footnotes/rearnotes split across multiple spine files and backreferences. Pandoc resolves notes through the HTML reader; the PHP reader fills selected empty EPUB footnotes but not every cross-file pattern.
- EPUB-12: EPUB2 `switch/case/default` content with fallback cases. Pandoc's EPUB reader handles legacy EPUB constructs; the PHP reader has bounded EPUB switch/raw wrapper behavior.
- EPUB-13: Package with guide references for cover, title-page, toc, text, bibliography, and glossary. Pandoc uses guide/nav information; the PHP reader records guide references as metadata.
- EPUB-14: XHTML with pagebreak spans and page-list anchors. Pandoc can use pagebreak semantics in output; the PHP reader primarily records navigation/page-list metadata.
- EPUB-15: Book with deeply nested sections and repeated IDs across spine items. Pandoc normalizes section/identifier behavior; the PHP reader adds spine markers and relies on local HTML sectioning.
- EPUB-16: EPUB with obfuscated or encrypted font/image resources. Pandoc may still convert visible XHTML while ignoring unavailable assets; the PHP reader media-bag path records missing/loaded local media only.
- EPUB-17: Package with unusual rootfile paths, dot segments, and percent-encoded spine hrefs. Pandoc has mature EPUB path resolution; the PHP reader has targeted package path handling.
- EPUB-18: EPUB with alternate renditions or multiple rootfiles in `container.xml`. Pandoc chooses a rendition; the PHP reader takes the first valid rootfile path.
- EPUB-19: XHTML chapters that rely on CSS counters or generated content for visible numbering. Pandoc may not render CSS, but its reader handles the prose cleanly; the PHP reader also ignores generated CSS content, so numbering can be missing.
- EPUB-20: EPUB with endnotes encoded as HTML lists without explicit `epub:type` but linked by conventions. Pandoc's HTML reader can still surface links/content; the PHP EPUB note filling depends on recognized references.

## PPTX

- PPTX-01: Deck with grouped shapes containing text boxes, images, and nested groups. Pandoc can read more shape text; the PHP reader records grouped-shape diagnostics and skips unsupported drawables.
- PPTX-02: Deck with connector shapes and labeled arrows. Pandoc can represent visible labels when extracted; the PHP reader records unsupported connector/contentPart drawable diagnostics.
- PPTX-03: Deck with charts whose data comes from embedded workbook parts. Pandoc's PPTX reader has chart-related handling; the PHP reader records chart metadata/placeholders rather than chart data as tables.
- PPTX-04: Deck with SmartArt using complex layouts not covered by the local hierarchy parser. Pandoc has SmartArt reader modules; the PHP parser handles selected hierarchy/layout cases.
- PPTX-05: Deck with equations stored as OMML in text runs. Pandoc can convert Office math in several readers; the PHP PPTX reader does not expose full OMML-to-AST parity.
- PPTX-06: Deck with speaker notes containing rich formatting, lists, tables, and images. Pandoc reads notes text in its PPTX path; the PHP reader records selected notes metadata/text.
- PPTX-07: Deck with comments and threaded replies used as review content. Pandoc may ignore or process comments depending on reader behavior; the PHP reader records comments as review metadata, not normal document blocks.
- PPTX-08: Deck with slide animations whose order conveys content. Pandoc reads slide text independent of animation; the PHP reader records animation metadata and does not model animated reveal order.
- PPTX-09: Deck with transition sounds and embedded media that should become media references. Pandoc's media bag support is mature; the PHP reader records transition/rich-media sources as metadata.
- PPTX-10: Deck with embedded audio/video objects inside slides. Pandoc can retain media references; the PHP reader treats rich media as review metadata/media-bag items, not visible AST content.
- PPTX-11: Deck with OLE embedded objects such as Excel sheets or PDFs. Pandoc handles some embedded resources; the PHP reader does not parse embedded package contents into document blocks.
- PPTX-12: Deck with linked external images and external media. Pandoc can preserve links or media references; the PHP reader skips/diagnoses non-embedded or unknown image relationships.
- PPTX-13: Deck with custom layouts where placeholders inherit text from master/layout parts. Pandoc's PPTX reader has layout handling; the PHP reader is focused on slide-local visible shapes and metadata.
- PPTX-14: Deck with tables containing merged cells, vertical text, rich text runs, and style-driven header rows. Pandoc has broader table extraction; the PHP reader handles selected table shapes.
- PPTX-15: Deck with multi-column placeholders, nested lists, and mixed paragraph levels beyond adjacent-level grouping. Pandoc has established paragraph/list extraction; the PHP reader has bounded bullet grouping.
- PPTX-16: Deck with hidden slides or custom shows that determine intended reading order. Pandoc has presentation semantics; the PHP reader records sections/custom shows but emits the normal slide list.
- PPTX-17: Deck with theme-driven fonts, colors, and semantic emphasis only in styles. Pandoc's reader can infer more formatting; the PHP reader focuses on visible text and limited inline styles.
- PPTX-18: Deck with right-to-left text, complex scripts, and bidi paragraph properties. Pandoc handles Unicode text; the PHP reader does not fully model bidi paragraph properties.
- PPTX-19: Deck with diagrams represented through DrawingML data/layout parts with missing or duplicate model IDs. Pandoc's SmartArt parser handles more cases; the PHP parser has explicit partial/diagnostic fallbacks.
- PPTX-20: Deck using macros, embedded ActiveX, or content parts for visible controls. Pandoc ignores unsafe behavior while reading visible text; the PHP reader treats content parts as unsupported drawable shapes.

## XLSX

- XLSX-01: Workbook whose important sheet is an external worksheet relationship. Pandoc reads workbook content available through its reader path; the PHP reader throws on external worksheet relationships.
- XLSX-02: Workbook where formulas have no cached values and must be evaluated to show results. Pandoc can read cached values when present; the PHP reader explicitly uses cached-values-only and does not evaluate formulas.
- XLSX-03: Workbook with dynamic array formulas spilling across cells. Pandoc can read resulting cell values if stored; the PHP reader does not model formula spill semantics.
- XLSX-04: Workbook with pivot tables where the pivot result is the main content. Pandoc can read sheet cell results; the PHP reader records pivot parts as feature metadata, not computed tables.
- XLSX-05: Workbook with charts that carry the meaningful labels/data. Pandoc can read visible sheet data; the PHP reader records chart parts as metadata and does not render chart content.
- XLSX-06: Workbook with images anchored to cells and captions in drawing text boxes. Pandoc may expose media references; the PHP reader records drawing/image metadata but emits sheet tables as primary content.
- XLSX-07: Workbook with comments or threaded comments that are part of the document text. Pandoc may ignore comments; the PHP reader records comments as metadata, not table-cell content.
- XLSX-08: Workbook with rich inline strings where bold/italic spans matter inside a cell. Pandoc can preserve cell text; the PHP reader has limited direct font/style mapping.
- XLSX-09: Workbook with date/time formats that depend on custom number formats. Pandoc handles values as stored text/numbers; the PHP reader has bounded date/style handling and can lose display fidelity.
- XLSX-10: Workbook with merged cells used for section headers across many columns. Pandoc emits table structures from sheet data; the PHP reader has colspan/rowspan handling but limited layout semantics.
- XLSX-11: Workbook with hidden rows/columns that should be excluded from reading. Pandoc reader choices differ by semantics; the PHP reader emits all sheets and records visibility as metadata.
- XLSX-12: Workbook with very-hidden sheets containing sensitive or auxiliary content. Pandoc may read workbook data normally; the PHP reader emits all sheets and records hidden state.
- XLSX-13: Workbook with named ranges that define the intended table region. Pandoc's reader may use sheet data broadly; the PHP reader records defined names as metadata only.
- XLSX-14: Workbook with autofilter ranges and table parts defining structured table boundaries. Pandoc can read table-like sheet content; the PHP reader records table parts/autofilters as metadata while emitting dense sheet grids.
- XLSX-15: Workbook with data validation lists that are important visible options. Pandoc reads cell values; the PHP reader records validation metadata but does not turn lists into document content.
- XLSX-16: Workbook with shared formulas and calc-chain-dependent values. Pandoc relies on stored cell values; the PHP reader does not evaluate shared formulas.
- XLSX-17: Workbook with error cells whose formulas carry the meaningful expression. Pandoc may surface stored error values; the PHP reader records formula/error diagnostics and may not preserve user intent.
- XLSX-18: Workbook with phonetic runs or East Asian ruby annotations in cells. Pandoc can preserve visible text; the PHP reader does not model phonetic annotation semantics.
- XLSX-19: Workbook with multiple tables on one sheet separated by blank regions. Pandoc's output can be inspected from sheet cells; the PHP reader emits one dense table per sheet.
- XLSX-20: Workbook with protected ranges, sheet views, frozen panes, and print areas that identify reading order. Pandoc mostly reads cell content; the PHP reader records layout metadata but does not use it to reshape output.

## CSV

- CSV-01: Valid CSV where the first row must be the table header exactly as Pandoc does, but the port is called with `header=false`. Pandoc direct CSV always consumes the first row as `TableHead`; local behavior can diverge by option.
- CSV-02: CSV with a quoted field containing several newline-separated paragraphs. Pandoc converts cell newlines to line breaks inside a simple cell; the PHP port has a configurable `cellLineBreak` option that can diverge.
- CSV-03: CSV with post-comma spaces after delimiters. Pandoc default skips spaces and tabs after comma delimiters; local non-default `keepSpace` can produce different cells.
- CSV-04: CSV with a leading empty first field in a row. Pandoc's parser requires a delimiter after an empty first cell; local relaxed diagnostics may preserve shapes differently.
- CSV-05: CSV with text after a closing quote. Pandoc throws a parse error; local relaxed parsing can preserve/recover the record, so it is not Pandoc-equivalent unless strict mode is used.
- CSV-06: CSV ending inside an unclosed quoted field. Pandoc throws a parse error; local relaxed parsing preserves a partial record.
- CSV-07: CSV with a backslash escape convention instead of doubled quotes. Pandoc direct CSV uses doubled quotes by default; local dialect options can accept backslash escapes and diverge from Pandoc.
- CSV-08: CSV using single quotes as quote characters. Pandoc direct CSV only uses double quotes; local dialect options can accept single quotes and diverge.
- CSV-09: CSV with a semicolon delimiter and comma decimals. Pandoc direct CSV expects commas; local dialect options can parse semicolons, which is not direct Pandoc CSV parity.
- CSV-10: CSV with a pipe delimiter. Pandoc direct CSV expects commas; local auto/dialect options can infer or parse other delimiters.
- CSV-11: CSV with bare carriage returns inside fields. Pandoc's parser treats carriage return as a line-ending component; local normalization/review can behave differently.
- CSV-12: CSV with vertical-tab or form-feed whitespace after quotes. Pandoc's parser has specific whitespace behavior; local prefix/control-character diagnostics can change recovery behavior.
- CSV-13: CSV with ragged rows wider than the header. Pandoc uses the first row column count for table specs; local repair can pad/truncate according to local policy.
- CSV-14: CSV with duplicate header labels. Pandoc keeps header cell text as-is; local metadata may normalize column names and can affect downstream consumers.
- CSV-15: CSV with Markdown syntax in cells that must stay literal. Pandoc direct CSV emits plain text cells; the local table AST must also keep it literal, so this is a parity check candidate.
- CSV-16: CSV with formula-looking values beginning with `=`. Pandoc direct CSV treats them as text; local downstream spreadsheet-safety handling must not reinterpret them.
- CSV-17: CSV with UTF-8 BOM plus leading whitespace before the first row. Pandoc parses text after source decoding; local bounded input-prefix handling can affect the first cell if not strict.
- CSV-18: CSV with a quoted CRLF cell and mixed LF row endings. Pandoc normalizes line endings through parser behavior; local source normalization and cell line-break policy can differ.
- CSV-19: CSV with trailing empty fields and delimiter-only rows. Pandoc preserves parsed empty cells according to parser rules; local row repair can create different table width metadata.
- CSV-20: CSV with NUL or other control characters in fields. Pandoc reads Unicode text through its source pipeline; local bounded control-character review can mark or alter downstream handling.

## TSV

- TSV-01: TSV where the first row must be the table header exactly as Pandoc does, but the port is called with `header=false`. Pandoc direct TSV always consumes the first row as `TableHead`; local behavior can diverge by option.
- TSV-02: TSV with literal double quote characters. Pandoc direct TSV has `csvQuote = Nothing`, so quotes are literal; local quote-enabled options can diverge.
- TSV-03: TSV with post-tab spaces after delimiters. Pandoc skips spaces after tab delimiters by default; local `keepSpace` can preserve them and change cells.
- TSV-04: TSV with embedded newlines in a field represented by quotes. Pandoc direct TSV does not quote fields, so this should split records; local quote-enabled dialects can diverge.
- TSV-05: TSV with consecutive tabs producing empty fields. Pandoc preserves empty fields by parser rules; local repair can alter width metadata.
- TSV-06: TSV with leading empty fields. Pandoc parser rules for empty first cells require delimiters; local diagnostics/recovery need strict verification.
- TSV-07: TSV with ragged rows and a wider body row than the header. Pandoc's first-row width controls table specs; local pad-to-wide-row policy can diverge.
- TSV-08: TSV with duplicate header labels. Pandoc keeps header text; local generated/normalized column labels can affect metadata consumers.
- TSV-09: TSV with Markdown syntax in cells that must stay literal. Pandoc direct TSV emits plain text cells; local downstream conversion must not parse Markdown inside cells.
- TSV-10: TSV with formula-looking values beginning with `=`. Pandoc treats them as text; local spreadsheet-safety or review logic must not reinterpret them.
- TSV-11: TSV with CRLF rows and bare CR in cell text. Pandoc parses line endings through its CSV parser; local normalization can differ.
- TSV-12: TSV with trailing empty fields. Pandoc preserves parsed empty cells; local table repair can create different body/header widths.
- TSV-13: TSV with a row containing only delimiters. Pandoc emits empty table cells; local blank-row and delimiter-only diagnostics need parity verification.
- TSV-14: TSV with Unicode tabs-like characters that are not ASCII tab. Pandoc only treats ASCII tab as delimiter; local auto inference must not split on lookalikes.
- TSV-15: TSV with leading BOM and whitespace. Pandoc parses decoded source text; local bounded input-prefix handling can affect strict parsing.
- TSV-16: TSV with NUL/control characters inside fields. Pandoc's source pipeline reads text; local control-character review can change downstream behavior.
- TSV-17: TSV with a final row lacking a trailing newline. Pandoc accepts EOF after the final row; local partial-final-record diagnostics must not change the AST.
- TSV-18: TSV with literal backslashes before tabs. Pandoc has no escape character for TSV; local escape-enabled options can diverge.
- TSV-19: TSV with single-quote dialect expectations. Pandoc direct TSV treats single quotes as literal; local dialect options can parse them as quotes.
- TSV-20: TSV with spaces-only cells at row edges. Pandoc's delimiter whitespace skipping can trim certain post-delimiter spaces; local keep-space or normalization can differ.

## man

- MAN-01: Manual page using custom macro definitions with `.de`, `.am`, and later invocations. Pandoc's roff reader handles more roff macro behavior; the PHP reader skips macro definitions and cannot expand arbitrary custom macros.
- MAN-02: Page using conditionals `.if`, `.ie`, `.el` to select visible text. Pandoc's roff parser handles more requests; the PHP reader ignores many requests and can lose conditional content.
- MAN-03: Page using string registers and interpolations like `.ds` plus `\*` references. Pandoc supports more roff escape/string behavior; the PHP reader has bounded escape handling.
- MAN-04: Page using number registers and arithmetic in text. Pandoc has roff parser support; the PHP reader does not evaluate register expressions.
- MAN-05: Page with complex tbl options, spanning cells, decimal alignment, and multiline cells. Pandoc handles tbl more broadly; the PHP reader has a bounded table parser.
- MAN-06: Page using eqn math blocks. Pandoc can process roff equation content better; the PHP reader does not model eqn as math.
- MAN-07: Page using pic diagrams or grap/preprocessor output. Pandoc can consume preprocessed roff output; the PHP reader does not run preprocessors.
- MAN-08: Page with nested relative indents and mixed `.RS`/`.RE` around lists. Pandoc handles more roff layout state; the PHP reader covers selected blockquote/list cases.
- MAN-09: Page with `.TQ` multiple tagged paragraph terms. Pandoc handles multi-term tagged paragraphs; the PHP reader has bounded `.TP` support.
- MAN-10: Page with `.UR`/`.UE` link bodies containing nested inline macros and punctuation. Pandoc handles link delimiters and inline parsing broadly; the PHP reader has targeted link parsing.
- MAN-11: Page with `.MT`/`.ME` email links and optional label text. Pandoc supports email link macros; the PHP reader has selected support.
- MAN-12: Page using font escapes `\f[BI]`, `\f(CW`, and nested font changes. Pandoc has fuller roff font handling; the PHP reader maps selected inline macro styles.
- MAN-13: Page using special characters, glyph escapes, and overstriking. Pandoc has wider roff escape tables; the PHP reader decodes a bounded set.
- MAN-14: Page using hyphenation, no-fill/fill transitions, and line spacing requests to shape code. Pandoc handles roff layout conventions; the PHP reader has selected `nf`, `EX`, and code block support.
- MAN-15: Page with synopsis options expressed through nested macros and optional groups. Pandoc's man reader has synopsis option behavior; the PHP reader covers selected synopsis options.
- MAN-16: Page with multiple `.TH` metadata fields and unusual quoting. Pandoc parses title metadata robustly; the PHP reader has bounded argument parsing.
- MAN-17: Page using `.so` include requests for shared content. Pandoc can process prepared source or include behavior depending on pipeline; the PHP reader does not resolve include files.
- MAN-18: Page using `.als` aliases for standard macros. Pandoc handles more macro aliasing; the PHP reader only recognizes fixed macro names.
- MAN-19: Page using `.ad`, `.na`, `.nh`, `.hy`, and spacing requests that affect visible line breaks. Pandoc's roff model is richer; the PHP reader ignores many layout requests.
- MAN-20: Page generated by a tool with mixed man, mdoc, and raw roff requests. Pandoc's roff reader tolerates more request families; the PHP reader is scoped to pinned man unit semantics.

## mdoc

- MDOC-01: Manual with callable macros outside the small local callable set. Pandoc's mdoc reader supports more macro families; the PHP reader only handles selected callable macros.
- MDOC-02: Manual using `.Bd` displays with literal, filled, ragged, and offset variants. Pandoc handles display blocks; the PHP reader has limited `.Ed`/display boundary behavior.
- MDOC-03: Manual with `.Fo`, `.Fa`, `.Fc` function prototypes. Pandoc parses mdoc function synopsis macros; the PHP reader does not fully model those macros.
- MDOC-04: Manual with `.Ft`, `.Fn`, `.Vt`, and nested argument macros. Pandoc handles function/type macro families; the PHP reader maps only selected inline macros.
- MDOC-05: Manual with `.In` include-file macros in SYNOPSIS. Pandoc renders include directives semantically; the PHP reader treats unrecognized macros as plain collected text or skips.
- MDOC-06: Manual with `.An`, `.Aq`, `.Bq`, `.Brq`, `.Pq`, and nested quoting macros. Pandoc supports more quoting macros; the PHP reader has selected quote macros.
- MDOC-07: Manual with `.Rs`/`.Re` reference blocks. Pandoc maps reference sections; the PHP reader does not have a full reference-block parser.
- MDOC-08: Manual with `.Bl -column` tables and column headers. Pandoc supports mdoc column lists; the PHP reader handles bullet, ordered, and tag list slices.
- MDOC-09: Manual with `.Bl -enum` using custom offsets and compact spacing. Pandoc handles more list options; the PHP reader maps basic list kinds.
- MDOC-10: Manual with nested `.Bl` lists inside `.It` items containing paragraph breaks. Pandoc supports deeper mdoc list nesting; the PHP parser has bounded nested list handling.
- MDOC-11: Manual with `.Nm` defaults changing across NAME, SYNOPSIS, and DESCRIPTION. Pandoc tracks mdoc document-name context; the PHP reader has a simpler document-name state.
- MDOC-12: Manual with `.Os`, `.Dt`, `.Dd`, `.Lb`, and `.St` metadata variants. Pandoc maps more metadata macros; the PHP reader captures selected metadata.
- MDOC-13: Manual with `.Xr` references plus punctuation and section numbers. Pandoc creates proper cross-reference text; the PHP reader represents Xr as a span class.
- MDOC-14: Manual with `.Lk`, `.Mt`, and URI/email macros. Pandoc supports more link macros; the PHP reader does not cover the full mdoc link family.
- MDOC-15: Manual with `.Pa` paths, `.Ev` environment variables, and `.Cm` commands nested in optional groups. Pandoc handles nested callable macro parsing; the PHP reader has selected nesting behavior.
- MDOC-16: Manual with `.Ns` no-space chains across several inline macros. Pandoc models no-space behavior carefully; the PHP reader has bounded attach/no-space handling.
- MDOC-17: Manual with comments and escaped comments inside macro arguments. Pandoc's roff/mdoc parser handles comment syntax more fully; the PHP reader strips a bounded comment form.
- MDOC-18: Manual with special character escapes and named glyphs in macro arguments. Pandoc has larger escape tables; the PHP reader decodes a bounded set.
- MDOC-19: Manual with `.so` includes and generated shared sections. Pandoc can work with prepared expanded source; the PHP reader does not resolve include requests.
- MDOC-20: Manual using vendor-specific macros emitted by mandoc/groff extensions. Pandoc tolerates more roff/mdoc variants; the PHP reader is scoped to bounded macro-family semantics.
