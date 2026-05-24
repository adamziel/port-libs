# Independent Audit - 2026-05-24T22:05Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `d1a7a79b6fe3`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T22:05Z
HEAD: d1a7a79b6fe3
recent history: d1a7a79b Record isolated Syncthing handoff rejection; 03aabd1c Refresh independent audit status; 365e1596 Integrate Syncthing BEP native path slice; 45aa82fb Record Gitoxide handoff rejection; cd6a6b69 Refresh independent audit status; 46b6e962 Record Gitoxide handoff rejection
default status rows including untracked: 24799
tracked dirty rows: 231
dirty shortstat: 230 files changed, 191197 insertions(+), 23492 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty
initial exact root process gate: `pgrep -af '^php tools/run-tests\.php$'` returned no rows
final exact root process gate: active PID 2289667 owned by claude, `php tools/run-tests.php`
root run by this audit: not started; the checkout moved and grew during sampling, and a no-argument root harness appeared before finish, so a duplicate root run was forbidden
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred; every support row still has null dependency-specific upstream denominator/pass-fail ledger
```

Live dirty manifest/status samples are not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail     latest status
difftastic    1257/1368                 3873/0                     pending Gleam slice
dolt          613/613                   471/0                      not committed COALESCE/IFNULL query-diff stack
esbuild       235/2567                  235/0                      uncommitted package resolver/browser-map slice
gitoxide      1481+/2877                7678/0                     pending discover/mailmap/actor stack
libsqlite     234/1589                  234/0                      pending JSON aggregate/table/patch handoff
LightningCSS  3029+/3548                4425/0                     uncommitted supports formatter slice
markerPDF     172+/78                   278/0                      pending FlateDecode predictor pile
pandoc        2276/2276                 410/0                      pending HTML reader standalone del slice
quadrable     115/115                   263/0                      pending docopt/submodule denominator plus broad dirty stack
rclone        521/1601                  521/0                      pending WebDAV multistatus XML slice
Readability   1720+/1984                156/0                      uncommitted eleven-slice fixture/cleanup pile
syncthing     658/658                   9270/0                     pending Windows native-model/BEP slice after one isolated Syncthing integration
```

## Findings

1. **Critical - the repository still has no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit small, reviewable slices with passing verification from a stable snapshot.
   - Evidence: `HEAD` moved again after the prior audit to `d1a7a79b6fe3`; status rows grew to `24799`, tracked dirty rows grew to `231`, and dirty shortstat grew to `230 files changed, 191197 insertions(+), 23492 deletions(-)`. All 12 lane manifests and all 12 lane-status files are dirty. A root test from this state would test a mixed moving pile, not an accept/reject batch.

2. **Critical - one accepted Syncthing commit does not make the remaining Syncthing lane or the repo accepted.**
   - Paths: `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/lane-status.json`, `progress.md`, `audits/integration-status.md`.
   - Goal requirement at risk: accepted progress must be tied to the exact committed slice and must not launder later dirty handoffs into progress.
   - Evidence: recent history includes `365e1596 Integrate Syncthing BEP native path slice`, followed by rejection/status commits. The live Syncthing status now advertises a different Windows native-model/BEP inbound slice with `9270/0` focused evidence and dirty metadata. That post-integration handoff still needs integrator review and root verification from a frozen snapshot.

