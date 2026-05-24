# Independent Audit - 2026-05-24T21:59Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `30f1c09c Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T21:58:49Z
HEAD: 30f1c09c9059
recent history: 30f1c09c Refresh independent audit status; 39562f51 Record Syncthing handoff rejection; 29b4eecb Record Pandoc handoff rejection; c201145c Refresh independent audit status; 80e90963 Record markerPDF handoff instability; 3cb7c1f2 Refresh evaluator feedback
default status rows including untracked: 24591
tracked dirty rows: 229
dirty shortstat: 229 files changed, 189549 insertions(+), 22844 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty
exact root process gate: initial `pgrep -af '^php tools/run-tests\.php$'` returned no rows; final pre-commit gate matched `2234239 php tools/run-tests.php`
active root owner evidence: `2234239 claude 2224611 Rs 00:10 php tools/run-tests.php`
root run by this audit: not started; the checkout is still moving and too dirty for a meaningful audit-owned aggregate run, and the final exact gate was occupied
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred; every sampled row still has upstreamDenominator null and no PHP pass/fail ledger
```

Live dirty manifest/status samples are not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail     latest status
difftastic    status fields nested       3858/0                     pending Objective-C slice
dolt          status fields nested       470/0                      not committed query-diff/expression stack
esbuild       status fields nested       234/0                      uncommitted package resolver/browser-map slice
gitoxide      status fields nested       7678/0                     pending discover/mailmap/actor stack
libsqlite     status fields nested       232/0                      pending JSON aggregate/table-output slice
LightningCSS  status fields nested       4418/0                     uncommitted @page validation slice
markerPDF     171/78                     276/0                      pending RunLengthDecode filter slice
pandoc        2276/2276                  409/0                      pending HTML reader linebreak slice
quadrable     115/115                    263/0                      pending docopt/submodule denominator slice plus broad dirty stack
rclone        518/1601                   518/0                      pending WebDAV PROPPATCH lock-integration slice
Readability   status fields nested       155/0                      uncommitted mixed fixture/cleanup pile
syncthing     status fields nested       9265/0                     pending Windows native-model/BEP slice
```

## Findings

1. **Critical - the repository still has no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit small, reviewable slices with passing verification from a stable snapshot.
   - Evidence: `HEAD` moved again to `30f1c09c9059`; untracked-inclusive status is now `24591`; tracked dirty rows remain `229`; dirty shortstat is `229 files changed, 189549 insertions(+), 22844 deletions(-)`. All 12 lane manifests and all 12 lane-status files are dirty. A root test from this state would test a mixed pile, not an accept/reject batch.

2. **Critical - every live lane status is still unaccepted worker output.**
   - Paths: `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: dirty worker handoffs must not count as native implementation progress until supervisor/integrator acceptance and verification occur.
   - Evidence: each lane status says pending, uncommitted, not committed, or root/integrator acceptance pending. Recent Git history is rejection/audit/status-heavy (`30f1c09c`, `39562f51`, `29b4eecb`, `c201145c`, `80e90963`) rather than accepted implementation-heavy.

3. **Critical - root harness evidence remains unusable for new acceptance, and the final root gate was occupied.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md`, `progress.md`.
   - Goal requirement at risk: repo-wide root verification must be serialized and tied to the same frozen snapshot being accepted.
   - Evidence: the initial exact gate was clear, but the checkout is not frozen and has changed since the prior audit. A final pre-commit exact gate matched active root PID `2234239` owned by `claude` (`php tools/run-tests.php`). Prior integration/audit records still identify root-red Difftastic `TokenDiffer::isDartLanguage()` and Syncthing `syncthing_session_outbound_frames()` blockers. I did not start a no-argument root harness.

4. **High - `porting.html` is stale relative to live metadata and should not be published as current.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit.
   - Evidence: `porting.html` still says verified source snapshot `0fa9ecafcd10` generated at `2026-05-24 21:19:36 UTC`, while the live dirty checkout is at `30f1c09c9059` with updated pending lane metadata such as markerPDF `171/78`, Pandoc `2276/2276`, Quadrable `115/115`, and rclone `518/1601`.

5. **High - support-library rows are visible but still not first-class support ports.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require the same granularity as lanes: bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has `37` rows, `0` active rows, and sampled rows still show `upstreamDenominator` as null with no dependency-specific PHP pass/fail ledger. These are routing notes, not accepted support-library progress. Missing packages are not final blockers until bounded `sudo -n` installs are attempted or ruled out, but no row currently records that runner ledger.

6. **High - Pandoc rich-format coverage remains over-credited.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`, `dependency-backlog.json`, `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion kernel with meaningful readers/writers and upstream-backed rich-format support, not just fixture routing.
   - Evidence: the manifest reports `2276/2276` mapped while lane status reports `409` PHP behavior tests and full Haskell runner parity remains unexecuted. DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, templates, citations, math, tables, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression are visible as gated backlog rows or reuse paths, but none is an active bounded support port with a denominator or pass/fail ledger.

