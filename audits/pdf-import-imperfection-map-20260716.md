# PDF import imperfection map and non-regression plan

Date: 2026-07-19

Branch: `pdf-visual-reviewer-20260715`

Evidence states:

- Reproducible before-state: `cba982f61`.
- Main implementation: `753f4e1b5`.
- Current code and corpus audited: `fc25ef955267bdb48384b406f7d517c58d898bab`.
- Release state: [PR #32](https://github.com/adamziel/port-libs/pull/32) is open and clean; the current head passed the [Playground workflow](https://github.com/adamziel/port-libs/actions/runs/29542448028) and was deployed and artifact-verified by the [Pages workflow](https://github.com/adamziel/port-libs/actions/runs/29542917386).

The original false-table problem is fixed at the audited commit. At that
immutable head, the overall PDF importer was **not** fully green: the probes and
public corpus output demonstrated false completeness, invisible-text inclusion,
destructive prose repair, partial-tag arbitration, semantic fragmentation, and
occurrence-level media loss or misplacement. The execution update below records
which of those findings the newer uncommitted working tree mitigates and which
release blockers remain.

Scope: searchable PDFs imported through MarkerPDF/Pandoc into WordPress, including the browser-assisted and resumable plugin path. OCR is explicitly out of scope.

## Execution update — 2026-07-19

This plan has been implemented in the working tree on top of
`fc25ef955267bdb48384b406f7d517c58d898bab`. The implementation and regenerated
showcase are intentionally uncommitted, and the previously deployed artifact
does not contain these working-tree changes.

Implemented work includes occurrence-exact source binding and locally scoped
reorder proof; fail-closed publication for unresolved required source items;
explicit visibility handling for text rendering modes, alpha, optional-content
groups, page boxes, partially visible `TJ` operands, and occlusion diagnostics;
container/decoder hardening that treats each page's `/Contents` array as one
bounded logical program, segments it at unknown members, resets inherited text,
graphics, and Form-operand state at those gaps, and retains independently
reopened post-gap text only as uncertified source text; region-local tag
arbitration; conservative prose, formula, and RTL handling; occurrence-level
media disposition, placement, bounded Form traversal, and unique issue
accounting; durable resumable upload metadata, cancellation, lock, and cleanup
behavior; and immutable corpus receipts with a parent-enforced conversion
watchdog.

### Verification performed on the working tree

| Evidence | Result |
|---|---|
| Current regression evidence | **Green:** the final 14-file bounded matrix passed 8,215 assertions; the additional 6-file matrix passed 583; the 4 isolated pipeline/memory/plugin/expectation suites passed 1,997; the final converter suite passed 375; the overlapping 20-file untracked-PDF/converter run passed 1,458; the two generated-showcase PHP suites passed 2,810; and the optional one-file Haskell-timeout suite passed 5 assertions. All recorded runs have 0 failures; overlapping assertion totals are not added together. |
| Generated showcase | **Regenerated, broader release gate still failing:** generated at `2026-07-19T05:41:43+00:00` with 109 samples across 40 formats and quality summary **98 pass / 1 review / 10 fail**. The common tracked segment remains release-red at **51 pass / 1 review / 9 fail**. The sole review is the safely represented OCR-overlay fixture. Nine successful PDF conversions remain fail closed on source, visibility, or page-representation evidence; MinerU is a typed `unsupported_no_text` editable-conversion refusal with eight exact browser page requests and zero materialized page representations. CDC exposes 7 image blocks, and Motograph's anchored JPEG 2000 wordmark is now a validated 28,048-byte AVIF without crediting its missing pages. |
| Post-build consistency checks | **Pass:** all nine preflight/JavaScript checks passed: exact quality signature; 109/109-entry examples page; 14-document layout reviewer; import-job session/cancellation; converter UI; PDF Form and whole-page rasterization; PDF.js facts; 1,800×750 JPX rasterization with lossless CDC palettes; and two-image JBIG2 rasterization. Manifest, compact-catalogue, reviewer, segment, conversion, faithfulness, and bibliography totals reconcile internally. These checker passes do not override the failing import-quality gate. |
| Full 24-candidate corpus report | **Executed and baseline-green:** all 21 obtainable pins verified; 4 checked-in and all 17 remote documents executed in 42/42 modes with 0 conversion or artifact failures; all 4 recorded semantic baselines passed; 17 remote candidates still await manual verdicts; 3 license-blocked candidates remain excluded. |
| Native resource gates | **Pass:** TraceMonkey peaks at 41,943,040 allocated bytes under the unchanged 46,137,344-byte ceiling in a 48 MiB process; the 250-page searchable fixture passes its 128 MiB gate; IRS 15-T geometry completes in 142.876 seconds with a 492,699,648-byte allocated peak and 44,171,264 bytes of headroom under 512 MiB. |
| Deterministic plugin package | **Pass, not promoted:** two temporary final-source builds were byte-identical; 8,254,562 bytes, 619 entries, 68,510 bytes below the 8,323,072-byte upload ceiling, SHA-256 `7001d5d727504c4476cee6819a6ab2e32954d3e92be51176457588b04ae28329`. Their byte-identical 133,506-byte manifests hash to `6f05285827b074d545f25866bdbaab5838939560c0b36cca0bbcb8fa07ddb6da`. The verifier proved required readers, writers, browser decoders, shared WASM, and legal files; an extracted-package CSV/TSV autoload and conversion smoke also passed. The checked worktree ZIP remained unchanged and distinct at 8,313,372 bytes, 618 entries, SHA-256 `e4659f62ad00e5d2bbcf1806dc9ee5fcf2721300bfc7a28dd1b14c8fc84dd6e6`. |
| Real dense-browser release gate | **Environment blocked before conversion:** two macOS Playground boots grew from about 1,108 to 1,648 MiB and 1,123 to 1,580 MiB summed RSS, already beyond the 1.5 GiB ceiling. The final host is Darwin/arm64 and exposes no Docker, Podman, Lima, Colima, or Multipass runtime, so the required Linux PSS measurement was not run. |

The complete corpus report is recorded in
`.port-libs/pdf-corpus/report.json` and `.port-libs/pdf-corpus/report.html`. It
was generated at `2026-07-19T05:35:06+00:00` and exits successfully. All 42
requested geometry/repair modes completed under the parent watchdog with zero
conversion failures. CDC's semantic baseline now
proves 26 paragraphs, 14 list starts, all 35 visual occurrences, 0 unresolved
source occurrences, and 0 unresolved media occurrences. Seven occurrences are
resolved and 28 are intentional omissions. The paragraph count changed from
27 to 26 because the exact `aves Lives` clipped-display occurrence is now a
signed artifact with no text destination; its complete remote counterpart
preserves the semantic text. A separately validated media-only bridge uses that
fact solely to authorize the existing same-page geometric fallback for four
important image tiles. It never treats the remote counterpart as their
insertion anchor.

The generated showcase deliberately remains release-red. Its locked release
expectations are: 98 automatic passes versus the historical 105-pass target,
one safely represented OCR-overlay review, nine successful PDF conversions
that fail stricter source/visibility/page evidence, and one common-format
WordPress failure because MinerU correctly refuses a successful empty import at
the `unsupported_no_text` boundary. The improved split reflects repaired
semantic/media cases without weakening document-completeness or page-
representation contracts. The common tracked segment remains red at 51 passes,
one review, and nine failures.

### Release blockers that remain

1. The showcase still reports 10 failures and one review. Nine successful PDF
   conversions fail source-integrity, visibility, or page-representation gates;
   MinerU correctly emits no editable conversion because its eight fully
   classified no-native-text pages have browser requests but no materialized
   representations.
2. All 17 remote candidates still require the four-screenshot receipt plus a
   named, timestamped human verdict. All are now fully executed; three
   additional candidates remain excluded for license reasons rather than
   counted as passes.
3. The real dense-browser Linux PSS/release gate has not been rerun. The macOS
   host was already over the memory ceiling during Playground boot, before the
   conversion workload began, and the final capability check found no local
   Linux container/VM runtime.
4. The deterministic package is verified only in temporary output. It must not
   replace the checked artifact or be deployed until the showcase,
   manual-review, and Linux browser gates are accepted.

No current plugin ZIP was deployed, promoted, or substituted for the previously
released artifact because these release gates remain red. In particular,
`pandoc-showcase/playground/port-libs-playground-converter.zip` remained
unchanged at 8,313,372 bytes, 618 entries, and SHA-256
`e4659f62ad00e5d2bbcf1806dc9ee5fcf2721300bfc7a28dd1b14c8fc84dd6e6`; the
verified temporary package was not copied over it.

## Executive decision

The theatre page was not a special-case problem and it was correctly fixed without character names, Polish words, a theatre document type, or a lower global table threshold. In the before-state, the importer could recognize a table but did not score independent column flow as an equally serious alternative when cells were short. The current implementation compares `tableScore` with `columnFlowScore`, protects hard table evidence, and selects columns only when the column score is at least 0.68 and clears a 0.12 margin.

The safe fix is to classify each page region by competing, explainable hypotheses—tagged structure, table, independent columns, line-oriented material, ordinary prose, and figure/form—and change the incumbent result only when the winning hypothesis clears a meaningful evidence margin. Strong table evidence must continue to protect invoices, statements, borderless numeric tables, spanning cells, rotated tables, and tagged tables. Strong column-flow evidence should protect theatre dialogue, stage directions, sidebars, brochures, and other short aligned text.

This is also the most useful answer to the broader PDF-quality problem. PDF import becomes whack-a-mole when a late heuristic directly rewrites output. It becomes tractable when every decision has:

1. immutable source facts and local provenance;
2. a document-wide layout profile shared by every chunk;
3. competing per-region scores rather than one-way detectors;
4. a disposition ledger accounting for every source text and visual item;
5. a conservative fallback that preserves content as paragraphs/line blocks;
6. source-to-AST and AST-to-WordPress integrity gates;
7. reproducible corpus, metamorphic, visual, performance, and failure-injection tests.

That column-vs-table fix is a successful first vertical slice, not evidence that the whole architecture is complete. The most useful next work, in safety order, is:

1. make completeness and order proof local to exact source occurrences and regions;
2. model visible versus non-rendering, hidden-layer, clipped, off-page, and covered text;
3. make destructive prose repair preserve uncertain code/math/punctuation and block publication when required occurrences are unresolved;
4. arbitrate tags, geometry, prose, tables, and code per covered region rather than with a document-wide tag switch;
5. make every visual occurrence end in a correctly ordered asset, deliberate omission, original/placeholder, or unresolved failure;
6. replace permissive corpus thresholds with per-document structural expectations and manual-review evidence.

These changes address the largest correctness and privacy risks without weakening the table controls that made the original fix safe.

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

- **R-B — reproduced before:** reproduced at `cba982f61`.
- **R-C — reproduced current:** rerun or directly probed at `fc25ef955`.
- **O — observed:** present in current generated showcase output or visual-review artifacts.
- **T — tested:** a current automated test proves either support or a bounded failure behavior.
- **D — detectable:** diagnostics exist, although this audit did not reproduce a public failing file.
- **G — gap:** the code path or format supports the failure, but there is no adequate regression fixture yet.

The private user PDF was used only as a local validation input. It is not named, copied, linked, or added to the repository. A generic synthetic reproduction with unrelated names and text proves that the failure does not depend on that document.

### Audit runs

| Run | Result |
|---|---|
| Private one-page range, geometry tables enabled | **R-B:** 2 Table blocks, 0 Paragraph blocks, 0 low-confidence candidates, 0 line-oriented regions |
| Same page, geometry tables disabled | **R-B:** 0 Table blocks, 11 Paragraph blocks; the text was available, so decoding was not the cause |
| Generic 5-row × 3-column stage-layout PDF | **R-B:** 1 Table block, 0 Paragraph blocks, 0 low-confidence candidates |
| Current theatre/metamorphic controls | **T:** generic 2/3-column and colon variants select independent columns above the 0.12 margin; protected tables retain the incumbent path |
| Global order-evidence probe | **R-C/P0:** source `alpha / bravo / charlie` became `alpha / charlie / bravo`; allowing order change only for `alpha` still returned `orderedSignificantCharactersPreserved=true` with basis `evidenced-layout-reorder` |
| Invisible-text probe | **R-C/P0:** a searchable stream using text rendering mode `3 Tr` emitted `SECRET-INVISIBLE` as a paragraph and returned `pdfSemanticTextComplete=true` |
| Production-option prose-repair probe | **R-C/P0:** a two-page searchable code/table fixture lost its standalone `}`; the ledger correctly reported unresolved U+007D, but incomplete output was still produced |
| Fixed-window boundary control | **T:** extraction chunks of 1/2/8 pages are invariant for named fixtures under a fixed eight-page semantic partition; a test explicitly proves that composite table families do **not** link across pages 8/9 |
| Fresh focused native/parser/plugin run | **R-C:** 20 test files, 8,798 assertions, 0 failures; this combines the final 14-file/8,215-assertion bounded matrix with six additional current suites/583 assertions |
| Isolated pipeline/memory/plugin/expectation run | **R-C:** 4 test files, 1,997 assertions, 0 failures |
| Overlapping untracked-PDF/converter run | **R-C:** 20 test files, 1,458 assertions, 0 failures; `PandocConverterTest` independently passed 375 assertions, so these overlapping totals are not added |
| Generated-showcase PHP run | **R-C:** 2 test files, 2,810 assertions, 0 failures after the Korean-table and table-picture-boundary cases were removed from the stale expected-failure list and the common expected fail count was reconciled to 9 |
| Optional Haskell-timeout run | **R-C:** 1 test file, 5 assertions, 0 failures |
| Current Playground CI | **T:** 32 test files, 9,403 assertions, 0 failures; [run 29542448028](https://github.com/adamziel/port-libs/actions/runs/29542448028) passed |
| Public visual-review corpus | **R-C/T:** reviewer check passed for 14 checked-in desktop/mobile documents; this proves reproducible rendering, not manual semantic acceptance |
| Table-corpus inventory | **R-C:** 4 checked-in screenshot/baseline controls, 17 hash-pinned remote candidates still requiring deterministic execution/review, and 3 license-blocked candidates |
| Generated-showcase consistency checks | **R-C/T:** all nine preflight/JavaScript checks passed: quality signature, examples page, layout reviewer, import-job session, converter UI, Form/whole-page rasterizer, PDF.js facts provider, JPX rasterizer, and JBIG2 rasterizer. Browser text/structure facts remain opt-in and have no semantic `PdfReader` consumer |
| JPX/JPEG 2000 and JBIG2 rasterizer checks | **R-C/T:** passed, including lossless palette verification |
| Dense large-file native gate | **T:** 8,736,666 bytes, 250 pages, 5,000 positioned lines; 613,418 output bytes, 11,291 AST nodes, 54,886,400-byte allocated peak under a 128 MiB process limit |
| Real Playground page-tree import | **T:** four-page Muir brochure published as one root plus four physical-page children in about 34.7 seconds; all posts were nonempty and the scenario passed |
| Real Playground single-page import | **T:** TraceMonkey published as one 14-page WordPress page with eight expected browser-rendered charts in about 55.6 seconds; the scenario passed |
| Dense real-browser import | **T:** the 250-page/8.7 MiB fixture published as one nonempty WordPress page in 148.151 seconds with 514,488 content bytes and 305,624 visible-text bytes; peak Linux PSS was 1,323,844,608 bytes under the 1.5 GiB gate, while summed RSS peaked at 2,296,791,040 bytes |
| Audited/deployed-head production plugin artifact | **T:** two deterministic builds matched; 8,255,591 bytes, 618 entries, SHA-256 `ab2c74ad93a4e7a1e73f3c5737eaab25b2b3042a44163142eaa867248165ae8c`. This is historical deployed-head evidence, not the unpromoted working-tree package above. |
| Pages deployment | **T:** [run 29542917386](https://github.com/adamziel/port-libs/actions/runs/29542917386) deployed current head and verified the production archive and PDF.js assets |

The current public layout manifest contains 14 focused layout documents plus nine long-standing searchable-PDF examples. The separate table manifest contains 24 table-oriented candidates, but only four are checked in with screenshot/baseline evidence. Hash pinning establishes identity, not conversion quality. The broad suite protects thousands of lower-level behaviors; it does not prove that every final document has the right semantics.

### Audited-head outputs that motivated execution

| Audited-head example | Evidence | What it tells us |
|---|---|---|
| One-page header/footer fixture | **O:** both header and footer survive as headings despite an automatic “pass” | One page cannot establish recurrence; text completeness alone cannot identify furniture |
| Arabic RTL fixture | **O:** review status, 0.7349 text completeness, 0.958 native source coverage, and no output `dir="rtl"` | Most tokens can survive while order, shaping, direction, or structure remains wrong |
| Code/formula fixture | **O:** formula `a2 + 8 = 12` survives as an ordinary paragraph | Character conservation is not semantic math recovery |
| IRS W-4 | **O:** automatic pass; 994 visual lines become 31 headings, 63 paragraphs, 3 tables, and 2 images | Stacked form labels become headings (`Enter` / `Personal` / `Information`), while other form regions collapse into 1,485- and 2,749-character paragraphs |
| TraceMonkey paper | **O:** automatic pass; 1,572 lines become 27 headings, 229 paragraphs, 1 table, and 0 static images | Front matter and title continuation are omitted, 90 placement records yield no static images, a benchmark matrix becomes prose, and wrap artifacts such as `pop-ular` survive; the separate browser scenario can render eight charts |
| CDC brochure | **O:** automatic pass; 35 placements yield four images and 25 `unanchored` diagnostics | All four images cluster after the title, page-two visuals disappear, `they examine you.` becomes a heading, an ordered list restarts, and `alcoholbased` loses a boundary |
| Aircraft handbook | **O:** automatic pass; nine Form-render requests and no static image blocks | Visual discovery, browser rendering, and placement are separate stages |
| Table/picture-boundary fixture | **O:** automatic pass; zero imported charts and two unresolved visual requests in the reviewer | “No crash” does not prove clean text or media completeness |
| Grand Canyon map | **O:** automatic pass; 50 headings, 61 paragraphs, no images, 11 unanchored placements, 5 unavailable placements, and source coverage 0.745 | Map fragments such as `T` and `Ar` become headings; roughly a quarter of reference tokens and all visuals can be absent while the gate passes |
| Motograph book | **O:** automatic pass; 47 pages but only one text page, 111 placements/65 unanchored, and one image | OCR restoration is out of scope, but pages 2–47 still need a page occurrence, original, or explicit placeholder rather than silent disappearance |
| Muir brochure | **R-C/O:** review status; 122 paragraphs, seven images, seven unanchored objects, and three anchor-order conflicts | 73 paragraphs contain at most three characters, 89 at most ten, and 84 are one word; the current paragraph gate still passes severe fragmentation |
| QuickBooks invoice | **O:** automatic pass; 14 headings, 13 paragraphs, 7 physical tables, 2 images, 1 unanchored and 1 unavailable placement | Seven physical tables are intentional and grouped as one family/two instances; the residual defects are heading overclassification and image order (page 3 before page 2), not raw table count |
| Borderless spreadsheet | **O:** automatic pass and exact token overlap | Grouped headers lack spans, following header rows become shifted body rows, and `YIELD ESTIMATE` becomes a one-item ordered list |
| Headerless numeric multicolumn table | **O/T:** automatic pass and exact token overlap | The first data row becomes six `<th>` cells and later rows are padded with empty cells; a current test locks in this semantically imperfect fallback |
| Picture/caption fixture | **O:** two image blocks and a pass | A happy path exists, but it does not establish exhaustive occurrence accounting for Forms, repeated assets, or page-level graphics |

At the audited commit, automatic quality gates emphasized text overlap,
permissive block counts, nonempty output, and successful rendering. They
contained systematic false negatives:

- `media_imported` recognizes words such as `missing`, `failed`, `limit`, and `conflict`, but not `unanchored` or `placement-unavailable`; CDC, Grand Canyon, Motograph, and QuickBooks therefore pass despite explicit placement loss.
- Geometry passes when there is any nonzero text-page count, so one text page out of 47 can satisfy the gate.
- Paragraph fragmentation is reviewed only when paragraphs exceed 90% of source visual lines; Muir's 122 fragments remain below 381 and pass that gate.
- Generic real-corpus tests mainly require nonempty output, a text line, a small byte floor, and metadata keys. Heading assertions have lower bounds but no meaningful upper bounds.

These gates are useful smoke tests, not semantic ground truth or release acceptance.

### Audited-head high-risk code evidence

- `PdfSourceDispositionLedger` consumes source occurrences from one global emitted token/character multiset at `lanes/pandoc/src/PdfSourceDispositionLedger.php:80-123`; at `:170-184`, one explicit `allowOrderChange` plus equal global inventory/byte length can certify a different document-wide order. `PdfReader` uses that result in `pdfSemanticTextComplete` at `lanes/pandoc/src/PdfReader.php:612-629`.
- Native text interpretation at `lanes/markerpdf/src/PdfTextExtractor.php:18103-18383` does not model `Tr`, OCG state, ExtGState opacity/soft masks, clipping, or later opaque coverage, explaining the invisible-text probe.
- Normal Playground layout options enable `pdfRepairProseText` at `tools/playground-converter-plugin/port-libs-playground-converter.php:8913-8934`. Deletion paths include terminal-hyphen tails (`PdfReader.php:12585-12610`), isolated fragments (`:12622-12643`), brace stripping (`:16009-16014`), and punctuation-only noise (`:16034-16072`). The ledger detected the reproduced brace loss, but detection did not restore content or prevent incomplete output.
- Any nonempty tagged result suppresses positioned reconstruction/table inference and prose repair at `PdfReader.php:241-330`, and final arbitration selects tags globally at `:487-489`; untagged gaps receive only the simpler `blocksFromLines` path.

## Before-state root cause and current fix

The failing page had searchable text and ordinary positioned runs, but no tagged table, no tagged structure blocks, and no filled rectangles forming a grid. Its short dialogue and stage-direction fragments recurred at several x positions. That was enough for the before-state geometry candidate to see stable columns and multi-cell baselines.

At `cba982f61`, `PdfReader::positionedRowsLookLikeNarrativeColumnLayout()` rejected a candidate as narrative columns only when:

- at least half the rows contain multiple cells;
- 30–40% of populated cells have at least seven words or 48 characters; and
- 65–75% of populated cells are at least 80 PDF units wide.

Those conditions protected long brochure prose, but short theatre lines rarely satisfied them. The later table confidence rewarded row count, occupancy, recurring columns, width, and numeric anchors without an equivalent positive score for independent top-to-bottom flow. The oversegmented-prose fallback was also oriented toward broader/longer grids.

Current head `fc25ef955` implements the intended fix:

- `positionedTableVsColumnHypothesis()` independently calculates table and column-flow evidence in `lanes/pandoc/src/PdfReader.php:20181-20516`;
- hard fills, recurring numeric columns, and compact table headers protect genuine tables;
- independent columns win only with `columnFlowScore >= 0.68` and `columnFlowScore - tableScore >= 0.12` at `:20476-20489`;
- the bounded feature vector, selected hypothesis, reason, and margin are retained in diagnostics at `:20520-20534`;
- `pdfDialogueCueAndBody()` now accepts attached or spaced ASCII/full-width colons without leaking the delimiter at `:12343-12364`.

The current synthetic and private-local controls now emit editable line/prose blocks instead of false tables, while protected table tests pass. This is a verified fix for the geometry table-versus-independent-column decision, not a universal layout proof. The working tree now arbitrates tagged coverage by bounded region/page, preserves untagged gaps, and proves authorized permutations against exact occurrence ranges. Other semantic hypotheses and full semantic-window invariance remain incomplete.

### Decision model and non-regression contract

The implemented scorer should remain governed by this rollout rule as it expands to other regions:

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

The feature set is typographic and geometric. It does not require a theatre mode, user-selected document type, a language dictionary, or special-cased names. Current colon handling accepts optional horizontal whitespace around a cue delimiter and excludes the delimiter from the body; cue evidence should continue to contribute only inside a recurring line-oriented region. Theatre content must remain paragraphs/line blocks, never a code block.

## Complete non-OCR imperfection map

### A. Container, security, and bounded decoding

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| A1 | Encrypted content is empty or partial when no valid password is available | D/T | Standard security revisions are covered extensively; unauthenticated encryption is warned. Keep password state explicit and never publish an empty “success.” |
| A2 | Copy restrictions or unsupported security handlers prevent extraction | D/T | Permission policy is diagnosed. Preserve the distinction between technical readability and policy; active/unsupported handlers must fail clearly. |
| A3 | Malformed/stale xrefs, incremental revisions, object streams, page trees, or content references omit pages/regions | D/T/G | Current recovery and page-level issues cover many cases. A missing, unsupported, or refused `/Contents` carrier/member is now a typed page-incompleteness boundary: decoded siblings may retain source text, but they cannot make the omitted region or page geometry/visibility complete. Every other omitted object/page still needs the same typed issue and incomplete status. |
| A4 | Unsupported/corrupt content-stream filters omit or partially decode text/resources | D/T/G | ASCIIHex, ASCII85, RunLength, Flate, LZW, and supported Crypt paths are native and failures are typed. A page's ordered `/Contents` array is now tokenized as one bounded logical program, so dictionaries, operands, `BT`/`ET`, and Form names/`Do` may span contiguous decoded members. Form expansion carries `BT`/`ET` state across those members and expands `Do` only outside a text object. Aggregate bytes, separators, and tokens are charged once across the page; an undecodable/refused member creates an unknown gap that resets inherited state rather than masquerading as an empty stream. Residuals include non-8-bit PNG predictors and malformed inline-image terminators. |
| A5 | Missing/wrong MediaBox, CropBox, inherited rotation, or transforms corrupt coordinates and order | G/T | Some rotation and page-tree inheritance are covered; US Letter fallback can be geometrically wrong. Record inferred boxes and lower layout confidence. |
| A6 | Stream, token, positioned-run, byte, time, or memory caps drop a tail or force degraded output | T/G | Current extractor limit diagnostics are typed and propagated. Decoded-byte and token ceilings are aggregate page-program bounds; Form diagnostics share page-local traversal/program budgets, retain exact `Do` member attribution, and deduplicate a typed limit fact independently of whether inventory rows can still be retained. Keep every other safety skip bound to page/object/range completeness and a visible retry/degraded classification. |
| A7 | Fast text-only fallback preserves words but loses tables, images, ordering, and semantics | T | This is an intentional degraded mode. Label it, do not compare it with a full semantic success, and make retry/resume choices visible. |
| A8 | MIME/extension disagrees with bytes, or a resumable source is changed/truncated after facts were stored | T/G | Sniff and hash original bytes; bind every facts/media/result checkpoint to the same immutable content hash and length, and reject stale resumptions rather than mixing versions. |
| A9 | Extreme page dimensions, `UserUnit`, non-finite transforms, deep/recursive resources, or cyclic references produce wrong geometry or resource exhaustion | T/G | Apply finite/range/depth/visited-object bounds before arithmetic or recursion, record the exact rejected page/object, and preserve a degraded text/original fallback. |
| A10 | Optional PHP extensions or runtime differences change normalization, bidi, case, width, image, or memory behavior | T/G | Define deterministic behavior with and without `Normalizer`, `mbstring`, image decoders, and platform-specific helpers; run an environment matrix and never let a missing extension silently change semantic status. |

Resource-limit diagnostics live in `lanes/markerpdf/src/PdfTextExtractor.php`.
Current hard bounds include decoded stream, logical tokenized page program,
token-count, positioned-run, and Form traversal/program limits.
`boundedDecodedPageContent()` retains exact source-member byte ranges while the
page remains inside aggregate bounds. Page summaries and page text aggregate
once per physical page; an unknown member gap starts a new logical segment,
clears carried text/graphics/Form operand state, and prevents later positioned,
geometry, clip, alpha, visibility, or paint-order facts from being certified.
`PdfReader` separately reports `pdfTextComplete`, `pdfGeometryComplete`,
`pdfRangeComplete`, `pdfDocumentComplete`, and `pdfLimitReasons`.

### B. Characters, words, and text-line reconstruction

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| B1 | Missing/partial ToUnicode CMaps, subset fonts, Type3/custom glyphs, symbol encodings, or missing widths suppress or misdecode characters | D/T/G | Broad CMap/font tests exist and suppressed/partial glyph runs are diagnosed. Never invent letters; reconcile with browser facts only when exact local evidence is stronger. |
| B2 | Empty/malformed `ActualText`, artifacts, invisible overlays, or duplicate text layers remove visible glyphs or duplicate body text | D/T/O | ActualText and artifact tests are strong. Add occurrence-level disposition so hidden replacement, artifact suppression, and duplicate suppression remain auditable. |
| B3 | Kerning arrays, fragmented runs, font switches, transforms, or absent widths produce `T h`, `Y our`, glued words, or missing leading fragments | R-C/T | Existing repair has many character-conservation controls. Keep source text as character authority; geometry may alter only proven boundaries/order. |
| B4 | Hard hyphens, soft hyphens, discretionary wraps, minus signs, URLs, compounds, or letter/digit boundaries are joined or split incorrectly | R-C/O/T/G | Current outputs retain `pop-ular` and produce `alcoholbased`; production prose repair can also change `Model2024` to `Model 2024`. Require the same-page/local wrapped pair or explicit font/geometry boundary before changing text. |
| B5 | Ligatures, combining marks, normalization, diacritics, C0/C1 controls, or replacement characters leak or disappear | T/O/G | Add exact significant-character and forbidden-control gates to public corpus results, not only normalized token overlap. |
| B6 | RTL, mixed bidi text/numbers, vertical CJK, and rotated writing are reversed, flattened, or lack `lang`/`dir` | O/T/G | Arabic is a current review case. Preserve logical source order when reliable, use geometry only with sustained directional proof, and propagate language/direction semantics. |
| B7 | Source-stream order and visual order disagree, or an unrelated permutation is falsely authorized | R-C/T/O | The audited head used a document-wide character/token inventory. The working tree replaces that path with exact source-to-output edges plus page/region-bounded permutation proofs; unrelated same-inventory permutations fail focused tests. Corpus items with unresolved exact occurrences now fail closed instead of borrowing a global allowance. |
| B8 | A repair candidate has similar words but changes a character | T | Current exact-character tests reject this. Keep this invariant globally: geometry cannot authorize character substitution. |
| B9 | Invisible, clipped, off-page, optional, or visually covered text is imported—or visible clipped text is omitted | R-C/T/G | The working tree models rendering mode, crop/page and clip bounds, opacity, OCG default state, and later opaque coverage as separate occurrence-local visibility evidence. Certainly non-rendering text is excluded, accessible replacement text remains separately disposed, and uncertain soft masks/complex clips fail closed. After an unknown `/Contents` gap, independently reopened source text may be conserved as source-only evidence, but it cannot be positioned or certified visible. |

PDF.js exposes each text item’s string, direction, transform, dimensions, font name, and `hasEOL`, while structure is a separate source; it also normalizes whitespace to ordinary spaces. See the official [`getTextContent` API](https://mozilla.github.io/pdf.js/api/draft/module-pdfjsLib-PDFPageProxy.html). Browser facts therefore make useful competing evidence, not an unquestionable replacement for native extraction.

### C. Reading order and geometric regions

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| C1 | Independent columns become a table | R-B/T | Fixed for the reported and synthetic controls by the current competing scorer. Keep the 0.12 margin, hard-table veto, exact-character checks, and protected-table AST controls as mandatory non-regression gates. |
| C2 | A real sparse, borderless, prose-heavy, or irregular table remains prose | T/G | Preserve low-confidence text fallback, but expose the candidate score so later evidence (tags, browser structure, repeated schema) can promote it safely. |
| C3 | One logical table is split, unrelated tables merge, headers/spans are lost, or a multipage table loses continuation | O/T/G | QuickBooks intentionally retains seven editable physical parts as one family/two instances, but Tabula controls still show wrong header cells, missing spans, shifted cells, and padding. Composite-family inference deliberately stops at the eight-page window boundary. Test exact cells/spans/order and relations across semantic windows. |
| C4 | Two/three-plus columns, sidebars, callouts, full-width interstitial blocks, and asymmetric bands interleave | T/G | Current column ordering handles common cases with fixed gap/width heuristics. Replace page-wide assumptions with a region graph and ordered transitions. |
| C5 | Floats, overlapping boxes, captions, footnotes, or marginalia move away from their anchors | O/T/G | Use page/region provenance and constraint edges rather than nearest text alone. Footnote body/marker linking needs its own relation. |
| C6 | Headers, footers, page numbers, watermarks, odd/even titles, or rotated furniture remain—or genuine repeated headings disappear | R-B/O/T/G | Build a document profile across pages using position, style, recurrence, and variation. Single-page ambiguity must remain content unless explicitly classified. |
| C7 | Rotated pages/regions, inherited rotation, diagonal labels, or vertical furniture enter the wrong flow | T/G | Current rotated regression passes. Extend to mixed orientations, forms, annotations, and chunk boundaries. |
| C8 | Chunking changes layout because global evidence disappears at an arbitrary page boundary | T/G | Extraction chunk sizes 1/2/8 are invariant for named fixtures under one immutable profile, but semantics are still partitioned into fixed eight-page windows. Add bounded overlap/source-ID reconciliation and vary semantic-window size (2/8/16/full), especially across pages 8/9. |
| C9 | Tagged logical order conflicts with geometry or covers only part of the page | T/G | The audited path selected tags globally. The working tree now scores and maps tag coverage per bounded region/page, preserves uncovered untagged gaps, and arbitrates their strongest local evidence independently. Malformed, duplicated, or ambiguous tag mappings remain conservative rather than authorizing a document-wide replacement. |
| C10 | Physical page boundaries, printed page labels, or internal destinations are lost or point incorrectly after single-page/page-tree reflow | T/G | Give every physical page a stable source ID/anchor independent of publication topology, retain page-label metadata, and rewrite internal destinations only after final post IDs/anchors exist. |

### D. Semantic block inference

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| D1 | Visual lines become too many paragraphs, or separate dialogue/paragraph lines collapse | R-C/O/T | Muir currently has 122 paragraphs, including 73 of at most three characters and 84 one-word fragments, while its paragraph gate passes. Make joining a region-local decision with lexical/geometry expectations and a structural fingerprint. |
| D2 | Plays, transcripts, poetry, lyrics, or aligned prose become code; code/punctuation is changed or deleted as prose | R-C/T/G | Current theatre controls stay editable prose, but the production-option probe deletes a standalone `}`. Uncertain code/math/punctuation must stay verbatim; code requires sustained monospace plus syntax/indent evidence, and every deletion needs a local disposition/replacement. |
| D3 | Body text/page numbers/form/map labels become headings, or headings are inlined/misleveled | O/T/G | Grand Canyon emits 50 headings and IRS W-4 emits 31, including one-character map fragments and stacked form labels. Use document font hierarchy plus region role; add upper bounds, lexical guards, and exact expected/forbidden headings. |
| D4 | Lists are missed, split, restarted, nested incorrectly, or ordinary numbered prose becomes a list | O/T/G | CDC separates item 1 from items 2–5 and restarts numbering; the borderless spreadsheet turns `YIELD ESTIMATE` into a one-item ordered list. Preserve explicit markers, start values, alignment, and continuation identity across regions/chunks. |
| D5 | Formula characters survive but equation structure, superscripts, fractions, or symbols do not | O/T/G | Character-conserving prose is a safe fallback. Promote to semantic math only with operator/baseline evidence and exact character conservation. |
| D6 | Captions, footnotes, citations, bibliography entries, and inline markers lose their relation | T/G | Represent relations in the intermediate model before emitting blocks; nearest-text matching is insufficient. |
| D7 | Valid tags are ignored in page slices; malformed tags are trusted; duplicate text makes tag mapping fail | T/G | Map tags per source item/page with plausibility and uniqueness checks, and carry tag facts into resumable chunks. |
| D8 | Bold/italic, alignment, indentation, small caps, language, direction, color, or decoration is lost or misapplied | O/T/G | Preserve semantic styles only where font/run evidence is local and stable; output `lang`/`dir`; do not chase pixel-perfect styling. |
| D9 | Links lose text/targets; duplicate labels bind wrongly; annotations, outlines, attachments, comments, or destinations disappear from body semantics | T/G | Link/annotation diagnostics are strong. Match with quads and provenance; retain unsupported interactive items as inert metadata or explicit attachments, never execute actions. |
| D10 | AcroForm/XFA field values, widget appearances, signatures, portfolios, 3D/video/audio, or other interactive content is missing or disagrees with the visible page | G | Prefer the static visible appearance as import content, preserve safe field values/embedded files as inert metadata or attachments, report unsupported active content, and never claim editable-form/signature preservation. |
| D11 | Unsafe link schemes, attachment names/paths, metadata, or emitted block markup become executable or escape their intended location | T/G | Allowlist inert schemes/types, canonicalize and uniquify names under a scoped directory, escape block attributes/HTML, sanitize again after WordPress storage, and report dropped active content without executing it. |

### E. Images, charts, and other graphics

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| E1 | JPX/JPEG 2000, JBIG2, CCITT, DCT, indexed color, or another image cannot be decoded on the server | R-C/T/D | Browser raster checks pass for JPX/JBIG2. If a decoder is absent, preserve the original stream as a download and emit a visible placeholder; do not fail the document. |
| E2 | Inline images, masks, transparency, clipping, colorspaces, predictors, `/Decode`, or palette handling produce missing/altered images | T/G | The fallback media decoder infers grayscale/RGB/RGBA from byte length and does not fully honor Indexed/ICC/CMYK/Lab, predictors, `/Decode`, soft masks, or masks. Use the xref-selected object graph and full color semantics, or accept a browser raster only after occurrence/dimension/color verification. |
| E3 | A chart is a Form XObject or page-level vector paths/shadings/patterns rather than an image | O/T/G | Browser PDF.js can render the region without OCR. Page-level drawing regions still need discovery; MarkerPDF alone does not reconstruct them as SVG. |
| E4 | Figures are appended, interleaved across pages, or attached to the wrong repeated caption/column | R-C/O/T/G | The audited path required globally unique text in limited top-level block types. The working tree anchors by stable page/bbox/region/occurrence provenance and uses deterministic local visual order when no text anchor is safe. CDC's recovered tiles are bound to exact source edges and a same-page region boundary; remaining unresolved/order cases still fail or review rather than borrowing a global match. |
| E5 | Full-page Form wrappers are discarded even when genuine; object/source-size/global-count caps drop later figures | T/G | The working-tree inventory uses the extractor's xref-selected occurrence graph, bounded page/Form traversal, page requests, and typed omissions. Unique traversal issue IDs are retained independently of the occurrence-row ceiling: a regression with 8,191 image paints plus an over-tokenized Form reports exactly the terminal image omission and one shared Form-traversal omission, not a duplicate third issue. Discovering and semantically accepting every page-level vector region remains open; uncertain visuals still need originals/thumbnails/placeholders. |
| E6 | A missing upload/decoder still leaves a broken `<img>` that passes publication verification | T/G | Every visual occurrence must end as a valid attachment, verified source, downloadable original, intentional omission, or visible unresolved placeholder. |
| E7 | Optional-content layers hide/show alternative text or graphics, producing duplicates or the wrong version | G | Record layer membership and use the default visible configuration; do not merge mutually exclusive layers. Adobe notes that optional-content groups can control any graphical content in its [logical structure and layers overview](https://opensource.adobe.com/dc-acrobat-sdk-docs/library/overview/Overview_Metadata.html). |
| E8 | Transcoding needlessly worsens resolution, compression, color, orientation, or metadata | T/G | Preserve a web-compatible original when possible; otherwise choose lossless or perceptually appropriate output per occurrence and verify dimensions, alpha/palette/color behavior, and visual/hash criteria before replacement. |

### F. Browser, resumability, WordPress, and deployment

| ID | Imperfect outcome | Evidence | Current behavior and safe direction |
|---|---|---|---|
| F1 | PDF.js facts add a second parse but do not improve `PdfReader` output | D/T | Eager collection is now disabled and facts are opt-in, which removes the default waste. The provider stores browser spans/structure, but `PdfReader` still rehydrates only native facts. Keep them diagnostic until a local comparator consumes them; then request bounded ranges. |
| F2 | Browser figure rendering or whole-source copies cause time/memory spikes and lost work | D/T/G | Raw byte arrays are used and the current 1.5 GiB peak-PSS gate passes, but 148.151-second elapsed time has no enforced ceiling and peak PSS does not prove completed canvases/render tasks are released. Stream/ack each figure, assert post-completion release, and use a scoped binary/range endpoint. PDF.js recommends raw byte arrays and rendering only needed pages in its [FAQ](https://github.com/mozilla/pdf.js/wiki/frequently-asked-questions). |
| F3 | A lost `/advance` response, tab reload, network error, or Playground reset makes durable work look lost | D/T/G | Shared browser/admin sessions, persisted job IDs, status recovery, and resume behavior now have strong automated tests. Add real lost-response/reload/process-termination and concurrent-advance scenarios before calling recovery exhaustive. |
| F4 | One growing WordPress option duplicates page/segment/results and fails to save late | D/T/G | Compact state and separately persisted facts/results are implemented and large-state tests pass. Keep readback verification/status paging and add database/disk exhaustion plus retention/concurrency proof. |
| F5 | The first large/pathological chunk times out or exhausts memory before metrics exist, so retries repeat the same failure | D/T/G | Bounded ranges, checkpoints, and memory gates exist. Complete adaptive halving/process-kill tests, isolate a pathological page deterministically, and expose an explicit degraded fallback. |
| F6 | Segment boundaries split paragraphs, lists, tables, captions, furniture profiles, or columns, making output host-limit-dependent | D/T/G | Preflight and extraction-partition invariance are implemented for named fixtures, but disjoint eight-page semantic windows remain. Process bounded overlap, deduplicate by source ID, and compare several semantic-window sizes with full-document output. |
| F7 | Retryable publication failure becomes terminal after some children are public; a root/index remains stale | D/T/G | Publication cursors, retryable state, and idempotent post/media work are implemented/tested. Add real termination at child/root/index boundaries and make final tree visibility atomic or explicitly recoverable. |
| F8 | Completed/failed jobs and source/render files never expire, eventually exhausting disk | D/G | Add retention, storage reporting, cron cleanup, active-lock protection, and a resumable retention window. |
| F9 | Gutenberg sanitization, block-version drift, or `wp_insert_post()` failure changes or empties content | T/G | Parse/validate before publish, return `WP_Error`, and verify structure after storage. WordPress notes that static block markup mismatch can invalidate content in its [block deprecation guide](https://developer.wordpress.org/news/2023/03/block-deprecation-a-tutorial/); [`wp_insert_post()`](https://developer.wordpress.org/reference/functions/wp_insert_post/) can fail and must be checked. |
| F10 | Fidelity/fingerprint gates pass a wrong reorder, collapsed structure, changed table/link, or missing duplicate image occurrence | R-C/T/G | Exact source edges, local permutation proofs, structural readback, stable visual occurrence IDs, and unresolved-publication rejection are implemented in the working tree. Remaining showcase examples fail their source-integrity or page-representation gates rather than receiving a false pass. |
| F11 | A single-page import exceeds practical post/database/request limits late; page-tree mode creates a partial hierarchy | T/G | Estimate block bytes before publication, preserve the chosen topology, and switch only through an explicit durable state transition. |
| F12 | CI tests a newly built plugin ZIP while Pages deploys a stale checked-in ZIP | T | The historical release path has deterministic archive identity and deployed-hash verification. This working-tree execution built and compared two temporary packages only; it deliberately left the distinct checked ZIP unchanged and made no promotion or deployment. Keep build-once/content-manifest equality as a mandatory future deployment gate. |
| F13 | PDF.js API/worker/CMap versions or asset paths disagree, so browser facts/renders fail only on the deployed host | T/G | The current bundle/deployed smoke checks pass. Continue to pin and verify one manifest through the production URL, and preserve native fallback plus explicit browser-unavailable diagnostics for future drift. |
| F14 | Automatic quality thresholds report `pass` despite obvious semantic/visual defects | R-C/O | The audited showcase had those false passes. The regenerated working-tree showcase is fail closed at 98 pass / 1 review / 10 fail; its common tracked segment remains red at 51 pass / 1 review / 9 fail. Known incomplete PDF examples no longer pass, per-document expectations remain locked, and the sole review is the safely represented OCR-overlay boundary case. Manual verdicts are still missing. |
| F15 | Simultaneous workers, double-clicked advances, cancellation, cleanup, or lock expiry duplicate or delete live work | T/G | Use owner/job-scoped leases and idempotency keys, compare-and-swap cursors, cancellation tombstones, and cleanup that excludes active leases. Test concurrent `/advance`, stale locks, cancel/resume, and cleanup races with posts/media/state readback. |

WordPress’s [`parse_blocks()`](https://developer.wordpress.org/reference/functions/parse_blocks/) parses the whole supplied document; large atomic content therefore adds memory pressure at the publication boundary. The system should keep conversion checkpoints and structural verification bounded even when the final post is necessarily atomic.

### G. Explicit OCR boundary

Image-only/scanned pages are detectable and tested, but classification is evidence-dependent. A completely decoded zero-text page produces `unsupported_no_text` with `needsOcr=true` and must have a safe page representation. A zero-line page with unresolved content references remains `incomplete`, sets `needsOcr=false`, and produces no editable import. Likewise, one undecodable/refused member of a page's logical `/Contents` program keeps that page incomplete even when contiguous decoded siblings retain source text; the unknown gap cannot be interpreted as empty or certify the sibling text's visibility/geometry. MinerU now exercises the fully classified branch instead: all eight pages are `unsupported_no_text`, eight exact whole-page browser requests are emitted, no static page representation is claimed, and both editable conversions remain failed. A hybrid searchable/scanned fixture also proves that visible text on one page cannot hide an unrepresented sibling. This report does not propose OCR.

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

Give every native line, positioned run, tag item, annotation, image occurrence, Form, and browser fact a stable source ID: document hash, page, object/MCID when available, occurrence, bbox, orientation, extraction method, and visibility evidence. Preserve source characters separately from inferred boundaries/order.

### 2. Bounded document preflight

Before semantic chunks, compute a compact profile: page inventory/boxes, font-size hierarchy, recurring edge furniture, column bands/gutters, direction/writing modes, tagged-role inventory, repeated cue patterns, table schema/header candidates, and visual occurrence inventory. Persist it once and feed the same profile to every chunk.

### 3. Region graph and hypothesis ledger

Segment pages at strong transitions: full-width blocks, gutters, rules, large whitespace, orientation changes, and figure boundaries. Score competing hypotheses inside each region. Record features, selected hypothesis, runner-up, margin, and fallback reason. Initially run new scoring in shadow mode over the corpus, then enable only high-margin decisions.

### 4. Disposition and fidelity ledger

`PdfTextFidelityLedger` supplies aggregate checks and the working-tree
`PdfSourceDispositionLedger` now supplies exact source-to-output edges with
emitted node/inline IDs and page/region-bounded order proofs rather than letting
one global token/character multiset authorize a permutation. Retain these
dispositions:

- emitted unchanged;
- emitted with a proven boundary/order change;
- represented by semantic structure;
- replaced by `ActualText` or a rendered visual;
- suppressed as artifact, duplicate, or running furniture with evidence;
- retained as an original downloadable asset/placeholder;
- unresolved.

Completeness means every source occurrence has a disposition **and** an auditable destination/suppression edge. An allowed reorder must name the exact occurrence set and expected permutation; one locally allowed move cannot authorize a document-wide shuffle. Quality success additionally means no unresolved required occurrence, ordered significant-character conservation, visibility policy compliance, and structural expectations met. Diagnostics stay in import metadata/UI; they are never prepended to page content.

### 5. Conservative semantic pass

Run dialogue, paragraph, heading, list, formula, caption/footnote, and code inference after region selection. Destructive operations require local provenance. Uncertain content remains paragraphs/line blocks; uncertain visuals remain placeholders/original attachments. No semantic detector may delete source content before its replacement exists.

### 6. Chunk-invariant processing

Use the immutable profile and a small bounded overlap around semantic chunks. Reconcile overlap using source IDs, not fuzzy text. The target is the same normalized AST across extraction partitions, semantic-window sizes, PHP time limits, and single-page/page-tree publication modes. Current evidence proves only extraction-partition invariance for named fixtures under a fixed eight-page semantic partition.

### 7. Durable media and publication state machines

Persist each figure request/result individually by stable request/content hash. Persist compact job cursors separately from page facts and results. Treat every mutating request as idempotent, recover uncertain responses by reading status, and distinguish retryable from terminal failure. Verify both source-to-AST conservation and ordered Gutenberg structure before marking the job complete.

## Actionable implementation plan

### Phase 0 — Evidence and shadow instrumentation — substantially complete

The synthetic theatre replica, score/feature/margin diagnostics, protected-table snapshots, typed extractor limits, durable facts, corpus identity metadata, and visual reviewer now exist. Remaining evidence work is to split every status into `implemented`, `tested at SHA`, `corpus exercised`, `manual review`, `released`, and `known limitations`; hash-pinned candidates must not count as executed tests.

Exit gate: every claim is bound to a commit/run/artifact and no automatic pass is described as manual semantic acceptance.

### Phase 1 — Table versus independent columns — complete for the reported slice

The current scorer, independent-flow ordering, generic colon parsing, 0.12 rollout margin, hard-table veto, line-oriented output, and protected controls solve the reported/private-local and synthetic variants. Keep geometry-table-off only as an explicit degraded retry.

Exit gate for future scorer changes: all column variants pass; protected table ASTs/counts/cell text/spans/order remain unchanged; exact significant characters remain accounted for.

### Phase 2 — Correct false completeness and destructive semantics — core implemented, corpus hardening remains

Outcome: the importer cannot certify or publish a wrong reorder, invisible secret, or known content deletion as a complete semantic import.

1. Commit the three current P0 probes: unrelated reorder, `3 Tr` invisible text, and standalone-brace deletion through `plpc_converter_options()`.
2. Give emitted nodes/inlines source-derived IDs; replace global bag reconciliation with exact source-to-output edges and page/region-bounded permutation proofs.
3. Implement a visibility policy for rendering mode, page/crop/clip bounds, ExtGState opacity/soft masks, OCG default state, and opaque coverage; keep accessibility replacement text in a separate disposition.
4. Preserve uncertain punctuation/code/math verbatim. Require a local evidence edge for every deletion/substitution and prevent publication success while a required source occurrence is unresolved.
5. Reconcile tagged coverage, geometry, tables, code, and prose per source region instead of globally selecting tags whenever any tag block exists.
6. Add region-local heading/paragraph/list/formula/caption/footnote expectations, propagate `lang`/`dir`, and compare semantic windows 2/8/16/full with source-ID overlap reconciliation.
7. Treat a page's `/Contents` array as one bounded lexical program. Carry state only across contiguous decoded members; at every unknown member gap, reset text/graphics/Form state and retain any independently reopened suffix text as source-only evidence without geometry, visibility, or later-paint certification.

Exit gate: arbitrary same-inventory permutations fail; non-rendering text follows the declared visibility policy; all required occurrences have exact destinations/dispositions; pass cases have expected/forbidden structure and direction; no production-option fixture loses a character.

### Phase 3 — Complete visual occurrence handling — accounting implemented, visual acceptance remains

Outcome: each source visual is imported, represented, or explicitly unresolved in the correct page position.

1. Build the inventory from the extractor's xref-selected object graph, not raw `obj...stream` regex scanning; include raster images, inline images, Forms, and discoverable page-level vector regions.
2. Assign a stable ID to every painted occurrence even when the same asset repeats; page browser-render requests and submit/acknowledge one result at a time.
3. Anchor by page/bbox/region provenance; support tables, lists, code, repeated captions, and missing captions; fall back to deterministic visual y/paint order.
4. Honor ColorSpace, predictors, `/Decode`, masks, alpha, clipping, and incremental revisions, or use a verified browser raster. Preserve decoder-missing originals and visible placeholders.
5. Make `unanchored`, `placement-unavailable`, order conflicts, missing page occurrences, caps, and swallowed placement exceptions fail or review the occurrence gate.
6. Share bounded traversal/program budgets across each page's Form graph, preserve exact `Do`-member attribution, and deduplicate typed traversal omissions independently of whether the inventory row limit has already been reached.

Exit gate: media disposition is 100%; there are no broken `<img>` elements; ordered occurrence counts, captions, and placeholders match expectations.

### Phase 4 — Finish large-import durability and performance proof — native green, Linux browser pending

Outcome: an 8–10 MB/250-page searchable PDF cannot consume unbounded memory or turn a late response/database failure into lost work.

1. Keep browser facts opt-in until a reader comparator exists; when it does, request incremental bounded ranges.
2. Use file-backed source/index data and owner-scoped binary/range access.
3. Normalize job storage and verify each state commit.
4. Persist/recover the active job in both wp-admin and GitHub Pages clients.
5. Halve interrupted ranges, isolate pathological pages, and checkpoint subphases.
6. Make publication retryable/atomic from the user’s perspective and add job/file retention cleanup.
7. Stream WordPress media metadata work as its own resumable phase.
8. Fail unexpected console/network observations, add generous elapsed ceilings/trend alerts, and assert that canvases/render tasks and memory are released after completion.

Exit gate: failure injection after any mutation resumes without duplication; state remains bounded; progress survives reload; no completed work is hidden by a lost response; peak and post-completion resource gates pass.

### Phase 5 — Turn corpus identity into semantic release evidence — execution green, manual verdicts pending

Outcome: changes cannot ship on unit assertions alone.

1. Deterministically fetch and checksum the 17 licensable remote table candidates; keep the three license-blocked items visibly excluded and never count them as passing.
2. Add the focused public fixtures below, especially visibility, same-inventory wrong order, partial tags, repeated visual occurrences, headerless/multirow tables, form/map headings, list continuation, and front matter.
3. Give every document exact expected/forbidden headings, paragraphs, list starts, table headers/spans/cells/order, links, page coverage, media occurrences, and unresolved dispositions.
4. Run those DOM/AST gates plus recorded side-by-side screenshots and an explicit manual verdict on desktop/mobile.
5. Preserve the now-verified build-once/archive-hash/Pages deployment chain for every release and publish per-document unresolved criteria in the reviewer, never in imported post bodies.

Exit gate: corpus matrix is green, visual review is recorded, production artifact identity is verified, and Pages demonstrates the tested build.

## Required red-green tests

### Correctness, privacy, and gate-truth tests

1. Authorize a column reorder on page 1, then swap ordinary paragraphs on page 2 with identical character inventory: semantic completeness must fail.
2. Exercise `0 Tr`, `3 Tr`, alpha-zero text, clipped/off-CropBox text, a default-hidden OCG, accessible replacement text, and text under an opaque cover. Assert the declared visible/accessibility policy and never expose an apparent redaction silently.
3. Run a short code/math/punctuation fixture through the normal Playground options beside a real geometry table. Standalone `{`, `}`, operators, terminal hyphens, and letter/digit compounds must remain exact unless an explicit local replacement disposition exists.
4. Combine a valid tagged H1 with an untagged column body, table, list, and code block. Each uncovered region must use its strongest local evidence rather than the document-wide tag path.
5. Make showcase gates fail/review on `unanchored`, `placement-unavailable`, a missing physical page occurrence, excessive one-character paragraphs/headings, and known unresolved required source items.

Assertions: exact source-to-output edges; only the named local permutation is allowed; no unresolved required item can produce a pass; visibility, page coverage, and automatic/manual verdicts are explicit.

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
- QuickBooks seven editable physical table parts grouped as one family/two instances;
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
- vary extraction chunks 1/2/8 independently from semantic windows 2/8/16/full; compare by normalized AST and source-ID edges, including a relation crossing pages 8/9;
- kill PHP before/after extract, convert, media, post insert, post update, child publication, root/index publication, and state save;
- lose the response after a successful mutation; reload/close the tab; retry a 409 lock;
- fail option/database save, disk write, media metadata, and post sanitization;
- run 250-, 1,000-, and 2,000-page synthetic job-state scenarios without storing duplicated result trees in one option;
- verify the checked/deployed ZIP content manifest;
- repeat an image asset on several pages with repeated/no captions and deliberately reversed anchors; require one ordered result/disposition per painted occurrence.

Assertions: idempotence, no duplicate posts/media, preserved cursor, resumable UI, bounded status payload, deterministic final topology, structural fingerprint equality, and explicit unresolved state rather than false success.

### Resource and responsiveness gates

- Unit/native suite remains below 128 MiB RSS; the current dense native fixture reports a 54,886,400-byte allocated peak under its 128 MiB process limit.
- A representative 8–10 MB/250-page import stays below 384 MiB PHP peak under a 512 MiB limit, leaving safety headroom.
- Browser import stays below 1.5 GiB proportional resident memory on Linux (PSS, with summed RSS retained as diagnostics and as the conservative fallback elsewhere); the current dense run peaked at 1,323,844,608-byte PSS. Add a post-completion drop/canvas-count assertion before claiming completed renders are released.
- Dense 250-page elapsed time has a generous enforced ceiling or trend alert; the current measured 148.151 seconds must replace the stale 80-second observation.
- No request deliberately consumes more than 80% of its PHP execution window; it checkpoints and yields first.
- Job option/state remains below 64 KiB regardless of page count; page facts/results are separately paged.
- UI reports a durable stage/page/occurrence update at least once per successful request and can recover progress in one status request after reload/lost response.

These are completion targets, not claims about the current build. If a public fixture demonstrates that a numeric ceiling is unrealistic, adjust it once with recorded evidence before implementation—not after a regression.

## Success criteria and completion matrix

| Workstream | Required outcome | Non-regression proof | Status now |
|---|---|---|---|
| False table | Private local case and generic 2/3-column variants emit ordered paragraphs/line blocks, no table/code | Protected true-table matrix unchanged | **Verified targeted fix:** current scorer uses the 0.12 margin/hard-table veto and named theatre/metamorphic controls pass |
| Cue delimiter | Attached/spaced colons parsed generically without punctuation leakage | Ordinary headings, labels, tables, and prose are not dialogue | **Verified targeted fix:** both delimiter forms pass; parser is dictionary-free |
| Region inference | Every ambiguous region records hypotheses, features, margin, and fallback | Shadow/selected output and source coverage | **Partial, with the named arbitration defect fixed:** tagged facts are arbitrated per bounded region/page and untagged gaps are preserved; deterministic table/column features pass. Other semantic hypotheses and full semantic-window invariance remain incomplete. |
| Text integrity/order | 100% source occurrence destination/disposition; no unresolved required items or unauthorized permutation in pass cases | Exact characters, source-to-output edges, exact local permutation, negative same-inventory tests | **Core mechanism and targeted corpus controls green; broader showcase fail closed:** exact edges and page/region-bounded proofs reject unrelated same-inventory permutations. CDC binds all 160 and Muir all 480 occurrences with 0 unresolved items and complete edge maps; Korean resolves 67/67 source occurrences and all 2,557 significant bytes. Trace and RTL still report exact unresolved occurrence counts and cannot qualify as complete documents. Grand Canyon is now extraction/source clean but remains blocked by visibility evidence. |
| Visibility/privacy | Only content allowed by an explicit visible/accessibility policy is imported | `Tr`, opacity, clip, OCG, page-box, and occlusion fixtures | **Implemented and automated-test green, broader review partial:** rendering modes, alpha, OCG state, page boxes, occurrence-local partial-`TJ` clipping, and occlusion diagnostics are covered. An unknown `/Contents` gap resets carried state and prevents suffix geometry, clipping, alpha, visibility, or later-paint facts from being certified; independently reopened text is source-only. Unresolved soft masks/complex clips remain uncertified and adversarial/manual coverage is not complete. |
| Global layout | Same normalized AST across extraction partitions and semantic-window sizes | 1/2/8 extraction × 2/8/16/full semantic matrix with cross-boundary relations | **Partial:** named 1/2/8 extraction partitions agree under a fixed eight-page semantic window; composite families deliberately do not link across pages 8/9 |
| Semantics | Expected/forbidden counts and relations for headings, paragraphs, lines, lists, code, math, captions, notes | Per-document public/synthetic expectations and manual verdicts | **Targeted mechanisms and all four recorded corpus baselines green; broader acceptance pending:** formula/brace preservation, CDC clipped-display reconciliation, Korean source conservation, and bounded Grand Canyon extraction pass exact controls. Grand Canyon now requires the exact 13-heading sequence—`North Rim Day Hikes` immediately before the 12 trail headings—and the generic crossed-panel promotion requires occurrence-exact proof for every row; a 16/16 synthetic promotes while 15/16 does not. RTL remains a real semantic-order defect with 58/58 unresolved source occurrences, and Trace remains fail closed with 1,801/1,803 unresolved occurrences plus visibility risks. Grand Canyon is extraction/source clean but still fails visibility evidence. Seventeen remote candidates also lack human verdicts. |
| Tables | Logical regions, cells, headers/spans/continuations preserved | Executed 24-candidate corpus plus exact synthetic/tagged controls | **Executed, automated baselines green, manual acceptance pending:** all 21 obtainable pins verify and all 42 modes execute under the watchdog; all 4 semantic baselines pass. All 17 remote candidates still await verdicts, and 3 license-blocked candidates remain excluded. |
| Media | Every visual occurrence imported, intentionally omitted, original+placeholder, or unresolved in order | Ordered occurrence manifest and decoder/browser/upload failure injection | **Accounting and CDC regression green; broader visual acceptance pending:** stable occurrence IDs, dispositions, decoder/upload failures, and per-page representation are tested. CDC accounts for 35 occurrences as 7 resolved and 28 intentional omissions with 0 unresolved. Its clipped-artifact bridge is bound to the exact PDF/source edge/node graph, rejects tampering, and places four recovered tiles at a local page-region boundary rather than beside the remote text counterpart. The 8,191-image-plus-over-tokenized-Form regression reports exactly two unique terminal omissions, not a row-retention-dependent duplicate third issue. Omission is not visual acceptance: four successful conversions still need page representations (RTL page 1, VDL page 1, all 14 Trace pages, and Motograph pages 2–47), while MinerU has eight exact browser requests and 0/8 represented pages. Motograph is semantic-text complete, no longer carries a false later-paint risk, and its anchored JPX wordmark is independently decoded to AVIF; `needsOcr=true` and the exact page-representation requirement for pages 2–47 remain. Raw page substitution remains restricted to exact, safely decoded, non-composited occurrences. |
| WordPress integrity | Ordered stored Gutenberg structure matches the provenance-bearing AST | Types/boundaries/cells/links/media/captions/attachment IDs plus source edges | **Core automated controls green; release acceptance red:** structural readback, source-edge enforcement, and incomplete-publication rejection pass. CDC now passes its broader document gate with exact later-paint reconciliation. MinerU produces no editable import, and absent page representations prevent success; other red PDFs retain explicit source, visibility, or representation failures. |
| Resumption | Any uncertain mutation can be recovered without duplicate/lost work | Kill, lost response, reload, lock, DB/disk, concurrency matrix | **Automated gates green, environment proof pending:** durable media metadata/readback, cancellation rollback, persisted client cancel intent, stale locks, concurrency, and cleanup paths pass; the final real-browser release environment still must be exercised |
| Resources | Fixed ceilings above; no unbounded source/facts/canvas/job duplication | Dense import, large state, elapsed trend, and post-completion release tests | **Native gates green; browser release gate pending:** Trace peaks at 41,943,040 allocated bytes under its 44 MiB ceiling with deterministic 102,322-byte output, the 250-page fixture passes, and IRS 15-T peaks at 492,699,648 allocated bytes while finishing in 142.876 seconds below its 180-second ceiling. macOS Playground boot still reached about 1.58–1.65 GiB summed RSS, and no current Linux PSS or completed-render release measurement exists. |
| Deployment | Pages serves the exact production artifact tested in CI | Deterministic archive/content manifest/hash and deployed smoke | **Deterministic but not promoted:** two final-source temporary builds match at 8,254,562 bytes/619 entries/SHA-256 `7001d5d727504c4476cee6819a6ab2e32954d3e92be51176457588b04ae28329`, with 68,510 bytes of margin under the 8,323,072-byte upload ceiling. Their byte-identical 133,506-byte manifests hash to `6f05285827b074d545f25866bdbaab5838939560c0b36cca0bbcb8fa07ddb6da`, the distribution verifier passes, and an extracted-package CSV/TSV smoke passes. The distinct checked ZIP remains unchanged at 8,313,372 bytes/618 entries/SHA-256 `e4659f62ad00e5d2bbcf1806dc9ee5fcf2721300bfc7a28dd1b14c8fc84dd6e6`; it was not replaced or promoted because showcase, manual-review, and browser gates remain red. |
| OCR boundary | Image-only pages are explicit unsupported inputs, never successful empties | Detection/status/page-placeholder fixtures only; no OCR assertions | **Implemented/tested within the non-OCR boundary:** fully classified zero-text pages become `unsupported_no_text`/`needsOcr=true` only with complete evidence. The sole review keeps its sparse visible source link and one sanitized 473,015-byte JPEG while excluding hidden OCR text. MinerU is now exactly `unsupported_no_text`, `needsOcr=true`, document/text/semantic classification complete, and source/order clean; it still fails PHP/WordPress conversion because all eight requested page representations remain absent. |

The change is complete only when every row above is green or explicitly split into a separately accepted follow-up with no false “pass.” A screenshot loading successfully is not completion; neither is token overlap without structural proof.

## Remaining order and rationale

1. **Reduce the regenerated showcase's fail-closed set without weakening its
   gates.** Each of the 10 failures needs exact source/visibility/page evidence
   or a deliberately unsupported outcome; MinerU must remain a typed
   `unsupported_no_text` refusal until its eight page requests are safely
   materialized.
2. **Record manual evidence for all 17 remote candidates.** Each needs four
   immutable screenshots and a named, timestamped verdict; hash identity and a
   successful conversion are not semantic acceptance.
3. **Run the real Linux browser resource gate, then build once and promote that
   exact artifact.** Promotion remains last so the tested hash is the deployed
   hash and no red corpus/manual/resource gate is hidden by a successful ZIP.

The key safety rule throughout is: preserve uncertain content, and only make a more semantic output when local evidence plus non-regression controls prove it is better.

## Reproduction and verification commands

The 14-file bounded regression run used:

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

The six additional current suites used:

```sh
php -d memory_limit=768M tools/run-tests.php \
  lanes/markerpdf/tests/PdfDocumentLayoutProfileTest.php \
  lanes/markerpdf/tests/PdfObjectScannerTest.php \
  lanes/markerpdf/tests/PdfResourceLimitDiagnosticsTest.php \
  lanes/markerpdf/tests/PdfVisualOccurrenceInventoryTest.php \
  lanes/pandoc/tests/PdfProtectedTableLayoutTest.php \
  lanes/pandoc/tests/PdfSourceDispositionLedgerTest.php
```

The overlapping run of all 19 new PDF suites plus
`lanes/pandoc/tests/PandocConverterTest.php` passed 1,458 assertions. The
converter was also run independently and passed 375 assertions.

The current Muir fragmentation counts were independently reproduced from the
generated WordPress HTML with `DOMDocument`: 122 nonempty paragraphs, 73 with
at most three characters, and 84 containing one word. The P0 order, visibility,
and brace-loss results began as small in-memory probes at the verified head;
the working tree now carries exact occurrence-order, visibility-policy, and
brace-preservation regressions in the focused suites.

Additional checks:

```sh
php tools/run-tests.php --isolate \
  lanes/pandoc/tests/PdfSemanticRecordPipelineTest.php \
  lanes/pandoc/tests/PandocImportMemoryBudgetTest.php \
  lanes/pandoc/tests/PlaygroundConverterPluginTest.php \
  lanes/pandoc/tests/PdfCorpusExpectationReportTest.php
php tools/pdf-corpus-report.php
php tools/build-pandoc-showcase.php
php tools/build-pandoc-showcase.php --verify-quality-signature
node tools/check-showcase-examples-page.js
node tools/check-pdf-layout-reviewer.mjs
node tools/check-import-job-session.mjs
node tools/check-playground-converter-ui.js
node tools/check-pdf-form-rasterizer.mjs
node tools/check-pdfjs-facts-provider.mjs
node tools/check-pdf-jpx-rasterizer.mjs
node tools/check-pdf-jbig2-rasterizer.mjs
php tools/run-tests.php \
  lanes/pandoc/tests/ShowcaseImportQualityManifestTest.php \
  lanes/pandoc/tests/ShowcaseQualitySignatureTest.php
php tools/run-tests.php lanes/pandoc/tests/ShowcaseHaskellReferenceTimeoutTest.php
```

The signature option is a bounded preflight/self-check and exits before
generation; it does not replace the preceding full showcase build. The two
temporary distribution builds and four-path comparison verifier used:

```sh
plpc_package_a="$(mktemp -d /tmp/port-libs-package-a.XXXXXX)"
plpc_package_b="$(mktemp -d /tmp/port-libs-package-b.XXXXXX)"
php tools/build-playground-converter-plugin.php --target-dir="$plpc_package_a"
php tools/build-playground-converter-plugin.php --target-dir="$plpc_package_b"
php tools/verify-playground-converter-distribution.php \
  "$plpc_package_a/port-libs-playground-converter.zip" \
  "$plpc_package_a/port-libs-playground-converter.manifest.json" \
  "$plpc_package_b/port-libs-playground-converter.zip" \
  "$plpc_package_b/port-libs-playground-converter.manifest.json"
```

The extracted-package smoke used only package A and did not alter the checked
ZIP:

```sh
plpc_extract="$(mktemp -d /tmp/plpc-runtime-smoke.XXXXXX)"
unzip -q "$plpc_package_a/port-libs-playground-converter.zip" -d "$plpc_extract"
```

An inline PHP runner then registered extracted `PortLibs\Pandoc` and
`PortLibs\MarkerPDF` autoloaders; required `PandocConverter`,
`DelimitedTextReader`, `DelimitedTextUpstreamReaderEvidence`, and
`WordPressBlockWriter`; converted `name,value\nalpha,1\n` as CSV and
`name\tvalue\nbeta\t2\n` as TSV to WordPress; and required a `wp:table` plus the
`alpha`/`beta` cells. It exited 0 with `Packaged autoload and CSV/TSV conversion
smoke passed`.

## Primary code and corpus references

- `lanes/pandoc/src/PdfReader.php`
- `lanes/pandoc/src/PdfTextFidelityLedger.php`
- `lanes/pandoc/src/PdfSourceDispositionLedger.php`
- `lanes/pandoc/src/PdfSemanticChunkReconciler.php`
- `lanes/pandoc/src/PdfSemanticRecordPipeline.php`
- `lanes/pandoc/src/PandocMediaExtractor.php`
- `lanes/markerpdf/src/PdfTextExtractor.php`
- `lanes/markerpdf/src/BrowserPdfFactsProvider.php`
- `lanes/markerpdf/src/LayoutOrderer.php`
- `lanes/markerpdf/src/TableRecognizer.php`
- `lanes/markerpdf/tests/PdfContainerDecoderHardeningTest.php`
- `lanes/markerpdf/tests/PdfNestedFormXObjectVisibilityTest.php`
- `lanes/markerpdf/tests/PdfResourceLimitDiagnosticsTest.php`
- `lanes/markerpdf/tests/PdfVisualOccurrenceInventoryTest.php`
- `tools/playground-converter-plugin/port-libs-playground-converter.php`
- `tools/playground-converter-plugin/assets/admin-importer.mjs`
- `pandoc-showcase/playground-converter.js`
- `pandoc-showcase/pdfjs-form-rasterizer.mjs`
- `tools/pdf-layout-corpus-manifest.json`
- `tools/pdf-corpus-table-manifest.json`
- `.port-libs/pdf-corpus/report.json`
- `pandoc-showcase/manifest.json`
- `lanes/pandoc/tests/PdfReaderCorpusQualityTest.php`
- `lanes/pandoc/tests/PdfRegionAwareLayoutTest.php`
- `lanes/pandoc/tests/PdfImportCompletenessTest.php`
- `lanes/pandoc/tests/PdfTextFidelityLedgerTest.php`
- `lanes/pandoc/tests/PdfSourceDispositionLedgerTest.php`
- `lanes/pandoc/tests/PdfProtectedTableLayoutTest.php`
- `lanes/pandoc/tests/PlaygroundConverterPluginTest.php`
