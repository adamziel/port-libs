# Pandoc package design decisions

This register records durable decisions for the native PHP conversion package.
The decisions are normative for maintainers; dated files under `notes/` explain
individual implementation slices but do not replace this register.

Each decision states the boundary, why it exists, its consequences, and the
evidence that should fail when the decision is accidentally reversed.

## ADR-001: Keep the production conversion path native PHP

**Decision.** Readers, the shared AST, transformations, and writers run in
standard PHP. External Pandoc, browsers, WordPress core, PDFKit, and other tools
may be pinned evidence or target-consumer oracles, but they are not required to
perform the production conversion.

**Why.** The package targets WordPress, Playground, migration tools, and shared
hosting where spawning or installing a foreign executable is unavailable or
unreliable. Independent oracles are still valuable because they prevent the
implementation from grading itself.

**Consequences.** A feature implemented only by shelling out remains
unsupported by this package. Evidence reports must distinguish native behavior
from reference-tool behavior.

**Enforced by.** [`PandocFormatRegistry`](src/PandocFormatRegistry.php), pinned
upstream manifests, native reader/writer tests, and distribution tests that load
the PHP implementation.

## ADR-002: Use one shared semantic AST as the reader/writer boundary

**Decision.** Every reader produces [`AstNode`](src/AstNode.php), and every
writer consumes it. Format-specific facts may be attached as provenance, but a
reader must not directly generate one target's serialized output.

**Why.** A shared intermediate representation prevents the number of conversion
paths from growing as readers multiplied by writers. It also gives media,
fidelity audits, and host integrations one inspection boundary.

**Consequences.** New semantic behavior should first be expressed as a stable
node or attribute and then supported by relevant writers. Unknown provenance
must be preserved by transforms or harmlessly ignored. Compact, serialized, and
lazy storage are implementation details that must materialize to the same public
tree.

**Enforced by.** [`CompactAstTest`](tests/CompactAstTest.php), converter tests,
writer tests, and eager/materialized equivalence contracts.

## ADR-003: Treat the format registry as capability truth

**Decision.** [`PandocFormatRegistry`](src/PandocFormatRegistry.php), plus the
facade's canonical aliases, decides whether and how a format is dispatched.
Class existence, file extension, or upstream Pandoc support does not establish
native support.

**Why.** The project mirrors a broad upstream inventory while implementations
and evidence mature at different rates. Explicit state prevents a partial
reader from being advertised as equivalent.

**Consequences.** `reader-equivalent`, `partial`, and `unsupported` remain
distinct claims. Project-local formats such as PDF and legacy DOC stay separate
from the upstream denominator. Alias changes require registry tests and must
preserve requested extension profiles.

**Enforced by.** [`PandocFormatRegistryTest`](tests/PandocFormatRegistryTest.php),
[`UPSTREAM_TEST_MANIFEST.json`](UPSTREAM_TEST_MANIFEST.json), and
[`lane-status.json`](lane-status.json).

## ADR-004: Make repairs deterministic, reviewable, and provenance-carrying

**Decision.** Readers may repair malformed syntax or reconstruct ambiguous
semantics only through deterministic rules. A repair or incomplete
interpretation must remain visible in AST metadata, diagnostics, source
dispositions, or a scoped support claim.

**Why.** Importers need useful results from imperfect documents, but silent
guessing converts uncertainty into data loss. Provenance lets a host choose to
publish, review, retry, or fall back.

**Consequences.** A readable AST is not automatically proof of complete
conversion. Readers do not execute document-supplied scripts, macros, notebook
cells, TeX, or other embedded programs. Destructive repairs need exact source or
layout evidence and corresponding regression tests.

**Enforced by.** Reader hardening tests, malformed-input fixtures, PDF stage
audits, and showcase quality gates.

## ADR-005: Keep WordPress serialization separate from importing

**Decision.** `wordpress` is a writer target. The package produces Gutenberg
block markup; it does not create posts, upload attachments, authenticate,
authorize, persist resumable jobs, or decide publication policy.

**Why.** Conversion is reusable outside WordPress and can be tested
deterministically. Importer lifecycle and security depend on the host
application and site policy.

**Consequences.** A WordPress importer is an adapter around this package. The
Playground implementation demonstrates one adapter but is not the architecture
of the conversion kernel. Plugin API keys, capabilities, nonces, post status,
and storage belong elsewhere.

