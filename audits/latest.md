# Independent Audit - 2026-05-24T22:20Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `45aa82fbaadf`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T22:20Z
HEAD: 45aa82fbaadf
recent history: 45aa82fb Refresh independent audit status; cd6a6b69 Refresh independent audit status; 46b6e962 Record Gitoxide handoff rejection; 30f1c09c Refresh independent audit status; 39562f51 Record Syncthing handoff rejection; 29b4eecb Record Pandoc handoff rejection
default status rows including untracked: 24623
tracked dirty rows: 229
dirty shortstat: 229 files changed, 190040 insertions(+), 22870 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty
exact root process gate: `pgrep -af '^php tools/run-tests\.php$'` returned no rows
root run by this audit: not started; the checkout moved during sampling and is still too dirty for a meaningful audit-owned aggregate run
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred; Pandoc-related rows still have null dependency denominators and no PHP pass/fail ledgers
```

Live dirty manifest/status samples are not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail     latest status
difftastic    1245/1356                 3873/0                     pending Gleam slice
dolt          613/613                   471/0                      not committed REGEXP_INSTR/query-diff stack
esbuild       235/2567                  235/0                      uncommitted package resolver/browser-map slice
gitoxide      1481/2877                 7678/0                     pending discover/mailmap/actor stack
libsqlite     234/1589                  234/0                      pending JSON aggregate/table/patch handoff
LightningCSS  3029/3548                 4420/0                     uncommitted charset formatter slice
markerPDF     172/78                    not sampled cleanly        pending ASCII85/RunLength stream-filter pile
pandoc        2276/2276                 410/0                      pending HTML reader standalone del slice
quadrable     115/115                   263/0                      pending docopt/submodule denominator plus broad dirty stack
rclone        518/1601                  518/0                      pending WebDAV PROPPATCH lock-integration slice
Readability   1720/1984                 156/0                      uncommitted mixed fixture/cleanup pile
syncthing     658/658                   9270/0                     pending Windows native-model/BEP slice
```

## Findings

1. **Critical - the repository still has no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit small, reviewable slices with passing verification from a stable snapshot.
   - Evidence: `HEAD` moved during this audit from `cd6a6b6997a2` to `45aa82fbaadf`; default status rows remain `24623`; tracked dirty rows are `229`; dirty shortstat is `229 files changed, 190040 insertions(+), 22870 deletions(-)`. All 12 lane manifests and all 12 lane-status files are dirty. A root test from this state would test a mixed moving pile, not an accept/reject batch.

