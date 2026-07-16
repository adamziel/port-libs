# Pandoc Reader Missing Feature Audit - 2026-06-26

Scope: remaining gaps for CSV/TSV, BibTeX/BibLaTeX, XLSX, PPTX, and DOCX after
the `plainmath-parity-20260625` checkpoint.

Evidence used: checkpoint reader implementations and focused tests in
`lanes/pandoc/src/*Reader.php`, `lanes/pandoc/tests/*ReaderTest.php`, the
reader sweep brief in `reader-feature-parity-supervisor-20260626.md`, and the
checked-in upstream inventory notes. I did not browse. The upstream cache is not
present in this worktree, and the final merge-queue base may not contain every
checkpoint reader file, so this is a checkpoint code/test/static-note audit
rather than a live upstream runner comparison.

Explicit exclusions for this audit:

- No JavaScript execution or scripted document behavior.
- No crypto authorization, password cracking, signature validation, or protected
  package bypass.
- No DRM support.
- No writer parity claims unless reader verification currently depends on an
  existing writer path.

## CSV/TSV

Likely checkpoint files:

- `lanes/pandoc/src/CsvReader.php`
- `lanes/pandoc/tests/CsvReaderTest.php`
- `lanes/pandoc/src/PandocConverter.php`
- `lanes/pandoc/src/PandocFormatRegistry.php`

Supported at checkpoint:

- `csv` and `tsv` are routed through `PandocConverter` as partial input formats.
- CSV handles a first-row header by default, a headerless reader option, quoted
  fields, doubled quotes, multiline quoted cells, `sep=` delimiter directives,
  configured delimiter/quote/escape/comment characters, UTF BOM decoding, ragged
  rows, and table metadata.
- TSV forces tab delimiters and disables quote interpretation, so quote
  characters remain literal.
- Focused tests cover quoted commas, multiline cells, escaped quotes, semicolon
  detection, comments, alternate quote/escape settings, UTF-16LE BOM input,
  ragged rows, headerless import, empty input, and inferred column type metadata.

Partially supported:

- Dialect detection is heuristic and bounded to comma, semicolon, tab, and pipe
  candidates. It samples only the first non-comment lines and cannot represent
  multi-character separators.
- Type inference is metadata only. Cell contents remain plain table text; no AST
  value node records numeric/date/boolean semantics.
- Header handling is a reader option, not an automatic semantic decision based
  on source shape.
- Unquoted fields are trimmed. That is convenient for imports, but it loses
  leading/trailing spaces that may be significant in fixed-width exports,
  identifiers, or deliberately padded cells.

Still missing:

- Full CSV option parity around dialects: delimiter candidate configuration,
  quote mode variants, blank-line policy, and locale-specific numeric/date
  parsing are not modeled.
- Malformed CSV diagnostics are absent. Unclosed quotes, stray quote runs, and
  inconsistent record shapes are normalized into a table with metadata instead
  of being reported as parse warnings or recoverable diagnostics.
- No streaming import for large files. The reader accepts the whole source
  string and materializes all rows before producing the AST.
- No source-position provenance for rows/cells. Downstream reviewers cannot
  point a WordPress table cell back to line/column offsets in the original CSV.
- No table captions, column alignment inference, column width hints, or typed
  column schema import. All columns use default alignment.

User impact:

- Messy spreadsheet exports are readable, but ambiguous delimiters and malformed
  rows can silently produce the wrong table shape.
- Data-cleaning workflows that care about padding, locale numbers, or exact
  source row locations still need preflight tooling outside this reader.

## BibTeX/BibLaTeX

Likely checkpoint files:

- `lanes/pandoc/src/BibTexReader.php`
- `lanes/pandoc/tests/BibTexReaderTest.php`
- `lanes/pandoc/src/PandocConverter.php`
- `lanes/pandoc/src/PandocFormatRegistry.php`
- `lanes/pandoc/src/WordPressBlockWriter.php` for the visible bibliography body

Supported at checkpoint:

- `bibtex` and `biblatex` are routed as partial input formats.
- The reader parses `@string`, `@preamble`, `@comment`, braced/quoted/bare
  values, concatenated values, common month macros, ordinary entries, person
  names, common title/date/journal/url/doi aliases, and wildcard `nocite`.