**Enforced by.** [`PandocConverter`](src/PandocConverter.php),
[`WordPressBlockWriter`](src/WordPressBlockWriter.php), and this package's
documentation contract.

## ADR-006: Separate raw-content fidelity from publication security

**Decision.** Generated text and attributes are escaped or allowlisted, while
literal source raw HTML remains literal for fidelity. Active raw inline markup
causes its containing paragraph/plain fragment to be emitted as `core/html`.
Ordinary raw inline markup may remain in `core/paragraph`, and SVG media remains
`core/image`.

**Why.** Silently discarding a script, event attribute, SVG, or unsupported raw
structure corrupts the source. Placing active markup inside a normal paragraph
also lies to Gutenberg's block validator. Explicit Custom HTML isolates the
trust boundary without pretending to sanitize it.

**Consequences.** Scripts, event handlers, JavaScript URLs, and active SVG can
still exist in output. `core/html` is classification, not an XSS guarantee. A
publisher must apply its own HTML/SVG/URL/MIME and user-capability policy before
publication.

**Enforced by.** [`WordPressTopLevelCoreNodeRenderer`](src/WordPressTopLevelCoreNodeRenderer.php)
and the raw-inline versus SVG-media contract in
[`WordPressBlockWriterCoreRoundTripTest`](tests/WordPressBlockWriterCoreRoundTripTest.php).

## ADR-007: Test target semantics through the real consumer

**Decision.** Serialized output is not accepted merely because it resembles
the target grammar. Where a stable consumer exists, parse and serialize through
that consumer and assert the semantic payload.

**Why.** Gutenberg can accept block-looking markup while invalidating or
reclassifying inner HTML. Similar integration errors are invisible to string
snapshots or block-count tests.

**Consequences.** The WordPress contract pins official core parser files and
their hashes. Version upgrades are deliberate evidence changes. Code listings
must retain exact normalized line endings, indentation, aligned comments,
count, and order; they are semantic content, not decorative layout.

**Enforced by.** [`WordPressBlockWriterCoreRoundTripTest`](tests/WordPressBlockWriterCoreRoundTripTest.php),
including the three exact TraceMonkey code listings and full showcase round
trips.

## ADR-008: Reconstruct and certify PDF content from independent evidence

**Decision.** PDF conversion prefers editable semantic flow and separately
accounts for source text, reading order, semantic structure, and visual page
representation. Destructive semantic stages require evidence. Fidelity and
source-disposition ledgers independently audit the result.

**Why.** PDF paint order is neither reading order nor document structure, and a
visually plausible output can still lose or reorder content. Conversely, a page
screenshot preserves appearance while discarding editable semantics.

**Consequences.** [`PdfTextFidelityLedger`](src/PdfTextFidelityLedger.php) and
[`PdfSourceDispositionLedger`](src/PdfSourceDispositionLedger.php) remain
separate. `sourceIntegrity.complete` fails closed unless all required text,
binding, ordering, occurrence, and page-representation evidence is complete.
OCR/image-only status and browser-raster handoffs remain explicit. Unresolved
visuals cannot be replaced by a preview-memory-limit message and called
complete.

**Enforced by.** [`PdfSemanticRecordPipeline`](src/PdfSemanticRecordPipeline.php),
PDF ledger tests, [`PdfReaderCorpusQualityTest`](tests/PdfReaderCorpusQualityTest.php),
and showcase PDF quality gates.

## ADR-009: Keep media extraction explicit and storage caller-owned

**Decision.** Parsing produces media references. An optional media phase loads
and canonicalizes bodies, assigns safe paths, records MIME/path/hash provenance,
and rewrites a new AST. The facade returns public metadata without bodies and
writes bodies only when `outputDirectory` is explicitly supplied.

**Why.** Conversion should not silently mutate the filesystem or choose public
URLs, retention, deduplication, or accepted MIME types for its host. Keeping
bodies outside the AST prevents large binary payloads from multiplying through
tree transforms.

**Consequences.** Hosts own media storage lifecycle and publication policy.
Lower-level extractor callers may receive bodies for application-managed
persistence. Paths and MIME types must remain provenance-carrying and collision
repair must be deterministic.

**Enforced by.** [`MediaBag`](src/MediaBag.php),
[`PandocMediaExtractor`](src/PandocMediaExtractor.php), media tests, and
converter public-entry filtering.

