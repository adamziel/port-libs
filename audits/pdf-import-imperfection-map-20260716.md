# PDF import imperfection map and non-regression plan

Date: 2026-07-16

Branch audited: `pdf-visual-reviewer-20260715` at `cba982f61`

Implementation status: implemented on PR #32 in the same branch. The commit,
CI run, and live Pages deployment are recorded at release time; this document
keeps the original audited commit above as the reproducible before-state.

Scope: searchable PDFs imported through MarkerPDF/Pandoc into WordPress, including the browser-assisted and resumable plugin path. OCR is explicitly out of scope.

## Executive decision

The theatre page is not a special-case problem and it should not be fixed with character names, Polish words, a theatre document type, or a lower global table threshold. It exposes a missing layout hypothesis: the importer can recognize a table, but it does not yet score an independent column flow as an equally serious alternative when the cells are short.

The safe fix is to classify each page region by competing, explainable hypotheses—tagged structure, table, independent columns, line-oriented material, ordinary prose, and figure/form—and change the incumbent result only when the winning hypothesis clears a meaningful evidence margin. Strong table evidence must continue to protect invoices, statements, borderless numeric tables, spanning cells, rotated tables, and tagged tables. Strong column-flow evidence should protect theatre dialogue, stage directions, sidebars, brochures, and other short aligned text.

This is also the most useful answer to the broader PDF-quality problem. PDF import becomes whack-a-mole when a late heuristic directly rewrites output. It becomes tractable when every decision has:

1. immutable source facts and local provenance;
2. a document-wide layout profile shared by every chunk;
3. competing per-region scores rather than one-way detectors;
4. a disposition ledger accounting for every source text and visual item;
5. a conservative fallback that preserves content as paragraphs/line blocks;
6. source-to-AST and AST-to-WordPress integrity gates;
7. reproducible corpus, metamorphic, visual, performance, and failure-injection tests.

The immediate column-vs-table fix should be the first vertical slice of that architecture, not an isolated exception.

## Scope, non-goals, and what “all imperfections” means

