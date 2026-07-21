# Pandoc package architecture

This document describes the current architecture of the native PHP conversion
package. It is the canonical component and boundary map. The numbered
[design decisions](DESIGN_DECISIONS.md) explain why those boundaries exist and
which tests enforce them.

## System boundary

The package converts documents. It accepts source bytes or an application-owned
file path and returns a shared AST, serialized output, media metadata, or chunks
sent to a caller-owned sink. It may write extracted media only when a caller
supplies an explicit output directory.

The package does not accept HTTP uploads, authenticate users, persist jobs,
create WordPress posts, or decide whether preserved source HTML/SVG is safe to
publish. Those responsibilities belong to an importer or other host
application. Likewise, the Playground showcase and browser PDF helpers exercise
and demonstrate the package; they are not conversion-kernel dependencies.

External Pandoc, WordPress core, PDFKit, browsers, and similar tools are used as
versioned evidence or target-consumer oracles. The production conversion path
is native PHP.

## Current boundaries

These constraints are part of the architecture and must remain visible to
integrators:

- There is no universal input-size, elapsed-time, or memory ceiling. Most APIs
  still receive a complete source string, and some reader limits default to the
  caller's PHP process limit. Hosts must impose workload budgets.
- ZIP entry-count, total expanded-size, expansion-ratio, and per-entry limits
  are available at the low-level package/preflight boundary, but they are not
  automatically propagated through every document reader. Some package-part
  reads remain unbounded.
- WordPress output sinks can avoid retaining the final serialized string, and
  selected EPUB paths are file-backed or node-streaming. Other writers may
  materialize complete output before calling the sink. The architecture as a
  whole is not a general streaming parser and may retain a document AST.
- Reader failure, fallback, and diagnostic shapes are not yet completely
  uniform. Callers must handle exceptions and inspect returned diagnostics,
  AST metadata, and PDF `sourceIntegrity` where applicable.
- Raw source HTML, scripts, event attributes, JavaScript URLs, and active SVG
  can remain in conversion output. Custom HTML classification is an explicit
  trust boundary, not an XSS filter.
- The WordPress consumer contract is pinned to the hash-verified WordPress core
  version used by the test fixture. It does not claim compatibility with every
  future WordPress release without a corresponding evidence update.
- PDF conversion can report semantic text as complete while separately needing
  visual page representation. OCR and browser-raster handoffs are explicit;
  the reader does not invent evidence or silently certify an unresolved page.

## Conversion pipeline

```text
source bytes or file path
          |
          v
  PandocConverter facade
          |
          v
 PandocFormatRegistry ---- aliases, support state, implementation dispatch
          |
          v
      format reader ------ package/PDF structural checks and diagnostics
          |
          v
        AstNode ----------- shared semantic and provenance boundary
          |
          +---- optional PandocMediaExtractor / MediaBag
          |                 rewrites references; separates media bodies
          v
       target writer ------ WordPress, HTML, Markdown, and other targets
          |
          v
 string result or caller-owned output sink
```

[`PandocConverter`](src/PandocConverter.php) is the public coordinator. It
canonicalizes formats, keeps `readerOptions` and `writerOptions` separate,
performs the optional media phase, and selects eager, file-backed, or sink
paths. It does not hide support-state failures: unknown or unsupported formats
raise exceptions.

The public entry points are:

- `read()` and `readFile()` for source-to-AST conversion;
- `write()` and `writeTo()` for AST-to-target conversion;
- `convert()` and `convertFile()` for the common eager pipeline;
- `convertToSink()` and `convertFileToSink()` for caller-owned output;
- `convertWithMedia()` and `convertFileWithMedia()` for explicit media
  extraction, rewritten output, diagnostics, and bounded source-integrity
  summaries;
- `canRead()`, `canWrite()`, and canonical-format helpers for capability
  negotiation.

## Format registry and dispatch

[`PandocFormatRegistry`](src/PandocFormatRegistry.php) is the runtime authority
for the upstream and project-local format inventory, its aliases,
implementation classes, and support states. Project-local PDF and legacy DOC
inputs are intentionally distinguished from the upstream Pandoc format
inventory. [`PandocConverter`](src/PandocConverter.php) layers a small set of
public aliases and the local WordPress output target on top, so integrations
should use the facade for final capability negotiation.