- Checkpoint code resolves `crossref` and `xdata` inheritance for direct parent
  records and filters data-only `xdata`/`set` entries from visible output.
- Focused tests cover strings, preambles, comments, visible bibliography blocks,
  `references` metadata, BibLaTeX routing, xdata/crossref inheritance, dates,
  name particles, selected field aliases, TeX accent cleanup, URL cleanup, and
  empty bibliographies.

Partially supported:

- The parser emits upstream-shaped `references` metadata and simple visible
  `csl-entry` blocks, but it does not run citeproc, CSL locale rendering,
  bibliography sorting, disambiguation, label generation, or style-specific
  formatting.
- TeX cleanup is intentionally shallow. It handles simple commands, URL/href
  wrappers, a small accent set, and brace stripping, but it does not preserve
  rich inline structure, math, protected title casing semantics, or arbitrary
  macro expansion.
- Creator mapping only promotes `author`, `editor`, and `translator` to CSL
  name lists. Other fields survive in `bibtex-fields` metadata but are not
  first-class CSL creators.

Still missing:

- Full BibLaTeX data model coverage. Entry types such as `mvbook`, `bookinbook`,
  `suppbook`, `reference`, `inreference`, `review`, `standard`, `legislation`,
  `jurisdiction`, `legal`, `performance`, `video`, `audio`, and many aliases
  collapse to generic CSL `entry` unless explicitly mapped.
- Entry sets are not rendered as bibliographic items. `set` is treated as
  data-only, so set membership and set titles can disappear from user-visible
  output.
- Relationship fields beyond the direct `crossref`/`xdata` copy path are not
  modeled: `xref`, `related`, `relatedtype`, `ids`, `shorthand`, `label`,
  `labelalpha`, and related-note behavior remain plain metadata at best.
- Name roles beyond `author`/`editor`/`translator` are not promoted:
  `bookauthor`, `commentator`, `annotator`, `compiler`, `redactor`, `holder`,
  `editor[a-c]`, and `name[a-c]` are representative gaps.
- Complex names remain fragile: corporate names, "others" / et-al markers,
  multiword particles outside the built-in small list, and suffix/particle
  combinations beyond the focused fixtures need more cases.
- Complex BibLaTeX dates are not parsed: ranges, open-ended ranges, seasons,
  uncertain/approximate dates, time components, and `year`/`month`/`day`
  combinations beyond simple extraction.
- Full TeX decoding is missing: nested formatting commands, custom macros,
  math fragments, non-Latin accent commands, and title-case protection should
  not be claimed as parity.
- Validation is minimal. Duplicate keys, unknown fields, syntax errors, and
  failed inheritance are not surfaced as warnings.

User impact:

- Simple bibliographies become usable WordPress review output, but scholarly
  BibLaTeX databases can lose creator roles, set membership, relationship
  semantics, exact title casing, and citeproc-rendered bibliography behavior.

## XLSX

Likely checkpoint files:

- `lanes/pandoc/src/XlsxReader.php`
- `lanes/pandoc/src/ZipOpcPackage.php`
- `lanes/pandoc/tests/XlsxReaderTest.php`
- `lanes/pandoc/src/WordPressBlockWriter.php` for rendered table/image checks

Supported at checkpoint:

- The reader opens the OPC package, resolves the workbook through root
  relationships, follows worksheet relationships, reads core properties, shared
  strings, styles, workbook date system, and worksheet relationships.
- It converts worksheets to sheet `Div` nodes with headings and tables.
- Focused tests cover multiple sheets, shared strings, inline strings, booleans,
  numbers, rich text runs, bold/italic/underline/strike styles, foreground
  color, fill color, horizontal alignment, date/datetime/percent formatting,
  merged cells, hyperlinks, and worksheet drawing images.

Partially supported:

- Formula cells are display-only. The reader uses cached values where present
  and does not evaluate formulas or preserve formula text as first-class
  metadata.
- Number formatting covers common built-ins and simple custom formats. It is not
  a complete Excel formatter for currencies, conditions, colors, fractions,
  elapsed time, locale tokens, or text sections.
- Styles are reduced to text emphasis, strikeout, fill/color, and horizontal
  alignment. Font families/sizes, borders, vertical alignment, themes/tints,
  protection, conditional formats, and row/column style inheritance are mostly
  absent from output.
- Every non-empty sheet becomes one dense table from min used row/column to max
  used row/column, with the first row treated as the header.