PDF is a painting and packaging format, not a guaranteed document model. The order in which text is painted need not be its reading order; tagged PDFs can provide a logical structure, while untagged PDFs require inference. Adobe documents that distinction directly in its [PDF accessibility overview](https://opensource.adobe.com/dc-acrobat-sdk-docs/library/accessibility/index.html). Consequently, no finite corpus can enumerate every adversarial PDF. “All” in this report means every failure class found across the current input, extraction, layout, semantic, media, browser, WordPress, and deployment pipeline, plus the materially different classes implied by the format and current code paths.

Non-goals:

- No OCR or visual text recognition. Image-only pages must be detected and reported as unsupported, not guessed.
- No pixel-perfect recreation of the original page in Gutenberg. The output remains editable, reflowable WordPress content.
- No execution of PDF JavaScript, launch actions, remote actions, or other active content.
- No promise to repair arbitrary corrupt or malicious PDFs beyond safe bounded recovery.
- No word-, title-, language-, author-, or document-specific repair rules.
- No silent substitution of browser-rendered text for native text until reconciliation proves it is better.

## Evidence and reproduction method

Evidence labels used below:

- **R — reproduced now:** rerun on the audited commit.
- **O — observed:** present in current generated showcase output or visual-review artifacts.
- **T — tested:** a current automated test proves either support or a bounded failure behavior.
- **D — detectable:** diagnostics exist, although this audit did not reproduce a public failing file.
- **G — gap:** the code path or format supports the failure, but there is no adequate regression fixture yet.

The private user PDF was used only as a local validation input. It is not named, copied, linked, or added to the repository. A generic synthetic reproduction with unrelated names and text proves that the failure does not depend on that document.

### Audit runs

| Run | Result |
|---|---|
| Private one-page range, geometry tables enabled | **R:** 2 Table blocks, 0 Paragraph blocks, 0 low-confidence candidates, 0 line-oriented regions |
| Same page, geometry tables disabled | **R:** 0 Table blocks, 11 Paragraph blocks; the text is available, so decoding is not the cause |
| Generic 5-row × 3-column stage-layout PDF | **R:** 1 Table block, 0 Paragraph blocks, 0 low-confidence candidates |
| Cue parser: `SPEAKER:` followed by sentence-case text | **R:** rejected as a cue |
| Cue parser: `SPEAKER :` followed by sentence-case text | **R:** cue accepted, but `:` leaks into the body |
| Native/parser/plugin regression suite | **R:** 14 test files, 5,307 assertions, 0 failures; 107.33 s; 97,386,496-byte maximum RSS |
| Public visual-review corpus | **R/T:** 14 documents at desktop and mobile sizes; reviewer check passed; saved run reports no console, page, or network errors |
| Showcase quality signature | **R/T:** passed |
| PDF.js facts-provider check | **R/T:** passed |
| JPX/JPEG 2000 and JBIG2 rasterizer checks | **R/T:** passed, including lossless palette verification |
| Combined release-focused native/plugin suites | **T:** 6,463 assertions, 0 failures across MarkerPDF, Pandoc PDF semantics, WordPress jobs, and isolated memory gates |
| Dense large-file native gate | **T:** 8,736,666 bytes, 250 pages, 5,000 positioned lines; 613,418 output bytes, 11,291 AST nodes, 54,886,400-byte allocated peak under a 128 MiB process limit |
| Real Playground page-tree import | **T:** four-page Muir brochure published as one root plus four physical-page children; all posts nonempty; 16 images across children; no console/page/network errors |
| Real Playground single-page import | **T:** TraceMonkey published as one 14-page WordPress page with eight expected charts in 37 seconds; no console/page/network errors |
| Dense real-browser import | **T:** the 250-page/8.7 MiB fixture published as one nonempty WordPress page in 80 seconds with 514,488 content bytes and 305,624 visible-text bytes |
| Production plugin artifact | **T:** two deterministic builds matched; 8,255,591 bytes, 618 entries, SHA-256 `ab2c74ad93a4e7a1e73f3c5737eaab25b2b3042a44163142eaa867248165ae8c` |

The current public layout manifest contains 14 documents. The separate table manifest contains 24 table-oriented candidates, but only a subset is checked in and exercised as deterministic regressions. The broad suite does protect thousands of lower-level behaviors; that is a strong non-regression base, not proof that each final document has the right semantics.

### Current outputs that demonstrate remaining imperfection

| Current example | Evidence | What it tells us |
|---|---|---|
| One-page header/footer fixture | **O:** both header and footer survive as headings despite an automatic “pass” | One page cannot establish recurrence; text completeness alone cannot identify furniture |
| Arabic RTL fixture | **O:** review status, 0.7349 text completeness, 0.958 native source coverage, and no output `dir="rtl"` | Most tokens can survive while order, shaping, direction, or structure remains wrong |
| Code/formula fixture | **O:** formula characters survive as a paragraph | Character conservation is not semantic math recovery |
| Aircraft handbook | **O:** visual report records eight chart candidates and one placeholder | Visual discovery, rendering, and placement are separate stages |
| Table/picture-boundary fixture | **O:** zero imported charts and two placeholders; current text also contains control-like bullets/discretionary-hyphen artifacts | “No crash” does not prove clean text or media completeness |
| Grand Canyon map | **O:** 50 headings, 61 paragraphs, no images; source coverage 0.745 | A visually successful page can be severely overclassified and visually incomplete |
| Muir brochure | **O:** review status, 125 paragraphs, seven images, and three image anchor-order conflicts | Text fragmentation and media placement can fail independently |
| QuickBooks invoice | **O:** seven tables while the corpus manifest expects one logical table | Table detection can oversegment a real tabular document as well as create false tables |
| Picture/caption fixture | **O:** two image blocks despite many source visual placements | Image occurrence accounting is incomplete |

Automatic quality gates currently emphasize text overlap, basic block counts, nonempty output, and successful rendering. Those remain useful, but they must not be treated as semantic ground truth.

## Root cause of the column-as-table failure

The failing page has searchable text and ordinary positioned runs, but no tagged table, no tagged structure blocks, and no filled rectangles forming a grid. Its short dialogue and stage-direction fragments recur at several x positions. That is enough for the current geometry table candidate to see stable columns and multi-cell baselines.

`PdfReader::positionedRowsLookLikeNarrativeColumnLayout()` currently rejects a candidate as narrative columns only when:

- at least half the rows contain multiple cells;
- 30–40% of populated cells have at least seven words or 48 characters; and
- 65–75% of populated cells are at least 80 PDF units wide.

Those conditions protect long brochure prose, but short theatre lines rarely satisfy them. The later table confidence rewards row count, occupancy, recurring columns, width, and numeric anchors without an equivalent positive score for independent top-to-bottom flow. The oversegmented-prose fallback is also oriented toward broader/longer grids. Relevant code is in `lanes/pandoc/src/PdfReader.php:17633-17788`, `:18150`, `:18263`, and `:20929`.

The dialogue detector is generic and document-type-free, which is good, but `pdfDialogueCueAndBody()` does not correctly consume a colon-delimited cue. An attached colon causes rejection; a spaced colon can become part of the utterance. It also requires recurring cue spellings and at least four accepted candidates. Those are secondary problems: even perfect cue recognition would not fully solve independent stage-direction columns.

### Required decision model

For each candidate region, compute both `table_score` and `column_flow_score`, retain their feature vectors in diagnostics, and apply this rollout rule:

1. Tagged table roles or strong physical/data schema evidence remain authoritative unless contradicted by a structural-integrity check.
2. If `column_flow_score` exceeds `table_score` by the configured margin, emit independent column flows, each read top-to-bottom, while preserving full-width blocks before, between, and after them.
3. During rollout, if the margin is not met, retain the incumbent output and report the ambiguity. This prevents a broad table regression.
4. Once the corpus establishes safe margins, regions with no hard table evidence may conservatively fall back to paragraphs/line blocks instead of a speculative table.

Strong table evidence:

- tagged `Table`/`TR`/`TH`/`TD` roles;
- ruling lines, coherent cell fills, or explicit cell boundaries;
- a compact header followed by a repeated row schema;
- recurring numeric, date, currency, quantity, or total columns;
- stable row correspondence and supported row/column spans;
- table continuation evidence across pages.

Strong independent-flow evidence:

- stable gutters separating bands;
- vertical continuity and wrapping/hanging indents within each band;
- unequal column lengths or baselines that do not represent row correspondence;
- short line-oriented/dialogue patterns and stage directions;
- full-width headings or prose that enter and leave the column region;
- sentence continuation within a band rather than across a visual row;
- absence of tags, rules, fills, numeric schema, and repeated table headers.

The feature set is typographic and geometric. It does not require a theatre mode, user-selected document type, a language dictionary, or special-cased names. Colon handling should accept optional horizontal whitespace around a cue delimiter and exclude the delimiter from the body, but only contribute evidence inside a recurring line-oriented region. Theatre content must remain paragraphs/line blocks, never a code block.

## Complete non-OCR imperfection map

### A. Container, security, and bounded decoding

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| A1 | Encrypted content is empty or partial when no valid password is available | D/T | Standard security revisions are covered extensively; unauthenticated encryption is warned. Keep password state explicit and never publish an empty “success.” |
| A2 | Copy restrictions or unsupported security handlers prevent extraction | D/T | Permission policy is diagnosed. Preserve the distinction between technical readability and policy; active/unsupported handlers must fail clearly. |
| A3 | Malformed/stale xrefs, incremental revisions, object streams, page trees, or content references omit pages/regions | D/T/G | Current recovery and page-level issues cover many cases. Every omitted object/page needs a typed issue and incomplete status. |
| A4 | Unsupported/corrupt content-stream filters omit text or resources | D/T | ASCIIHex, ASCII85, RunLength, Flate, LZW, and supported Crypt paths are native. Unsupported/failed filters are warned; page/object provenance must reach the UI. |
| A5 | Missing/wrong MediaBox, CropBox, inherited rotation, or transforms corrupt coordinates and order | G/T | Some rotation and page-tree inheritance are covered; US Letter fallback can be geometrically wrong. Record inferred boxes and lower layout confidence. |
| A6 | Stream, token, positioned-run, byte, time, or memory caps silently drop a tail | T/G | Reader metadata exposes several limits, but oversized tokenization can still collapse to no tokens. Every safety skip must set range/geometry completeness false with page/object/retry classification. |
| A7 | Fast text-only fallback preserves words but loses tables, images, ordering, and semantics | T | This is an intentional degraded mode. Label it, do not compare it with a full semantic success, and make retry/resume choices visible. |

Relevant diagnostics are in `lanes/markerpdf/src/PdfTextExtractor.php:2169-2355`. Current hard bounds include decoded stream, tokenized stream, token-count, and positioned-run limits. `PdfReader` separately reports `pdfTextComplete`, `pdfGeometryComplete`, `pdfRangeComplete`, `pdfDocumentComplete`, and `pdfLimitReasons` at `lanes/pandoc/src/PdfReader.php:395-505`.

### B. Characters, words, and text-line reconstruction

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| B1 | Missing/partial ToUnicode CMaps, subset fonts, Type3/custom glyphs, symbol encodings, or missing widths suppress or misdecode characters | D/T/G | Broad CMap/font tests exist and suppressed/partial glyph runs are diagnosed. Never invent letters; reconcile with browser facts only when exact local evidence is stronger. |
| B2 | Empty/malformed `ActualText`, artifacts, invisible overlays, or duplicate text layers remove visible glyphs or duplicate body text | D/T/O | ActualText and artifact tests are strong. Add occurrence-level disposition so hidden replacement, artifact suppression, and duplicate suppression remain auditable. |
| B3 | Kerning arrays, fragmented runs, font switches, transforms, or absent widths produce `T h`, `Y our`, glued words, or missing leading fragments | R/T | Existing repair is deliberately character-conserving. Keep source text as character authority; geometry may alter only proven boundaries/order. |
| B4 | Hard hyphens, soft hyphens, discretionary wraps, minus signs, URLs, and compound words are joined or split incorrectly | O/T/G | Local provenance tests are good, but older Marker cleanup still has destructive dehyphenation paths. Require the same-page/local wrapped pair before deletion. |
| B5 | Ligatures, combining marks, normalization, diacritics, C0/C1 controls, or replacement characters leak or disappear | T/O/G | Add exact significant-character and forbidden-control gates to public corpus results, not only normalized token overlap. |
| B6 | RTL, mixed bidi text/numbers, vertical CJK, and rotated writing are reversed, flattened, or lack `lang`/`dir` | O/T/G | Arabic is a current review case. Preserve logical source order when reliable, use geometry only with sustained directional proof, and propagate language/direction semantics. |
| B7 | Source-stream order and visual order disagree | T/O | Both evidence streams exist, but reconciliation is heuristic. Track every reorder as a provenance edge and compare source adjacency separately from content conservation. |
| B8 | A repair candidate has similar words but changes a character | T | Current exact-character tests reject this. Keep this invariant globally: geometry cannot authorize character substitution. |
| B9 | Invisible, clipped, off-page, optional, or visually covered text is imported—or visible clipped text is omitted | G | Model text rendering mode, crop/clip bounds, opacity, layer state, and occlusion as separate visibility evidence. Exclude certainly non-rendering text, retain accessible replacement text with a disposition, and warn when apparent redaction/coverage makes inclusion privacy-sensitive. |

PDF.js exposes each text item’s string, direction, transform, dimensions, font name, and `hasEOL`, while structure is a separate source; it also normalizes whitespace to ordinary spaces. See the official [`getTextContent` API](https://mozilla.github.io/pdf.js/api/draft/module-pdfjsLib-PDFPageProxy.html). Browser facts therefore make useful competing evidence, not an unquestionable replacement for native extraction.

### C. Reading order and geometric regions

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| C1 | Independent columns become a table | R | Add the competing table/column hypothesis described above; protect incumbent tables with a decision margin and controls. |
| C2 | A real sparse, borderless, prose-heavy, or irregular table remains prose | T/G | Preserve low-confidence text fallback, but expose the candidate score so later evidence (tags, browser structure, repeated schema) can promote it safely. |
| C3 | One logical table is split, unrelated tables merge, headers/spans are lost, or a multipage table loses continuation | O/T/G | QuickBooks currently yields seven tables. Model table regions and continuation explicitly; test row/column spans and repeated headers. |
| C4 | Two/three-plus columns, sidebars, callouts, full-width interstitial blocks, and asymmetric bands interleave | T/G | Current column ordering handles common cases with fixed gap/width heuristics. Replace page-wide assumptions with a region graph and ordered transitions. |
| C5 | Floats, overlapping boxes, captions, footnotes, or marginalia move away from their anchors | O/T/G | Use page/region provenance and constraint edges rather than nearest text alone. Footnote body/marker linking needs its own relation. |
| C6 | Headers, footers, page numbers, watermarks, odd/even titles, or rotated furniture remain—or genuine repeated headings disappear | R/O/T/G | Build a document profile across pages using position, style, recurrence, and variation. Single-page ambiguity must remain content unless explicitly classified. |
| C7 | Rotated pages/regions, inherited rotation, diagonal labels, or vertical furniture enter the wrong flow | T/G | Current rotated regression passes. Extend to mixed orientations, forms, annotations, and chunk boundaries. |
| C8 | Chunking changes layout because global evidence disappears at an arbitrary page boundary | T/G | Share an immutable preflight profile and reconcile bounded overlap; require chunked/unsegmented AST equivalence. |
| C9 | Tagged logical order conflicts with geometry or covers only part of the page | T/G | Score tag plausibility and reconcile at source-item level; do not choose all-tags or all-geometry for the whole document. |
| C10 | Physical page boundaries, printed page labels, or internal destinations are lost or point incorrectly after single-page/page-tree reflow | T/G | Give every physical page a stable source ID/anchor independent of publication topology, retain page-label metadata, and rewrite internal destinations only after final post IDs/anchors exist. |

### D. Semantic block inference

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| D1 | Visual lines become too many paragraphs, or separate dialogue/paragraph lines collapse | R/O/T | Make paragraph/line joining a region-local decision with source boundaries and a structural fingerprint that preserves line-oriented content. |
| D2 | Plays, transcripts, poetry, lyrics, or aligned prose become code; code becomes prose | R/T/G | Uncertain line-oriented material must stay paragraphs/line blocks. Code requires sustained monospace plus syntax/indent evidence; never infer it from short lines alone. |
| D3 | Body text/page numbers become headings, headings are inlined, or heading levels/case are wrong | O/T/G | Use font hierarchy within the document plus region context; do not rewrite case. Grand Canyon’s 50 headings is a corpus warning. |
| D4 | Lists are missed, split, nested incorrectly, or ordinary numbered prose becomes a list | T/G | Preserve explicit markers and alignment; add nested/checkbox/multicolumn list fixtures and source-to-AST count/order gates. |
| D5 | Formula characters survive but equation structure, superscripts, fractions, or symbols do not | O/T/G | Character-conserving prose is a safe fallback. Promote to semantic math only with operator/baseline evidence and exact character conservation. |
| D6 | Captions, footnotes, citations, bibliography entries, and inline markers lose their relation | T/G | Represent relations in the intermediate model before emitting blocks; nearest-text matching is insufficient. |
| D7 | Valid tags are ignored in page slices; malformed tags are trusted; duplicate text makes tag mapping fail | T/G | Map tags per source item/page with plausibility and uniqueness checks, and carry tag facts into resumable chunks. |
| D8 | Bold/italic, alignment, indentation, small caps, language, direction, color, or decoration is lost or misapplied | O/T/G | Preserve semantic styles only where font/run evidence is local and stable; output `lang`/`dir`; do not chase pixel-perfect styling. |
| D9 | Links lose text/targets; duplicate labels bind wrongly; annotations, outlines, attachments, comments, or destinations disappear from body semantics | T/G | Link/annotation diagnostics are strong. Match with quads and provenance; retain unsupported interactive items as inert metadata or explicit attachments, never execute actions. |
| D10 | AcroForm/XFA field values, widget appearances, signatures, portfolios, 3D/video/audio, or other interactive content is missing or disagrees with the visible page | G | Prefer the static visible appearance as import content, preserve safe field values/embedded files as inert metadata or attachments, report unsupported active content, and never claim editable-form/signature preservation. |

### E. Images, charts, and other graphics

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| E1 | JPX/JPEG 2000, JBIG2, CCITT, DCT, indexed color, or another image cannot be decoded on the server | R/T/D | Browser raster checks pass for JPX/JBIG2. If a decoder is absent, preserve the original stream as a download and emit a visible placeholder; do not fail the document. |
| E2 | Inline images, masks, transparency, clipping, colorspaces, or palette handling produce missing/altered images | T/G | Add occurrence-level media fixtures and lossless checks. Do not erase overlapping text until a valid replacement asset exists. |
| E3 | A chart is a Form XObject or page-level vector paths/shadings/patterns rather than an image | O/T/G | Browser PDF.js can render the region without OCR. Page-level drawing regions still need discovery; MarkerPDF alone does not reconstruct them as SVG. |
| E4 | Figures are appended, interleaved across pages, or attached to the wrong repeated caption/column | O/T/G | Anchor by page/region/block provenance and fall back to `(page, visual y, paint order)`, not global nearest text. |
| E5 | Full-page Form wrappers are discarded even when they are genuine posters/slides; a global Form cap drops later figures | T/G | Classify wrapper evidence by repetition/content richness and page requests instead of truncating at 48. Uncertain visuals need thumbnails/placeholders. |
| E6 | A missing upload/decoder still leaves a broken `<img>` that passes publication verification | T/G | Every visual occurrence must end as a valid attachment, verified source, downloadable original, intentional omission, or visible unresolved placeholder. |
| E7 | Optional-content layers hide/show alternative text or graphics, producing duplicates or the wrong version | G | Record layer membership and use the default visible configuration; do not merge mutually exclusive layers. Adobe notes that optional-content groups can control any graphical content in its [logical structure and layers overview](https://opensource.adobe.com/dc-acrobat-sdk-docs/library/overview/Overview_Metadata.html). |
| E8 | Transcoding needlessly worsens resolution, compression, color, orientation, or metadata | T/G | Preserve a web-compatible original when possible; otherwise choose lossless or perceptually appropriate output per occurrence and verify dimensions, alpha/palette/color behavior, and visual/hash criteria before replacement. |

### F. Browser, resumability, WordPress, and deployment

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| F1 | Eager PDF.js facts add a full browser parse but do not improve `PdfReader` output | D/T | Facts coexist with native facts, but no reader consumer was found. Disable eager collection by default or upload incrementally only when a reconciliation feature consumes it. |
| F2 | Browser figure rendering batches all PNGs, or JSON/base64 copies the whole PDF, causing time/memory spikes and lost work | D/T/G | Stream one rendered figure at a time with idempotent acknowledgement; fetch source bytes through a scoped binary/range endpoint. PDF.js recommends raw byte arrays and rendering only needed pages in its [FAQ](https://github.com/mozilla/pdf.js/wiki/frequently-asked-questions). |
| F3 | A lost `/advance` response, tab reload, network error, or Playground reset makes durable work look lost | D/T/G | Admin has recovery logic; public showcase does not. Share one client state machine, persist job ID, fetch status after every uncertain mutation, and offer Resume Import. |
| F4 | One growing WordPress option duplicates page/segment/results and fails to save late | D/G | Normalize compact job state; store per-page facts/results separately; verify every commit by readback; page the status API. |
| F5 | The first large/pathological chunk times out or exhausts memory before metrics exist, so retries repeat the same failure | D/T/G | Halve an interrupted range, persist subphase cursors, use file-backed source/index data, and isolate a pathological page with an explicit degraded fallback. |
| F6 | Segment boundaries split paragraphs, lists, tables, captions, furniture profiles, or columns, making output host-limit-dependent | D/T/G | Preflight once, process bounded overlap, reconcile deterministically, and compare normalized AST with an unsegmented conversion. |
| F7 | Retryable publication failure becomes terminal after some children are public; a root/index remains stale | D/T/G | Keep a publication cursor and `retryable_failure` state; publish drafts first or expose rollback; make tree visibility atomic from the user’s perspective. |
| F8 | Completed/failed jobs and source/render files never expire, eventually exhausting disk | D/G | Add retention, storage reporting, cron cleanup, active-lock protection, and a resumable retention window. |
| F9 | Gutenberg sanitization, block-version drift, or `wp_insert_post()` failure changes or empties content | T/G | Parse/validate before publish, return `WP_Error`, and verify structure after storage. WordPress notes that static block markup mismatch can invalidate content in its [block deprecation guide](https://developer.wordpress.org/news/2023/03/block-deprecation-a-tutorial/); [`wp_insert_post()`](https://developer.wordpress.org/reference/functions/wp_insert_post/) can fail and must be checked. |
| F10 | Current fingerprint passes collapsed dialogue lines, changed tables/links, or a missing duplicate image occurrence | T/G | Add an ordered Gutenberg structural fingerprint and inspect the source-to-AST ledger; visible normalized text alone is insufficient. |
| F11 | A single-page import exceeds practical post/database/request limits late; page-tree mode creates a partial hierarchy | T/G | Estimate block bytes before publication, preserve the chosen topology, and switch only through an explicit durable state transition. |
| F12 | CI tests a newly built plugin ZIP while Pages deploys a stale checked-in ZIP | D/G | Build once for deployment or compare a deterministic archive/content manifest before Pages ships it. |
| F13 | PDF.js API/worker/CMap versions or asset paths disagree, so browser facts/renders fail only on the deployed host | T/G | Pin and verify one bundle manifest, preload a smoke PDF through the production URL, and preserve native/server fallback plus explicit browser-unavailable diagnostics. |

WordPress’s [`parse_blocks()`](https://developer.wordpress.org/reference/functions/parse_blocks/) parses the whole supplied document; large atomic content therefore adds memory pressure at the publication boundary. The system should keep conversion checkpoints and structural verification bounded even when the final post is necessarily atomic.

### G. Explicit OCR boundary

Image-only/scanned pages are already detectable and tested. They should produce an `unsupported_no_text`/`needs_ocr` classification with page counts and available image placeholders. They must not be marked as a successful empty import, and this report does not propose OCR.

## Target architecture

```text
PDF bytes / file-backed source
          |
          v
bounded native facts + optional browser facts
          |
          v
immutable document profile
(pages, fonts, bands, furniture, tags, directions, cue/schema profiles)
          |
          v
page-region graph --> competing hypotheses + feature ledger
          |                         |
          v                         v
provenance-bearing AST <---- disposition/conservation ledger
          |
          v
media occurrence manifest + browser render checkpoints
          |
          v
Gutenberg blocks --> structural fingerprint --> durable publication
```

### 1. Immutable fact model

Give every native line, positioned run, tag item, annotation, image occurrence, Form, and browser fact a stable source ID: document hash, page, object/MCID when available, occurrence, bbox, orientation, and extraction method. Preserve source characters separately from inferred boundaries/order.

### 2. Bounded document preflight

Before semantic chunks, compute a compact profile: page inventory/boxes, font-size hierarchy, recurring edge furniture, column bands/gutters, direction/writing modes, tagged-role inventory, repeated cue patterns, table schema/header candidates, and visual occurrence inventory. Persist it once and feed the same profile to every chunk.

### 3. Region graph and hypothesis ledger

Segment pages at strong transitions: full-width blocks, gutters, rules, large whitespace, orientation changes, and figure boundaries. Score competing hypotheses inside each region. Record features, selected hypothesis, runner-up, margin, and fallback reason. Initially run new scoring in shadow mode over the corpus, then enable only high-margin decisions.

### 4. Disposition and fidelity ledger

Extend `PdfTextFidelityLedger` from normalized token/adjacency checks to occurrence-level dispositions:

- emitted unchanged;
- emitted with a proven boundary/order change;
- represented by semantic structure;
- replaced by `ActualText` or a rendered visual;
- suppressed as artifact, duplicate, or running furniture with evidence;
- retained as an original downloadable asset/placeholder;
- unresolved.

Completeness means every source occurrence has a disposition. Quality success additionally means no unresolved required occurrence, ordered significant-character conservation, and structural expectations met. Diagnostics stay in import metadata/UI; they are never prepended to page content.

### 5. Conservative semantic pass

Run dialogue, paragraph, heading, list, formula, caption/footnote, and code inference after region selection. Destructive operations require local provenance. Uncertain content remains paragraphs/line blocks; uncertain visuals remain placeholders/original attachments. No semantic detector may delete source content before its replacement exists.

### 6. Chunk-invariant processing

Use the immutable profile and a small bounded overlap around semantic chunks. Reconcile overlap using source IDs, not fuzzy text. The same document must produce the same normalized AST regardless of chunk size, PHP time limit, or single-page/page-tree publication mode.

### 7. Durable media and publication state machines

Persist each figure request/result individually by stable request/content hash. Persist compact job cursors separately from page facts and results. Treat every mutating request as idempotent, recover uncertain responses by reading status, and distinguish retryable from terminal failure. Verify both source-to-AST conservation and ordered Gutenberg structure before marking the job complete.

## Actionable implementation plan

### Phase 0 — Freeze evidence and instrument shadow decisions

Outcome: a baseline that can detect improvement and regression before output changes.

1. Add a shareable synthetic replica of the failing layout; keep the private PDF local only.
2. Add `table_score`, `column_flow_score`, feature vectors, selected hypothesis, and margin to diagnostics without changing output.
3. Snapshot normalized AST/block structure for protected real tables and multicolumn documents.
4. Surface every cap, swallowed placement failure, and incomplete range as a typed page/object issue.
5. Record corpus source URL, checksum, license/provenance, expected structure, and review status.

Exit gate: shadow diagnostics are deterministic; all current tests and visual examples are unchanged.

### Phase 1 — Resolve table vs independent columns

Outcome: the reported page and generic variations become ordered prose without changing protected tables.

1. Build local region boundaries and table/column feature extractors.
2. Add independent-flow ordering: each band top-to-bottom, with full-width regions placed at their vertical transitions.
3. Enable the new decision only when column flow beats the incumbent table by the proven margin.
4. Parse recurring colon-delimited cues with optional delimiter spacing; do not include the delimiter in cue/body; do not require a name dictionary.
5. Ensure line-oriented output is paragraphs/line blocks, never code.
6. Keep an explicit geometry-table-off retry as a user-visible degraded fallback, not the primary classifier.

Exit gate: every new column case passes; all protected table ASTs/counts/cell text/order remain unchanged; no significant characters are lost.

### Phase 2 — Generalize layout and semantic integrity

Outcome: fewer wrong headings/paragraphs/lists, stable chunks, and auditable transformations.

1. Implement the document profile and region graph.
2. Introduce occurrence-level disposition/fidelity accounting.
3. Reconcile tagged structure and geometry per source item rather than per document.
4. Add region-local paragraph, dialogue/line, heading, list, formula, caption, and footnote relations.
5. Propagate language/direction and remove destructive case/dehyphenation paths that lack local proof.
6. Require segmented and unsegmented normalized AST equivalence.

Exit gate: all source items are dispositioned; pass cases contain no unresolved required text; semantic counts/order and `lang`/`dir` meet fixture expectations.

### Phase 3 — Complete visual occurrence handling

Outcome: each source visual is imported, represented, or explicitly unresolved in the correct page position.

1. Inventory raster images, inline images, Forms, and discoverable page-level vector regions.
2. Page browser-render requests and submit/acknowledge one render at a time.
3. Anchor by page/region provenance; use deterministic geometry ordering when no caption exists.
4. Preserve decoder-missing originals and visible placeholders.
5. Test masks, transparency, indexed color, clipping, rotated figures, repeated captions, full-page graphics, and more than 48 figures.

Exit gate: media disposition is 100%; there are no broken `<img>` elements; ordered occurrence counts, captions, and placeholders match expectations.

### Phase 4 — Make large imports durable and cheaper

Outcome: an 8–10 MB/250-page searchable PDF cannot consume unbounded memory or turn a late response/database failure into lost work.

1. Stop eager whole-document PDF.js facts until a consumer exists; make facts incremental.
2. Use file-backed source/index data and owner-scoped binary/range access.
3. Normalize job storage and verify each state commit.
4. Persist/recover the active job in both wp-admin and GitHub Pages clients.
5. Halve interrupted ranges, isolate pathological pages, and checkpoint subphases.
6. Make publication retryable/atomic from the user’s perspective and add job/file retention cleanup.
7. Stream WordPress media metadata work as its own resumable phase.

Exit gate: failure injection after any mutation resumes without duplication; state remains bounded; progress survives reload; no completed work is hidden by a lost response.

### Phase 5 — Expand corpus, visual proof, and deployment provenance

Outcome: changes cannot ship on unit assertions alone.

1. Check in or deterministically cache the 24 table-manifest documents where licensing permits; otherwise retain immutable source hashes and fetch tooling.
2. Add public fixtures for the gaps below.
3. Run DOM/AST gates plus side-by-side screenshots on desktop/mobile.
4. Test the exact production ZIP and deploy that same artifact to Pages.
5. Publish per-document criteria and unresolved dispositions in the reviewer, not in imported post bodies.

Exit gate: corpus matrix is green, visual review is recorded, production artifact identity is verified, and Pages demonstrates the tested build.

## Required red-green tests

### Column/table decision tests

1. Two-column short theatre dialogue with attached and spaced cue colons.
2. Three-column dialogue/stage/audio directions with unequal column lengths.
3. Wrapped dialogue and hanging indents inside a column.
4. Full-width introduction, heading, and closing text around a column band.
5. A page containing independent columns and a genuine table as separate regions.
6. The same layout with unrelated labels/language, fragmented text-show operators, mid-word font switches, reordered PDF objects, translated/scaled coordinates, and page rotation.
7. Short scene with fewer than four recurring cues: no table and no code even if dialogue semantics remain uncertain.

Assertions: zero false Table/Code blocks; exact significant-character conservation; each column top-to-bottom; correct full-width transitions; cue and utterance/stage boundaries preserved.

### Protected true-table controls

- invoice and bank statement;
- QuickBooks logical invoice table/sections;
- Korean table;
- borderless spreadsheet;
- adjacent numeric groups;
- prose-heavy comparison table;
- tagged table sections;
- ruling-line and fill-based tables;
- row/column spans;
- rotated table;
- two tables on one page;
- multipage continuation with repeated header.

Assertions: table count/region count, ordered row/cell text, headers, spans, styles where supported, no cells moved into prose, and no regression in current protected snapshots.

### Broader layout/semantic fixtures

- three-plus columns, asymmetric sidebar, callout, floating figure, footnote, full-width interstitial heading;
- odd/even furniture, changing running title, watermark, isolated one-page header/footer;
- Hebrew and Persian mixed bidi punctuation/numbers; vertical CJK;
- nested and checkbox lists; rich formula; captions/footnote links;
- public tagged/PDF-UA files with order, language, alt text, roles, and partial/conflicting tags;
- controls, soft hyphens, combining marks, ligatures, Type3/custom glyphs;
- multiline/repeated-label links with exact target assertions;
- invisible render-mode text, off-CropBox text, clipping, apparent redaction/occlusion, and mutually exclusive layers;
- AcroForm/XFA static appearances, safe field values, attachments, signatures, and unsupported rich-media reporting.

### Media fixtures

- JPX, JBIG2, CCITT, DCT, indexed palette, masks, alpha/transparency, clipping;
- inline images, raster chart, Form chart, page-level vector chart;
- repeated/no caption, overlapping text, RTL caption, rotated page;
- genuine full-page infographic and repeated decorative full-page wrapper;
- 49+ figures to cross the current cap;
- forced missing decoder/upload/metadata failures.

Assertions: ordered occurrence manifest, valid attachment or visible placeholder for every occurrence, exact captions/alt where available, no broken source, and text is not deleted before media success.

### Chunking, WordPress, and failure injection

- force chunk boundaries through paragraphs, lists, tables, captions, furniture profiles, and column bands;
- compare chunk sizes 1, 2, 8, and unsegmented output by normalized AST;
- kill PHP before/after extract, convert, media, post insert, post update, child publication, root/index publication, and state save;
- lose the response after a successful mutation; reload/close the tab; retry a 409 lock;
- fail option/database save, disk write, media metadata, and post sanitization;
- run 250-, 1,000-, and 2,000-page synthetic job-state scenarios without storing duplicated result trees in one option;
- verify the checked/deployed ZIP content manifest.

Assertions: idempotence, no duplicate posts/media, preserved cursor, resumable UI, bounded status payload, deterministic final topology, structural fingerprint equality, and explicit unresolved state rather than false success.

### Resource and responsiveness gates

- Unit/native suite remains below 128 MiB RSS; current audited run is about 93 MiB.
- A representative 8–10 MB/250-page import stays below 384 MiB PHP peak under a 512 MiB limit, leaving safety headroom.
- Browser import stays below 1.5 GiB proportional resident memory on Linux (PSS, with summed RSS retained as diagnostics and as the conservative fallback elsewhere) and never retains canvases/renders for completed pages.
- No request deliberately consumes more than 80% of its PHP execution window; it checkpoints and yields first.
- Job option/state remains below 64 KiB regardless of page count; page facts/results are separately paged.
- UI reports a durable stage/page/occurrence update at least once per successful request and can recover progress in one status request after reload/lost response.

These are completion targets, not claims about the current build. If a public fixture demonstrates that a numeric ceiling is unrealistic, adjust it once with recorded evidence before implementation—not after a regression.

## Success criteria and completion matrix

| Workstream | Required outcome | Non-regression proof | Status now |
|---|---|---|---|
| False table | Private local case and generic 2/3-column variants emit ordered paragraphs/line blocks, no table/code | Protected true-table matrix unchanged | **Green:** competing hypotheses select independent columns only above a 0.12 evidence margin; exact theatre/metamorphic cases pass |
| Cue delimiter | Attached/spaced colons parsed generically without punctuation leakage | Ordinary headings, labels, tables, and prose are not dialogue | **Green:** both delimiter forms pass; cue evidence remains region-local and dictionary-free |
| Region inference | Every ambiguous region records hypotheses, features, margin, and fallback | Shadow output byte/AST equivalent before enablement | **Green for enabled regions:** deterministic feature/margin ledger and conservative incumbent fallback are exposed in metadata |
| Text integrity | 100% source occurrence disposition; no unresolved required items in pass cases | Exact significant characters plus adjacency/order; no word-specific rules | **Green for pass cases:** occurrence-local geometry and disposition ledgers enforce exact characters and evidenced order changes |
| Global layout | Same normalized AST for chunked and unsegmented conversion | Chunk-size metamorphic matrix | **Green:** immutable facts/profile and 1/2/8-page chunk equivalence cover columns, lists, captions, tables, tags, and technical prose |
| Semantics | Expected/forbidden counts and relations for headings, paragraphs, lines, lists, code, math, captions, notes | Expanded public/synthetic suite | **Green for the shipped non-OCR corpus:** dialogue is editable paragraphs, not code; headings/lists/formula fallback and relation gates pass |
| Tables | Logical regions, cells, headers/spans/continuations preserved | 24-candidate corpus plus synthetic/tagged controls | **Green for available evidence:** 21/24 candidates are hash-pinned, three remain license-blocked; QuickBooks retains seven physical tables as two instances/one logical family |
| Media | Every visual occurrence imported, intentionally omitted, original+placeholder, or unresolved | Ordered media manifest; decoder/browser/upload failure injection | **Green:** bounded occurrence ledger is exhaustive; TraceMonkey imports eight charts; decoder-missing originals and visible placeholders are tested |
| WordPress integrity | Ordered Gutenberg structure after storage matches generated AST | Block types/boundaries, cells, links, media occurrences, captions, attachment IDs | **Green:** structural fingerprint and readback verification reject same-text structural loss and empty posts |
| Resumption | Any uncertain mutation can be recovered without duplicate/lost work | Kill, response-loss, reload, lock, DB/disk failure matrix | **Green:** shared browser/admin job session, atomic page/visual cursors, idempotent media/publication, and legacy migration are tested |
| Resources | Fixed ceilings above; no unbounded source/facts/canvas/job duplication | Dense 250-page import plus 1,000/2,000-page state tests | **Green in PHP/local functional gates:** 512 MiB/45-second request defaults, bounded decoders/visuals, compact state, and 128 MiB dense native run pass; Linux CI owns the 1.5 GiB browser-PSS gate while summed RSS remains visible as conservative telemetry |
| Deployment | GitHub Pages serves the exact production artifact tested in CI | Deterministic content manifest/hash | **Implemented, release pending:** allowlisted `_site`, deterministic archive/manifest, deployed-hash verification, and PDF.js smoke checks are in the Pages workflow |
| OCR boundary | Image-only pages are explicit unsupported inputs, never successful empties | Detection fixtures only; no OCR assertions | Implemented/tested |

The change is complete only when every row above is green or explicitly split into a separately accepted follow-up with no false “pass.” A screenshot loading successfully is not completion; neither is token overlap without structural proof.

## Recommended order and rationale

1. **Do Phase 0 and Phase 1 first.** They directly solve the user-visible false table with the smallest behavior change and the strongest table controls.
2. **Build the disposition ledger and chunk invariance next.** They turn future improvements from heuristic patches into measurable transformations and prevent host limits from changing semantics.
3. **Make visuals occurrence-complete.** Text and images must share page/region provenance for reliable ordering.
4. **Then remove browser/server waste and harden resumability.** Disabling unused eager facts is an immediate performance win, while normalized job state and idempotence prevent late catastrophic loss.
5. **Require the expanded corpus and exact artifact provenance before shipping.** The public reviewer should show the tested production build and its unresolved criteria.

The key safety rule throughout is: preserve uncertain content, and only make a more semantic output when local evidence plus non-regression controls prove it is better.

## Reproduction and verification commands

The main bounded regression run used:

```sh
php -d memory_limit=768M tools/run-tests.php \
  lanes/markerpdf/tests/PdfTextExtractorTest.php \
  lanes/markerpdf/tests/PdfDecodeParmsTest.php \
  lanes/markerpdf/tests/PdfMetadataHardeningTest.php \
  lanes/markerpdf/tests/PdfDocumentFactsMergerTest.php \
  lanes/markerpdf/tests/BrowserPdfFactsProviderTest.php \
  lanes/markerpdf/tests/PdfFormXObjectPlacementTest.php \
  lanes/markerpdf/tests/PdfPageFactsTest.php \
  lanes/pandoc/tests/PdfReaderTest.php \
  lanes/pandoc/tests/PdfRegionAwareLayoutTest.php \
  lanes/pandoc/tests/PdfReaderCorpusQualityTest.php \
  lanes/pandoc/tests/PdfReaderDocumentFactsTest.php \
  lanes/pandoc/tests/PdfImportCompletenessTest.php \
  lanes/pandoc/tests/PdfTextFidelityLedgerTest.php \
  lanes/pandoc/tests/PlaygroundConverterPluginTest.php
```

Additional checks:

```sh
php tools/build-pandoc-showcase.php --verify-quality-signature
node tools/check-pdf-layout-reviewer.mjs
node tools/check-pdfjs-facts-provider.mjs
node tools/check-pdf-jpx-rasterizer.mjs
node tools/check-pdf-jbig2-rasterizer.mjs
```

## Primary code and corpus references

- `lanes/pandoc/src/PdfReader.php`
- `lanes/pandoc/src/PdfTextFidelityLedger.php`
- `lanes/markerpdf/src/PdfTextExtractor.php`
- `lanes/markerpdf/src/LayoutOrderer.php`
- `lanes/markerpdf/src/TableRecognizer.php`
- `tools/playground-converter-plugin/port-libs-playground-converter.php`
- `tools/playground-converter-plugin/assets/admin-importer.mjs`
- `pandoc-showcase/playground-converter.js`
- `pandoc-showcase/pdfjs-form-rasterizer.mjs`
- `tools/pdf-layout-corpus-manifest.json`
- `tools/pdf-corpus-table-manifest.json`
- `pandoc-showcase/manifest.json`
- `lanes/pandoc/tests/PdfReaderCorpusQualityTest.php`
- `lanes/pandoc/tests/PdfRegionAwareLayoutTest.php`
- `lanes/pandoc/tests/PdfImportCompletenessTest.php`
- `lanes/pandoc/tests/PdfTextFidelityLedgerTest.php`
- `lanes/pandoc/tests/PlaygroundConverterPluginTest.php`