## ADR-010: Reject archive amplification before materialization where possible

**Decision.** Archive validation is layered. Structural validation precedes
entry reads; caller-selected limits cover total uncompressed bytes, expansion
ratio, per-entry bytes, and entry count where the API supports them. The ZIP
entry-count gate runs from EOCD metadata before per-entry inventories or entry
objects are built.

**Why.** A policy check that first materializes attacker-controlled entries is
itself vulnerable to amplification. Package formats also need path, duplicate,
link/type, compression, size, and CRC validation independent of XML parsing.

**Consequences.** The new `maxEntryCount` control is opt-in and low-level. It is
not a universal converter quota, and some current package-part reads are still
unbounded. Hosts accepting untrusted packages must run preflight and impose
source/process/time limits until one policy object is propagated by all readers.

**Enforced by.** [`ZipPackage`](src/ZipPackage.php) and
[`ZipPackageTest`](tests/ZipPackageTest.php), especially tests that assert an
over-count archive stops before inventory and instantiation.

## ADR-011: Optimize memory without changing semantics

**Decision.** Caller-owned sinks, file-backed archives, node streaming, compact
or lazy AST storage, ownership-transfer APIs, released PDF phase graphs, and
bounded public diagnostics are first-class techniques. They may not change the
materialized tree or serialized result.

**Why.** The package must work in WordPress and shared-hosting process ceilings,
where an additional complete source, AST, media, or output copy can be the
difference between success and worker termination.

**Consequences.** Sink output must equal eager output byte for byte. Selected
EPUB-to-WordPress paths can stream; non-WordPress writers may materialize their
complete output before invoking a sink, and other readers may still retain the
AST or source string. Documentation must not describe the whole pipeline as
streaming. Memory claims are measured in isolated PHP processes with explicit
limits.

**Enforced by.** [`PandocConverterStreamingTest`](tests/PandocConverterStreamingTest.php),
[`CompactAstTest`](tests/CompactAstTest.php), and
[`PandocImportMemoryBudgetTest`](tests/PandocImportMemoryBudgetTest.php).

## ADR-012: Keep errors, diagnostics, and support claims honest

**Decision.** Conditions that prevent a trustworthy operation throw; bounded,
recoverable fidelity limitations remain diagnostics or metadata. Support and
parity claims remain pinned to their declared evidence rather than being
inferred from a non-crashing conversion.

**Why.** A host needs to distinguish an invalid request, an unsupported format,
a recoverable conversion, and a result that is incomplete or needs OCR/review.
Collapsing these states creates silent publication errors.

**Consequences.** Reader result shapes are not yet fully uniform, so callers
must currently inspect exceptions, diagnostics, metadata, and PDF integrity as
appropriate. `partial` must never be silently promoted to
`reader-equivalent`. “Did not crash” is not a sufficient content-fidelity test.

**Enforced by.** Registry/evidence tests, typed diagnostic tests, source
integrity contracts, and malformed-input suites.

## ADR-013: Build claims from layered, reproducible evidence

**Decision.** Important behavior is covered at the narrowest reproducible layer
and at each distinct integration boundary it crosses: focused semantics,
upstream mapping, real corpus, target consumer, and resource rejection as
applicable.

**Why.** Synthetic tests localize a defect; real documents expose component
interactions; official consumers validate target grammar; process and archive
tests detect amplification. No single layer substitutes for the others.

**Consequences.** A regression such as missing TraceMonkey code needs exact
listing assertions and WordPress round trips, not only screenshots. A resource
limit needs a test that proves rejection occurs before expensive work. Upstream
fixtures and consumer sources are version/hash pinned. Generated evidence files
record current results; architecture docs record policy.

**Enforced by.** [`tools/run-tests.php`](../../tools/run-tests.php), focused
GitHub workflows, upstream manifests, showcase quality tests, consumer round
trips, and [`PandocDocumentationContractTest`](tests/PandocDocumentationContractTest.php).

## Updating a decision

When a decision changes, update this register and
[`ARCHITECTURE.md`](ARCHITECTURE.md) in the same pull request. State the new
boundary, migrate any public API or evidence claim, and update tests that
enforce the old and new consequences. Do not leave a changed contract only in a
dated note or pull-request discussion.
