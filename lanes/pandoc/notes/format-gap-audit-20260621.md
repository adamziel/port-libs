# Pandoc format gap audit - 2026-06-21

Upstream reference:

- Pandoc manual date: 2026-06-03
- Pandoc source commit: 912bfa5e2e3f5c74eb125dfc19404f67c61ca58b
- Registry source: `PandocFormatRegistry`

Current registry state after this slice:

- Upstream inputs: 51 total; 20 partial native PHP inputs; 31 unsupported inputs.
- Local inputs: 2 partial inputs, `pdf` and `doc`.
- Upstream outputs: 75 total; 14 partial native PHP outputs; 61 unsupported outputs.
- Local output aliases include WordPress block markup through `wordpress` / `blocks`.

This slice moved existing PHP code onto the public converter path:

- `csv` and `tsv` now use `DelimitedTextReader` through `PandocConverter`.
- `rtf` now uses `RtfReader` through `PandocConverter`.
- `ipynb` now uses `IpynbReader` through `PandocConverter`.
- `doc` now uses `LegacyDocReader` through `PandocConverter` as a project-local input.
- `plain` now uses `PlainWriter` instead of routing through `MarkdownWriter`.
- The problematic local PDF sample was verified with geometry table reconstruction and no invoice-specific runtime or test strings.

Supported input gaps:

- Markdown family (`markdown`, `commonmark`, `commonmark_x`, `gfm`, `markdown_github`, `markdown_mmd`, `markdown_phpextra`, `markdown_strict`): broad native reader exists, but full extension enable/disable parity and long-tail Pandoc Markdown behavior remain open.
- `html`: routed through the shared HTML-capable Markdown reader. Many DOM/import slices are covered, but complete HTML5 tree-construction and every upstream HTML reader edge are not complete.
- `docbook`: bounded DocBook table command fixtures are mapped through the shared reader; a full DocBook XML reader is still missing.
- `latex`: raw TeX plus bounded table/math slices exist; a full LaTeX reader is still missing.
- `json` and `native`: current AST constructor coverage is good for the shared AST, but complete Pandoc constructor parity is still partial.
- `docx`: bounded OpenXML package input covers document body, styles, numbering, relationships, notes/comments, headers/footers, media references, bookmarks, fields, OMML, and core properties. Full WordprocessingML parity and a DOCX writer remain open.
- `odt`: bounded OpenDocument package input covers common body, metadata, styles, lists, tables, links, images, and package references. Full ODF parity and an ODT/OpenDocument writer remain open.
- `epub`: bounded EPUB package input covers OPF discovery, metadata, spine XHTML, resource href rewriting, image/resource references, nav, and NCX. EPUB writers remain open.
- `ipynb`: bounded notebook input covers markdown, code, and raw cells plus notebook, metadata, attachment, source-shape, execution, and output diagnostics without executing notebooks or exposing embedded output bytes. Native IPYNB writer parity remains open.
- `csv` and `tsv`: delimited text input now maps to table AST with quote, multiline, row-repair, control-character, and provenance diagnostics. Full Pandoc option parity is still open.
- `rtf`: bounded reader covers paragraphs, escaped characters, unicode fallback, tabs, and core inline styles. Full RTF control-word, destination, table, image, and metadata parity remains open.
- `pdf` (local): markerPDF bridge covers searchable text, structural provenance, encryption cases, link/annotation metadata, tagged tables, geometry tables, word gaps, and filled rectangle table backgrounds. Missing work includes OCR/image-only extraction, richer page layout semantics, complex spanning tables, multipage table stitching, forms, and higher-fidelity style/layout preservation.
- `doc` (local): legacy binary Word reader covers Compound File Binary containers, WordDocument text, OLE properties, metadata, fields, lists, notes, comments, sections, bookmarks, and review provenance. Missing work remains in full legacy Word binary layout, embedded object rendering, and complete Word feature parity.

Supported output gaps:

- Markdown family writers are partial and share `MarkdownWriter`; full variant-specific extension behavior remains open.
- `html` / `html5` writer covers core block/inline output and many slices; standalone/template and complete writer parity remain open.
- `json` and `native` writers cover the shared AST subset; complete constructor parity remains open.
- `latex` writer covers bounded block, inline, math, and raw TeX slices; full writer parity remains open.
- `plain` now uses `PlainWriter`, with wrapping/table/unicode diagnostics, but full Pandoc plain writer parity remains open.
- Rich package outputs remain unsupported: `docx`, `odt`, `opendocument`, `epub`, `epub2`, `epub3`, `pdf`, `pptx`, and others.

Code-present but not main-registry-exposed:

- Bibliography/citation classes (`BibtexCslParser`, `BibtexCslProcessor`, `CitationCslProcessor`) exist, but `bibtex`, `biblatex`, and `csljson` are not registered as input/output formats yet.
- XML/HTML DOM infrastructure exists and is heavily tested, but `xml`, `jats`, and the full DocBook/JATS family are not registered as complete readers/writers.

Porting plan:

1. Register bibliography formats where existing processors are strong enough.
   - Audit `bibtex`, `biblatex`, and `csljson` tests against Pandoc expectations.
   - Expose input/output only for the directions that can round-trip through the shared AST without external tools.

2. Move XML-family formats next.
   - Use `XmlHtmlDom` and existing XML/HTML5/JATS notes to pick bounded first targets: `xml`, then `jats`, then DocBook-specific work.
   - Avoid claiming full XML/JATS/DocBook parity until namespace, entity, table, metadata, and citation branches are covered.

3. Defer package writer formats until reader coverage is stable.
   - DOCX/ODT/EPUB/PDF outputs need native package/renderer writers, not registry-only changes.
   - PPTX/XLSX inputs require new OpenXML readers and should be treated as larger projects.

Recommended next format:

- Bibliography inputs, starting with `bibtex` / `biblatex` if existing processor tests prove enough Pandoc-facing coverage. They are the fastest remaining upstream input formats with substantial PHP code already present.