2. **Critical - every live lane status is still unaccepted worker output.**
   - Paths: `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: dirty worker handoffs must not count as native implementation progress until supervisor/integrator acceptance and verification occur.
   - Evidence: each lane status says pending, uncommitted, not committed, or root/integrator acceptance pending. Recent history is audit/rejection/status-heavy rather than accepted implementation-heavy: `45aa82fb`, `cd6a6b69`, `46b6e962`, `30f1c09c`, `39562f51`, and `29b4eecb`.

3. **Critical - root harness evidence remains unusable for new acceptance even though the current exact process gate is empty.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md`, `progress.md`.
   - Goal requirement at risk: repo-wide root verification must be serialized and tied to the same frozen snapshot being accepted.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` returned no rows, but the checkout moved during sampling and all lane manifests/status files are dirty. Prior integration/audit records still identify root-red Difftastic `TokenDiffer::isDartLanguage()` and Syncthing `syncthing_session_outbound_frames()` blockers. I did not start a no-argument root harness.

4. **High - `porting.html` is stale relative to live metadata and should not be published as current.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit.
   - Evidence: `porting.html` still says verified source snapshot `0fa9ecafcd10` generated at `2026-05-24 21:19:36 UTC`, while the live dirty checkout is at `45aa82fbaadf` and lane metadata has moved again, including Difftastic `1245/1356`, markerPDF `172/78`, Pandoc `2276/2276`, Readability `1720/1984`, and Syncthing `658/658`.

5. **High - support-library rows are visible but still not first-class support ports.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require the same granularity as lanes: bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has `37` rows, `0` active rows, and Pandoc-related rows still show null `upstreamDenominator`, `phpPass`, and `phpFail`. These are routing notes, not accepted support-library progress. Missing packages are not final blockers until bounded `sudo -n` installs are attempted or ruled out, but no row currently records that runner ledger.

6. **High - Pandoc rich-format coverage remains over-credited.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`, `dependency-backlog.json`, `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion kernel with meaningful readers/writers and upstream-backed rich-format support, not just fixture routing.
   - Evidence: the manifest reports `2276/2276` mapped while lane status reports only `410` PHP behavior tests and full Haskell runner parity remains unexecuted. DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, templates, citations, math, tables, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression are visible as gated backlog rows or reuse paths, but none is an active bounded support port with a denominator or pass/fail ledger.

7. **High - markerPDF still maps more semantics than its upstream denominator and mixes dependency planning with native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline; external runtime planning, supplied converter/model callbacks, benchmark archive probing, and dependency inspection cannot count as native conversion progress.
   - Evidence: markerPDF now maps `172` semantics against only `78` upstream paths. The stream-filter slices are narrow, but the manifest still co-locates Streamlit/FastAPI/converter/model/runtime planning and benchmark/archive inspection with native PDF stream extraction. PDF text dictionaries, page/layout planning, OCR/layout results, table geometry, Unicode/charset, and archive/package concerns remain inactive support rows.

8. **High - base lanes are crossing inactive support-library boundaries without activating shared rows.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: rich dependency work should not count as shared reusable progress unless a bounded row is activated, tested, and evidenced.
   - Evidence: esbuild package exports/browser-map work is ahead of deferred `js-package-resolution-core`; rclone WebDAV PROPPATCH/LOCK/XML work is ahead of inactive `webdav-protocol-core` and `xml-html5-dom-core`; Dolt and libsqlite JSON work is ahead of inactive `json-json5-document-core` and `sql-expression-semantics-core`; Syncthing BEP/session and URL-query work is ahead of inactive `protobuf-wire-core` and missing URL consumer routing; Gitoxide protocol/discovery work remains ahead of inactive `git-wire-protocol-core` and URL support rows.

9. **Medium - manifest/status count units remain non-comparable.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: progress percentages must be comparable and tied to upstream denominator, mapped tests, PHP pass/fail, phase, blocker, and commit.
   - Evidence: markerPDF reports `172/78`; Pandoc reports `2276/2276` while status has `410` PHP behavior tests; Syncthing and Gitoxide `phpPass` are assertion-like counts (`9270`, `7678`); rclone uses behavior tests (`518`) and assertions in prose; Quadrable counts upstream-plus-submodule paths (`115/115`) rather than only behavior scenarios.

10. **Medium - Quadrable's denominator changed from behavior scenarios to path inventory without a dashboard-level unit warning.**
    - Paths: `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`, `lanes/quadrable/lane-status.json`, `porting.html`.
    - Goal requirement at risk: each lane needs a real upstream denominator and mapped tests in comparable units.
    - Evidence: the manifest reports `115/115` with upstream-plus-submodule path inventory, while the earlier meaningful suite story was the 34 upstream `check.cpp` scenarios plus proof/sync subcases. Counting initialized submodule files as fully mapped can inflate progress unless the dashboard separates path inventory from behavior parity.

11. **Medium - broad dirty piles are blocking reviewable-slice acceptance.**
    - Paths: `lanes/readability/lane-status.json`, `lanes/quadrable/lane-status.json`, `lanes/dolt/lane-status.json`, `lanes/gitoxide/lane-status.json`, `lanes/syncthing/lane-status.json`.
    - Goal requirement at risk: prefer small correct slices over broad shallow ports and commit small reviewable slices with passing tests.
    - Evidence: Readability advertises ten interleaved fixture/cleanup slices; Quadrable lists many unrelated unaccepted proof/transport/store/helper slices; Dolt mixes a long query-diff expression stack; Gitoxide mixes discover, mailmap, and actor handoffs; Syncthing has a large route, GUI, discovery, BEP, native-model, and URL/query history in lane metadata.

12. **Medium - Syncthing URL/query needs are still not routed through the shared URL row.**
    - Paths: `dependency-backlog.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/lane-status.json`.
    - Goal requirement at risk: missing bounded support rows become blockers once the base lane is ready for or blocked by the next essential rich capability.
    - Evidence: Syncthing status includes discovery lookup URL construction and query encoding, but `url-percent-encoding-core` lists `rclone`, `gitoxide`, `esbuild`, `lightningcss`, and `readability` only. Either add Syncthing as a gated consumer with spec/vector expectations, or keep that URL evidence explicitly lane-local and non-reusable.

## Root Harness Decision

The exact no-argument root gate was clear, but I did not run `php tools/run-tests.php`. The tree is not stable enough: `HEAD` moved during sampling, all lane manifests/status files are dirty, and every lane status describes unaccepted output. A root run would not satisfy the goal's acceptance requirement because it would not be tied to a frozen, reviewable snapshot.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; select exactly one owner-free reduced batch, preferably the Difftastic root-red `TokenDiffer::isDartLanguage()` fix or the Syncthing `syncthing_session_outbound_frames()` fix if still reproducible; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units; activate support rows only behind a real accepted gate or blocker; regenerate dashboard artifacts from the accepted commit; then commit or reject.