7. **High - markerPDF still maps more semantics than its upstream denominator and mixes dependency planning with native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline; external runtime planning, supplied converter/model callbacks, benchmark archive probing, and dependency inspection cannot count as native conversion progress.
   - Evidence: markerPDF now maps `171` semantics against only `78` upstream paths. The current RunLengthDecode slice is narrow and focused, but the manifest still includes Streamlit/FastAPI/converter/model/runtime planning and tabled-pdf inspection alongside native PDF stream extraction. PDF text dictionaries, page/layout planning, OCR/layout results, table geometry, Unicode/charset, and archive/package concerns remain inactive support rows.

8. **High - base lanes are crossing inactive support-library boundaries without activating shared rows.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: rich dependency work should not count as shared reusable progress unless a bounded row is activated, tested, and evidenced.
   - Evidence: esbuild package exports/browser-map work is ahead of deferred `js-package-resolution-core`; rclone WebDAV PROPPATCH/LOCK/XML work is ahead of inactive `webdav-protocol-core` and `xml-html5-dom-core`; Dolt and libsqlite JSON work is ahead of inactive `json-json5-document-core` and `sql-expression-semantics-core`; Syncthing BEP/session work is ahead of inactive `protobuf-wire-core`; Gitoxide protocol/discovery work remains ahead of inactive `git-wire-protocol-core` and URL support rows.

9. **Medium - manifest/status count units remain non-comparable.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: progress percentages must be comparable and tied to upstream denominator, mapped tests, PHP pass/fail, phase, blocker, and commit.
   - Evidence: markerPDF reports `171/78`; Pandoc reports `2276/2276` while status has `409` PHP behavior tests; Syncthing and Gitoxide `phpPass` are assertion-like counts (`9265`, `7678`); rclone uses behavior tests (`518`) and assertions in prose; Quadrable changed its denominator to include submodule paths (`115/115`), not just upstream behavior scenarios.

10. **Medium - Quadrable's denominator changed from behavior scenarios to path inventory without a dashboard-level unit warning.**
    - Paths: `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`, `lanes/quadrable/lane-status.json`, `porting.html`.
    - Goal requirement at risk: each lane needs a real upstream denominator and mapped tests in comparable units.
    - Evidence: the manifest now reports `115/115` with unit `tracked upstream plus initialized submodule paths`; the earlier meaningful suite story was the 34 upstream `check.cpp` scenarios plus proof/sync subcases. Counting submodule files as fully mapped can inflate progress unless the dashboard separates path inventory from behavior parity.

11. **Medium - broad dirty piles are blocking reviewable-slice acceptance.**
    - Paths: `lanes/readability/lane-status.json`, `lanes/quadrable/lane-status.json`, `lanes/dolt/lane-status.json`, `lanes/gitoxide/lane-status.json`, `lanes/syncthing/lane-status.json`.
    - Goal requirement at risk: prefer small correct slices over broad shallow ports and commit small reviewable slices with passing tests.
    - Evidence: Readability advertises ten interleaved fixture/cleanup slices; Quadrable lists many unrelated unaccepted proof/transport/store/helper slices; Dolt mixes a long query-diff expression stack; Gitoxide mixes discover, mailmap, and actor handoffs; Syncthing has a large route, GUI, discovery, BEP, native-model, and URL/query history in lane metadata.

12. **Medium - Syncthing URL/query needs are still not routed through the shared URL row.**
    - Paths: `dependency-backlog.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/lane-status.json`.
    - Goal requirement at risk: missing bounded support rows become blockers once the base lane is ready for or blocked by the next essential rich capability.
    - Evidence: Syncthing status includes discovery lookup URL construction and query encoding, but `url-percent-encoding-core` lists `rclone`, `gitoxide`, `esbuild`, `lightningcss`, and `readability` only. Either add Syncthing as a gated consumer with spec/vector expectations, or keep that URL evidence explicitly lane-local and non-reusable.

## Root Harness Decision

The exact no-argument root gate was clear at the initial sample, but the final pre-commit gate matched active root PID `2234239` owned by `claude` (`php tools/run-tests.php`). I did not run `php tools/run-tests.php`. The tree is not stable enough: `HEAD` moved again, all lane manifests/status files are dirty, and every lane status describes unaccepted output. A duplicate root run would violate the serialization rule and would not satisfy the goal's acceptance requirement because it would not be tied to a frozen, reviewable snapshot.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; select exactly one owner-free reduced batch, preferably the Difftastic root-red `TokenDiffer::isDartLanguage()` fix or the Syncthing `syncthing_session_outbound_frames()` fix if still reproducible; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units; activate support rows only behind a real accepted gate or blocker; regenerate dashboard artifacts from the accepted commit; then commit or reject.