3. **Critical - every live lane status remains unaccepted worker output.**
   - Paths: `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: dirty worker handoffs must not count as native implementation progress until supervisor/integrator acceptance and verification occur.
   - Evidence: current lane statuses still say root verification, integrator acceptance, uncommitted, not committed, or split/freeze is pending. Several lanes are broad accumulated piles rather than one reviewable slice.

4. **Critical - root harness evidence remains unusable for new acceptance even though the current exact process gate is empty.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md`, `progress.md`.
   - Goal requirement at risk: repo-wide root verification must be serialized and tied to the same frozen snapshot being accepted.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` returned no rows, but the tree moved and grew during sampling. Prior audit/integration records still reference root-red Difftastic `TokenDiffer::isDartLanguage()` and Syncthing `syncthing_session_outbound_frames()` blockers unless superseded by an accepted root run. I did not start a no-argument root harness.

5. **High - `porting.html` is stale relative to live metadata and should not be published as current.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit.
   - Evidence: `porting.html` still says verified source snapshot `0fa9ecafcd10` generated at `2026-05-24 21:19:36 UTC`, while live `HEAD` is `d1a7a79b6fe3` and lane metadata has changed materially.

6. **High - support-library rows are visible but still not first-class support ports.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require the same granularity as lanes: bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has `37` rows, `0` active rows, and all rows still have null `upstreamDenominator` values. These are routing notes, not accepted support-library progress. Missing packages are not final blockers until bounded `sudo -n` installs are attempted or ruled out, but no row currently records that runner ledger.

7. **High - Pandoc rich-format coverage remains over-credited.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`, `dependency-backlog.json`, `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion kernel with meaningful readers/writers and upstream-backed rich-format support, not fixture routing.
   - Evidence: the manifest reports `2276/2276` mapped while lane status reports only `410` PHP behavior tests and full Haskell runner parity remains unexecuted. DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression are visible as gated rows or reuse paths, but none is an active bounded support port with a denominator or pass/fail ledger.

8. **High - markerPDF still maps more semantics than its upstream denominator and mixes dependency planning with native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline; external runtime planning, supplied converter/model callbacks, benchmark archive probing, and dependency inspection cannot count as native conversion progress.
   - Evidence: markerPDF reports more mapped items than the `78` upstream denominator and now carries a long stack of stream/filter, runtime, app, model, benchmark, output, and OCR/layout planning entries. The latest FlateDecode predictor slice is narrow, but broader PDF text dictionaries, page/layout planning, OCR/layout results, table geometry, Unicode/charset, and archive/package concerns remain inactive support rows.

9. **High - base lanes are crossing inactive support-library boundaries without activating shared rows.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: rich dependency work should not count as shared reusable progress unless a bounded row is activated, tested, and evidenced.
   - Evidence: esbuild package exports/browser-map work is ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML work is ahead of inactive `webdav-protocol-core` and `xml-html5-dom-core`; Dolt and libsqlite JSON work is ahead of inactive `json-json5-document-core` and `sql-expression-semantics-core`; Syncthing BEP/session work is ahead of inactive `protobuf-wire-core`; Gitoxide protocol/discovery work remains ahead of inactive `git-wire-protocol-core` and URL support rows.

10. **Medium - manifest/status count units remain non-comparable.**
    - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
    - Goal requirement at risk: progress percentages must be comparable and tied to upstream denominator, mapped tests, PHP pass/fail, phase, blocker, and commit.
    - Evidence: markerPDF reports mapped counts above its upstream path denominator; Pandoc reports full mapping while status has `410` behavior tests; Syncthing, Gitoxide, and LightningCSS `phpPass` values are assertion-like counts; rclone and Dolt mix behavior tests, assertions, selected Go functions, and static path inventories.

11. **Medium - Quadrable's denominator changed from behavior scenarios to path/submodule inventory without a dashboard-level unit warning.**
    - Paths: `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`, `lanes/quadrable/lane-status.json`, `porting.html`.
    - Goal requirement at risk: each lane needs a real upstream denominator and mapped tests in comparable units.
    - Evidence: the manifest reports `115/115` with upstream-plus-submodule path inventory, while the meaningful suite story remains the 34 upstream `check.cpp` scenarios plus proof/sync subcases. Counting initialized submodule files as fully mapped inflates progress unless the dashboard separates path inventory from behavior parity.

12. **Medium - Syncthing URL/query needs are still not routed through the shared URL row.**
    - Paths: `dependency-backlog.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/lane-status.json`.
    - Goal requirement at risk: missing bounded support rows become blockers once the base lane is ready for or blocked by the next essential rich capability.
    - Evidence: Syncthing status includes discovery lookup URL construction and query encoding, but `url-percent-encoding-core` lists `rclone`, `gitoxide`, `esbuild`, `lightningcss`, and `readability` only. Either add Syncthing as a gated consumer with spec/vector expectations, or keep that URL evidence explicitly lane-local and non-reusable.

## Root Harness Decision

The initial exact no-argument root gate was clear, but I did not run `php tools/run-tests.php`. The tree was not stable enough: `HEAD` moved during sampling, dirty status grew, all lane manifests/status files are dirty, and every lane status describes unaccepted output. Before finish, the final gate found active root PID `2289667` owned by `claude` with command `php tools/run-tests.php`, so starting a duplicate root run was forbidden.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; select exactly one owner-free reduced batch, preferably a still-reproducible Difftastic root-red fix or a single Syncthing BEP/session follow-up only after distinguishing it from `365e1596`; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units; activate support rows only behind a real accepted gate or blocker; regenerate dashboard artifacts from the accepted commit; then commit or reject.
