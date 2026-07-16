# Region-aware PDF import: comparative notes and design

## Why flattening fails

A PDF normally describes painted glyphs and graphics at coordinates. It does not have to say that a run is a heading, a speaker name, a paragraph continuation, a chart, or a footer. A reliable importer therefore cannot make one global choice such as “sort everything by x/y” or “trust content-stream order.” It needs to preserve multiple kinds of evidence, infer page regions and their roles, and make conservative AST decisions only when those signals agree.

The practical objective for this port is editable WordPress content, not a pixel-perfect copy. That makes reflow the default, while retaining tables, figures, code, line-oriented dialogue, and page furniture as explicit region roles. When evidence is weak, preserving readable text is preferable to inventing structure.

## What comparable systems support

| System | Layouts and roles | How it addresses hard cases | Design lesson for this port |
| --- | --- | --- | --- |
| Adobe Acrobat Export PDF | Flowing text, retained page layout, OCR, and image inclusion. “Retain Page Layout” can split output into text-box groups; “Retain Flowing Text” prioritizes editable wrapping. | Exposes the fundamental choice between visual fidelity and semantic flow to the user. | Keep one semantic import default, but preserve enough region geometry that a later fidelity mode could emit positioned groups without changing extraction. [Adobe export options](https://helpx.adobe.com/ee/acrobat/how-to/convert-pdf-retain-page-layout.html) |
| Unstructured | Fast text extraction, OCR-only, and `hi_res` layout inference; tables, forms, images, rotated pages, and per-element output. | Selects a strategy, merges PDF text with inferred regions, enables vertical detection for rotated pages, and uses configurable character/word/line margins. | Separate extraction from layout interpretation. Retain source text, positioned text, and region hypotheses instead of allowing one path to erase the others. [Unstructured PDF partitioner](https://github.com/Unstructured-IO/unstructured/blob/main/unstructured/partition/pdf.py) |
| Docling | Page layout, reading order, tables, code, formulas, pictures, and a unified lossless document model. | Uses a typed intermediate representation and dedicated models/stages before export. | Our AST should receive explicit region roles (`paragraph`, `table`, `code`, `line_block`, `figure`) rather than trying to reconstruct roles in the writer. [Docling](https://github.com/docling-project/docling) |
| Marker | Reading order, tables, forms, equations, inline math, links, references, code, OCR, and optional LLM correction. | Pipeline: text/OCR, layout and reading order, block cleanup/formatting, optional correction, then document-level postprocessing. Its published table benchmark uses 99 aligned FinTabNet tables; its overall benchmark uses sampled Common Crawl pages and compares several parsers. | Use staged, inspectable transforms and document-level consistency checks. Do not treat a small review corpus as a universal accuracy score. [Marker README and benchmarks](https://github.com/datalab-to/marker/blob/master/README.md) |
| MinerU | Single- and multi-column reading order, complex layouts, headings, paragraphs, lists, formulas, tables, images, vertical text, and removal of headers, footers, footnotes, and page numbers. | Produces reading-order JSON plus visualization artifacts so layout and span decisions can be reviewed. | Emit diagnostics and build a visual corpus browser. Page furniture and reading order need document-wide evidence, not isolated regexes. [MinerU](https://github.com/opendatalab/MinerU) |
| Azure Document Intelligence Layout | Paragraphs, titles, section headings, page headers/footers/numbers, footnotes, tables, figures, selection marks, orientation, and hierarchical sections. | Distinguishes geometric roles from logical roles and returns polygons, spans, confidence, and hierarchy. | Model geometry and logical role separately. A large font is evidence for a heading but should not itself become the heading decision. [Azure layout model](https://learn.microsoft.com/en-us/azure/ai-services/document-intelligence/prebuilt/layout?view=doc-intel-4.0.0) |
| Google Document AI Layout Parser | Paragraphs, headings, headers/footers, figures, tables, hierarchy, and context-aware chunks. | Builds a document tree, associates objects with headings, and can annotate complex visual elements. Its documented limitation that multi-page tables can split is a reminder that page-local detection needs document-level reconciliation. | Keep stable source anchors and parent/section context so later browser-rendered figures and multi-page structures can be inserted at the right location. [Google layout parser](https://docs.cloud.google.com/document-ai/docs/layout-parse-chunk) |
| Amazon Textract | Lines/words, layout, forms, key/value pairs, tables, merged cells, titles, footers, section titles, and selection elements. | Returns typed blocks with geometry and parent/child relationships rather than one flattened string. | Relationships are as important as boxes. Tables and forms should own their cells/controls so prose reconstruction cannot consume them. [Textract analysis](https://docs.aws.amazon.com/textract/latest/dg/how-it-works-analyzing.html), [Textract tables](https://docs.aws.amazon.com/textract/latest/dg/how-it-works-tables.html) |

The model-heavy systems cover more classes out of the box, but their transferable idea is architectural: retain observations, classify regions, build relationships, then serialize. The PHP port can apply that design with deterministic evidence and browser assistance where rendering is unavoidable.

## Region-aware pipeline for this port

1. **Observations**
   Keep source-stream text, positioned glyph/run text, exact non-whitespace character identity, page and stream identity, font size, baselines, bounding boxes, boundary provenance, image/Form placements, and extraction diagnostics.

2. **Page regions**
   Cluster aligned rows and columns without immediately deciding that a grid is a table. Detect repeated page furniture across pages, coherent text lanes, code bands, figure areas, and line-oriented cue/body lanes.

3. **Logical roles**
   Infer roles only from multiple independent signals. Examples in this PR:

   - theatre/transcript dialogue requires recurring cue shapes in one aligned column and sentence-case bodies;
   - short code requires stable pitch, syntax near the starts of multiple rows, and a coherent left edge;
   - a page number requires a bottom-band position repeated in the same horizontal slot on multiple pages;
   - inter-glyph spacing repair requires a positioned candidate with exactly the same non-whitespace characters and a sustained one-letter-fragment failure;
   - a display heading uses local font contrast or an exact positioned heading prefix, without a vocabulary list;
   - sparse narrative grids with prose and bullets are rejected as tables.

4. **Relationships and reading order**
   Order within a region first, then order regions. Permit reverse source-order adjacency only on baselines carrying strong RTL script. Keep table, code, figure, and line-oriented regions atomic. Use neighboring text anchors for browser-rendered PDF graphics so media returns to its source position.

5. **Conservative AST**
   Emit semantic nodes when the evidence threshold is met. Otherwise retain readable paragraphs and diagnostics. Never “repair” by guessing a particular word or speaker name. If an optional image decoder or browser rendering stage is absent, keep the original asset and a placeholder rather than failing the import.

## Corpus and success criteria

This PR adds 14 externally sourced PDFs to the showcase corpus, in addition to the existing real PDF fixtures and focused synthetic tests. They cover:

- running headers/footers;
- multi-column prose;
- nested and glyph-based lists;
- a Korean table;
- an OCR overlay and an image-only OCR case;
- compact code and formula material;
- Arabic/Latin RTL baselines;
- pictures and captions;
- an aircraft handbook with same-font section labels;
- a table/picture boundary;
- theatre dialogue;
- rotated text; and
- emphasis/style fragments.

The manifest records provenance, the layout risk, and machine-checkable minimum/maximum outcomes for every PDF. Gates include readable text volume, paragraph/heading/list/table/code/line-block counts, no single-glyph paragraph artifacts where prohibited, and a no-text allowance only for the explicitly scanned OCR fixture. The browser E2E adds mobile overflow/crash checks, tab/picker behavior, structure checks inside the preview, console-error checks, and screenshots.

This corpus is a regression and design corpus, not a benchmark score comparable with Marker’s Common Crawl or 99-table evaluation. A defensible effectiveness benchmark would require licensed or redistributable ground truth for reading order, regions, text, and structure, stratified by document family. The next useful expansion is roughly 10–20 reviewed pages per risk class, then precision/recall for region roles and reading-order edges rather than a single subjective “looks right” score.

## Avoiding whack-a-mole

The safest policy is evidence monotonicity: a new heuristic may add a role only when it introduces new positive evidence, and it must not weaken established negative evidence. Every fix should therefore include:

- at least one positive fixture;
- nearby negative variants;
- an assertion on the intermediate role or provenance, not only final HTML;
- the existing real-world corpus; and
- a screenshot review for layouts that are hard to express as text assertions.

That is why the spacing fix compares exact character sequences instead of recognizing “Your” or “The,” why dialogue uses recurring geometry instead of character-name dictionaries, and why the compact-code fix also tests prose prefixes and cross-column bibliography rows. This approach cannot make ambiguous PDFs unambiguous, but it makes improvements composable and regressions observable.