Still missing:

- Formula semantics: formulas, array formulas, shared formulas, formula errors,
  cached string results, and external workbook references are not represented
  robustly.
- Workbook/sheet semantics: hidden sheets, very hidden sheets, active sheet,
  named ranges, print areas, freeze panes, filters, Excel table objects, sheet
  views, outline/grouping, and protected sheet metadata are ignored.
- Comments and notes: legacy comments, threaded comments, cell notes, authors,
  and comment anchors are not imported.
- Rich spreadsheet objects: charts, pivot tables, slicers, timelines, data
  validation, conditional formatting, sparklines, and embedded OLE objects are
  not converted.
- Drawings are image-only and limited to `xdr:pic` extraction. Text boxes,
  shapes, connectors, chart drawings, and alternate content are skipped.
- Media extraction records only package-relative image URLs. There is no media
  bag payload extraction, image dimensions, crop metadata, or caption mapping.
- Row/column dimensions and styles are missing. Hidden rows/columns, custom row
  heights, column widths, and row/column-level style defaults do not affect the
  AST.

User impact:

- Basic workbooks import as readable tables, but business spreadsheets that
  rely on formulas, comments, filtered tables, pivots, charts, or hidden-sheet
  semantics can produce incomplete or misleading review output.

## PPTX

Likely checkpoint files:

- `lanes/pandoc/src/PptxReader.php`
- `lanes/pandoc/src/ZipOpcPackage.php`
- `lanes/pandoc/tests/PptxReaderTest.php`
- `lanes/pandoc/src/WordPressBlockWriter.php` for rendered table/image/chart
  checks

Supported at checkpoint:

- The reader opens the OPC package, resolves the presentation through root
  relationships, follows slide relationships, reads core properties, slide size,
  slide media entries, slide-local relationships, notes slides, layouts,
  masters, and theme colors.
- It converts slides to slide `Div` nodes with headings, text boxes, styled
  text runs, hyperlinks, bullet and numbered lists, pictures, grouped shapes,
  DrawingML tables, simple chart data tables, and speaker notes.
- Focused tests cover multiple slides, inherited title fallback from layout,
  media images, hyperlinks, bullets, ordered lists, table spans, theme-derived
  table fill/border colors, chart cached data extraction, notes, slide size, and
  package metadata.

Partially supported:

- Layout/master inheritance is shallow. The reader records layout/master/theme
  paths and can use placeholder title text, but it does not inherit full
  placeholder geometry, default text styles, bullet styles, slide backgrounds,
  or theme font schemes.
- Charts are data extraction only. Cached series/category/value points become a
  simple table; chart type, axes, labels, legends, formatting, embedded workbook
  data, and rendered appearance are not represented.
- Text formatting covers bold, italic, underline, strikeout, and hyperlinks in
  runs. Font size, color, baseline, all-caps, small-caps, language, and many
  paragraph properties are omitted.
- Picture nodes preserve target, alt/title, and relationship id, but not size,
  crop, rotation, position, or caption.

Still missing:

- Full slide layout fidelity: placeholders, z-order, transforms, coordinates,
  speaker-note/master-note structure, sections, custom shows, and hidden-slide
  state are not preserved.
- SmartArt and diagrams are skipped as graphics, not reconstructed as semantic
  hierarchies.
- Shape support is text-biased. Auto-shapes, connectors, freeform shapes,
  equations, WordArt, text boxes with geometry, and alternate content are not
  represented beyond any plain text that happens to be reachable.
- Media support is still narrow. Audio, video, embedded files, linked media,
  thumbnails, and OLE/ActiveX objects are not imported as reviewable artifacts.
- Comments, modern comments, ink annotations, slide annotations, and revision
  metadata are absent.
- Transitions and animations are intentionally not part of static text/table
  extraction and should not be claimed as supported.

User impact:

- Text-heavy slide decks become reviewable, including many tables, notes, and
  chart data. Visual decks that depend on SmartArt, geometry, rich chart
  styling, comments, animations, or embedded media lose important authorial
  context.

## DOCX

Likely checkpoint files:

- `lanes/pandoc/src/DocxReader.php`
- `lanes/pandoc/src/ZipOpcPackage.php`
- `lanes/pandoc/tests/DocxReaderTest.php`
- `lanes/pandoc/src/WordPressBlockWriter.php` for comments/revisions, lists,
  images, tables, styles, and math rendering