Dispatch follows registry and facade canonicalization after alias and
extension-profile normalization. A reader class being present does not itself
establish support, and a successful conversion of one fixture does not
establish parity. Registry states mean:

- `reader-equivalent`: the implementation has the package's declared,
  upstream-mapped reader evidence;
- `partial`: useful conversion exists, but known behavior or evidence remains
  incomplete;
- `unsupported`: no native implementation is advertised.

Aliases preserve intentional format distinctions. For example, Markdown
extension profiles are canonicalized for dispatch while their requested
feature switches are still passed to the reader.

## Shared AST

[`AstNode`](src/AstNode.php) is the semantic boundary between readers,
transformations, and writers. Readers produce a root `document` node; writers
consume the same public node model. Node type, attributes, and children form the
stable public shape. Provenance and review metadata travel as attributes and
must be retained by transforms that claim fidelity.

The implementation uses compact text-child forms, serialized child lists, lazy
attribute resolvers, and ownership-transfer paths because PHP arrays have high
per-node overhead. Those are storage details. Materializing a compact or lazy
node must expose the same public attributes and children and produce the same
writer bytes as its eager form. Memory work therefore needs semantic
equivalence tests, not only lower peak measurements.

The AST is semantic rather than page-layout-specific. Readers may attach
format-specific evidence, but writers must remain able to ignore unknown
provenance without corrupting ordinary content.

## Reader families

Readers share the AST contract but use format-appropriate front ends:

- Text, markup, bibliography, and wiki readers tokenize or parse directly into
  semantic blocks and inlines.
- HTML and XML-family readers use DOM/token bridges with bounded review
  summaries and preserve literal raw regions where the target AST has no safer
  semantic equivalent. Complete source parsing is not globally byte- or
  node-bounded.
- DOCX, PPTX, XLSX, ODT, and EPUB readers combine a package layer with
  format-specific relationship, manifest, XML, and style interpretation.
- PDF uses a source-facts and semantic-reconciliation pipeline because visual
  paint order is not a ready-made reading order.
- Legacy DOC is project-local and keeps its own constrained interpretation
  boundary.

Repairs for malformed or ambiguous documents must be deterministic and
reviewable. They should carry metadata or diagnostics rather than silently
turning an inference into an exact-parity claim. Readers do not execute
document-supplied scripts, notebook code, macros, TeX, or other programs.

## Package and archive layer

[`ZipPackage`](src/ZipPackage.php) provides the shared pure-PHP ZIP boundary for
package formats. [`EpubArchive`](src/EpubArchive.php) and its implementations
allow EPUB to use file-backed native Zip support where available.

The package layer verifies structure and records raw provenance. Depending on
the API and selected policy, it rejects unsafe paths, duplicates, symlinks and
special files, unsupported flags/methods, inconsistent local/central records,
size inconsistencies, and CRC failures. Strict preflight can apply total
uncompressed-size, expansion-ratio, per-entry-size, and entry-count policies.

The optional `maxEntryCount` gate is intentionally early: once the EOCD count
exceeds the caller's limit, strict raw preflight does not build a per-entry
inventory or instantiate entry objects. This protects the policy boundary
itself from an entry-count amplification attack.

These controls are layered, not universal defaults. An importer accepting an
untrusted package should run its selected preflight before conversion and
should apply source, time, and process-memory limits too. Until all reader call
sites propagate one quota object, documentation and code must not claim that
every package part or reader is comprehensively bounded.

## PDF semantic and visual pipeline

PDF requires separate evidence for source text, reading order, semantic
structure, and visual representation. MarkerPDF supplies source facts.
[`PdfReader`](src/PdfReader.php) normalizes those facts and runs named stages
through [`PdfSemanticRecordPipeline`](src/PdfSemanticRecordPipeline.php).
Stages record auditable boundaries; destructive repairs require source or
geometry evidence and are followed by exact-source reconciliation.

Two independent ledgers prevent pleasant-looking output from being mistaken
for fidelity:

- [`PdfTextFidelityLedger`](src/PdfTextFidelityLedger.php) audits tokens,
  significant characters, and adjacency.
- [`PdfSourceDispositionLedger`](src/PdfSourceDispositionLedger.php) accounts
  for each source occurrence and its output, evidence-backed omission, or
  unresolved state.

The AST retains detailed metadata. The media-aware facade exposes only a
bounded `sourceIntegrity` summary. `sourceIntegrity.complete` requires document
and semantic-text completeness, complete source bindings and source edges,
preserved significant-character order, zero unresolved occurrences, and
complete page representation. These fields fail closed when evidence is absent.

The preferred result is editable semantic flow, not an indiscriminate page
screenshot. Image-only pages, unsupported embedded image encodings, and visual
Form/page regions may require OCR or raster bytes from a host adapter. Browser
or prerender code must return those bytes against stable requests; a static
preview limit is not a valid substitute for resolving publication assets.
When a region cannot be represented, the integration must preserve a typed
fallback or refuse completeness rather than drop it silently.

The detailed region-aware rationale remains in
[`notes/pdf-region-aware-import-design-20260714.md`](notes/pdf-region-aware-import-design-20260714.md).

## Media phase and ownership

Parsing and media materialization are separate. The optional
[`PandocMediaExtractor`](src/PandocMediaExtractor.php) gathers package, data-URI,
local, or PDF media into [`MediaBag`](src/MediaBag.php), selects safe relative
paths, records MIME/path/hash provenance, resolves deterministic path
collisions, and returns a rewritten AST.

Media bodies do not live in public AST nodes. At the lower-level extractor
boundary, entries contain bodies so an application can persist them. The
`PandocConverter` facade removes bodies from returned public metadata. It
writes files only when `extractMedia.outputDirectory` explicitly authorizes a
destination. This keeps storage, retention, deduplication, accepted MIME types,
and public URLs under host ownership.

PDF media is additionally tied to stable occurrence/page evidence. Image modes
such as `all`, `important`, and `none` select policy, but every inspected visual
occurrence must still receive a disposition. Optional browser-decoded raster
inputs are validated and bound to the immutable source before they are used.

## Writers and WordPress blocks

Writers consume the shared AST and prefer native constructs of their target.
Eager and sink output must remain deterministic and byte-equivalent.

[`WordPressBlockWriter`](src/WordPressBlockWriter.php) delegates to focused
renderers for core nodes, extended nodes, inline content, lists, tables, and
code. It emits Gutenberg's comment-delimited block grammar and uses native core
blocks where possible. Literal raw blocks and known structures without a native
mapping become Custom HTML rather than leaking into an unclassified classic
block. Unknown node types are not promised a fallback.

Generated text and attributes are escaped or allowlisted by the relevant
renderer. Literal source raw HTML is intentionally preserved. When a paragraph
or plain block contains active raw inline HTML/SVG, the whole HTML fragment is
classified as `core/html`; ordinary raw inline markup can remain in a normal
paragraph, and SVG media remains `core/image`. This distinction keeps the
target structure honest but does not make preserved markup safe to publish.

Code blocks are semantic content. Newlines, indentation, aligned comments, and
the number and order of listings must survive the official WordPress parser and
serializer. The TraceMonkey fixture is an exact consumer contract for this
property, not a visual snapshot.

## Errors and diagnostics

Unknown formats, unsupported formats, invalid API options, and structural
conditions that prevent a trustworthy parse generally raise exceptions.
Recoverable fidelity limitations and evidence-backed repairs generally remain
as diagnostics or metadata so a host can decide whether to publish, retry with
a different policy, request OCR/raster help, or send the document to review.

Because readers do not yet expose one universal result envelope, a host should:

1. negotiate capability through the facade/registry;
2. apply its own workload and package policy before conversion;
3. catch conversion exceptions;
4. inspect media diagnostics and PDF `sourceIntegrity` when those paths are
   used;
5. apply publication and sanitization policy after conversion.

An AST's existence alone is not a proof that every source feature was
represented.

## Streaming and memory model

Shared-hosting constraints shape the public and internal APIs:

- WordPress sink methods can avoid retaining the final output string;
- EPUB-to-WordPress can stream spine nodes and use a file-backed archive;
- PDF has ownership-transfer paths and releases superseded evidence graphs;
- AST children and optional attributes may remain compact or lazy;
- diagnostic/public summaries are bounded instead of serializing large
  internal ledgers;