Supported at checkpoint:

- The reader opens the OPC package, reads `word/document.xml`, styles,
  numbering, relationships, core properties, footnotes, endnotes, comments,
  headers, footers, media file names, and package entry counts.
- It converts paragraphs, headings, tables, nested tables in cells, direct
  bold/italic runs, simple custom paragraph/character style metadata, external
  and anchor hyperlinks, simple fields, bookmarks as raw OpenXML markers,
  footnote/endnote/comment references, inline insertion/deletion spans, images,
  list numbering, and a bounded OMML-to-TeX subset.
- Focused tests cover body metadata, notes, headers/footers, review spans,
  styles, links, numbering levels/start/style/delimiter, bookmarks, REF fields,
  OMML fractions/superscripts, and byte input routing.

Partially supported:

- Revisions are retained as markup, not resolved through accept/reject modes.
  Inline `ins` and `del` are handled, but paragraph-level change behavior and
  move tracking are not equivalent to upstream fixture families.
- Comments are attached at `commentReference` points as notes. Comment range
  starts/ends and overlapping target spans are not reconstructed in the package
  reader.
- Header/footer handling is package-wide. It does not honor section properties,
  first/even/default header selection, per-section ordering, or page context.
- OMML support is a small TeX extraction layer. Fractions, scripts, radicals,
  n-ary operators, and delimiters have representative handling; matrices,
  accents, equation arrays, functions, runs with styling, and many math
  structures fall back to flattened text.
- Direct run/paragraph formatting is sparse. Bold and italic are handled, but
  underline, strike, small caps, color, size, highlight, superscript/subscript,
  alignment, indentation, and spacing are not comprehensively read from direct
  properties.

Still missing:

- Table fidelity: `gridBefore`/`gridAfter`, vertical merges/rowspans, header
  rows, captions, table references, cell borders/shading, widths, alignment,
  row heights, omitted cells, and table style inheritance are not supported by
  the current package reader.
- Image fidelity: alt text/title extraction, dimensions, captions in text
  boxes, VML/object images, linked images without embeds, crop/rotation, and
  media payload extraction are missing.
- Complex fields: split `instrText` fields, TOC, index, bibliography, SEQ
  captions, page numbers, REF/PAGEREF variants with switches, and nested fields
  are not modeled beyond simple field anchors.
- Structured document content: content controls, footnote separators,
  endnote separators, altChunk imported HTML/docx chunks, text boxes, shapes,
  equations in drawings, and custom XML bindings are not imported.
- Review metadata parity: move-from/move-to revisions, paragraph
  insertion/deletion, scrubbed metadata variants, accept/reject modes, comment
  range spans, and overlapping targets need package-reader coverage rather than
  only older Native fixture handoffs.
- Style system parity: based-on styles are partially merged, but latent styles,
  defaults, theme fonts, numbering style links, paragraph/list style
  interaction, and document defaults are not modeled.
- Task-list glyph semantics from upstream DOCX fixture work are not connected
  to the package reader as checkbox/task-list metadata.

User impact:

- Ordinary DOCX text, lists, comments, notes, links, headers/footers, simple
  tables, and basic math become reviewable. Legal/editorial documents with
  complex revisions, table structure, captions, fields, images, sectioned
  headers, and style-dependent meaning can lose important review evidence or
  render in a misleading order.

## Cross-format priorities

High-value next work:

1. Surface parse diagnostics for malformed CSV, failed BibTeX inheritance,
   unsupported workbook relationships, skipped slide graphics, and skipped DOCX
   constructs. Silent omission is the biggest audit risk across all five.
2. Add per-format source provenance where feasible: row/cell coordinates,
   BibTeX entry keys and raw field text, sheet/cell refs, slide shape ids, and
   DOCX part/paragraph/table cell identifiers.
3. Improve table fidelity in XLSX/PPTX/DOCX before adding broad decorative style
   support. Table spans, headers, captions, and cell provenance have higher
   user impact than fonts.
4. Keep media handling bounded to extraction/provenance. Do not execute scripts,
   macros, OLE, ActiveX, or external fetches while adding review metadata.
5. Preserve non-goals explicitly in future issues: JavaScript, crypto
   authorization, protected package bypass, and DRM remain out of scope.