- archive and media reads provide bounded paths where their caller supplies a
  policy.

These optimizations may not change semantics. Each needs an eager/materialized
equivalence contract plus a peak-memory or early-rejection test. Process-level
memory tests are kept separate from the main runner so the measurement includes
the same PHP ceiling a production worker would see. Non-WordPress writers may
still materialize their complete serialized result before invoking a sink.

## Test and evidence architecture

The test system makes different kinds of claims at different layers:

1. **Focused synthetic contracts** isolate parsing, rendering, malformed-input,
   and resource-boundary behavior.
2. **Pinned upstream fixtures and goldens** compare against a declared upstream
   version or source commit. Hashes and denominators make the claim auditable.
3. **Native AST comparisons** establish semantic mappings without counting an
   external implementation as production code.
4. **Target-consumer round trips** parse and serialize output with the official
   consumer, notably WordPress core.
5. **Real-document showcase coverage** catches cross-component failures such as
   code-listing loss, visual PDF artifacts, and media-reference mistakes.
6. **PDF fidelity and source-disposition ledgers** test conservation and source
   binding independently of visual appeal.
7. **Resource tests** exercise early rejection, bounded diagnostics, isolated
   memory ceilings, and eager/streaming equivalence.

When a bug crosses layers, add the smallest synthetic reproducer and retain a
real fixture or consumer round trip when it guards a distinct integration
failure. Assert exact semantic content whenever possible; “did not crash” is
not a fidelity test.

Useful gates include:

- [`PandocDocumentationContractTest`](tests/PandocDocumentationContractTest.php)
  for package documentation and boundary discoverability;
- [`WordPressBlockWriterCoreRoundTripTest`](tests/WordPressBlockWriterCoreRoundTripTest.php)
  for Gutenberg consumer compatibility, raw-content classification, and code
  fidelity;
- [`ZipPackageTest`](tests/ZipPackageTest.php) for archive structure and early
  policy rejection;
- [`PandocImportMemoryBudgetTest`](tests/PandocImportMemoryBudgetTest.php) for
  process-level ceilings;
- [`PdfReaderCorpusQualityTest`](tests/PdfReaderCorpusQualityTest.php) and
  [`ShowcaseImportQualityManifestTest`](tests/ShowcaseImportQualityManifestTest.php)
  for real-document quality boundaries.

[`UPSTREAM_TEST_MANIFEST.json`](UPSTREAM_TEST_MANIFEST.json) and
[`lane-status.json`](lane-status.json) are current generated evidence/status,
not substitutes for architecture policy. CI is split across focused workflows
under [`.github/workflows`](../../.github/workflows/) so a lane runs the
relevant evidence without requiring every repository test for every change.

## Package map

| Path | Responsibility |
| --- | --- |
| `src/PandocConverter.php` | Public conversion facade and option layering |
| `src/PandocFormatRegistry.php` | Format inventory, aliases, support claims, dispatch |
| `src/AstNode.php` | Shared semantic AST and compact/lazy public representation |
| `src/*Reader.php` | Format-specific interpretation into the AST |
| `src/Pdf*.php` | PDF facts, semantic reconstruction, provenance, and fidelity ledgers |
| `src/ZipPackage.php`, `src/EpubArchive*.php` | Package structure, provenance, and bounded archive paths |
| `src/MediaBag.php`, `src/PandocMediaExtractor.php` | Media ownership, path/MIME provenance, and AST rewriting |
| `src/*Writer.php`, `src/WordPress*Renderer.php` | Target serialization |
| `tests/` | Focused, upstream, corpus, consumer, and resource contracts |
| `fixtures/`, `examples/` | Local regression inputs and expected examples |
| `notes/` | Historical implementation and audit records |
| `reports/` | Generated or point-in-time evidence reports |

## Changing the architecture safely

A change that moves a boundary or alters a durable invariant must update this
document and [the decision register](DESIGN_DECISIONS.md) in the same pull
request. It should also update the registry/evidence status when a support claim
changes and add tests at every newly crossed boundary: focused semantics,
target consumer or real corpus where relevant, and resource rejection for work
that can amplify input.
