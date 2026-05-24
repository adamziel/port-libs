# Independent Audit - 2026-05-24T21:26Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`,
recent `audits/integration-status.md`, and recent Git history through
`748bc929 Record markerPDF handoff rejection`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T21:23:53Z -> 2026-05-24T21:30:16Z
base HEAD moved during audit: 4c1945f49852 -> 96cb5683a606 -> 748bc92968c2 before this audit commit
recent history: 748bc929 Record markerPDF handoff rejection; 96cb5683 Record Syncthing handoff rejection; 4c1945f4 Refresh independent audit status; 890f9d22 Refresh dashboard for libsqlite JSON integration; 0fa9ecaf Record libsqlite JSON integration; d7d1528e Refresh independent audit status
tracked status rows: 214 -> 214 -> 217 -> 219
default status rows including untracked: 23651 -> 23653 -> 23658 -> 23660 -> 23680 -> 23695
dirty shortstat: 214 files changed, 186027 insertions(+), 23049 deletions(-) -> 214 files changed, 186028 insertions(+), 23049 deletions(-) -> 214 files changed, 186036 insertions(+), 23049 deletions(-) -> 217 files changed, 186488 insertions(+), 23147 deletions(-) -> 219 files changed, 186713 insertions(+), 23107 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred; every row still has upstreamDenominator null
exact root process gate: initially clear; stability sample later matched transient no-argument root PID 1961412 (`php tools/run-tests.php`); pre-commit gate matched active no-argument root PID 1968710; later final exact gate cleared
owner evidence: PID 1961412 exited before `ps` could sample it; PID 1968710 owner evidence was `1968710 claude 1939149 Rs 00:50 php tools/run-tests.php`
external root result observed in integration history: markerPDF intake root failed 342 files / 47521 assertions / 243 failures, first in Difftastic `TokenDiffer::isDartLanguage()`, later in Syncthing `syncthing_session_outbound_frames()`
root run by this audit: not started because the checkout moved during sampling; no duplicate was started while root PID 1968710 occupied the gate
```

Live manifest/status samples from the dirty worktree, not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail     commit/status
difftastic    1191/1307                 3803/0                     pending
dolt          613/613                   467/0                      not committed
esbuild       228/2567                  228/0                      uncommitted
gitoxide      1478/2877                 7639/0                     pending
libsqlite     223/1589                  223/0                      accepted 9784b10c only
LightningCSS  3024/3548                 4409/0                     uncommitted
markerPDF     169/78                    274/0                      pending; last accepted 5e46840f
pandoc        2276/2276                 406/0                      pending
quadrable     55/55                     261/0                      pending
rclone        505/1601                  505/0                      pending
Readability   1660/1984                 151/0                      uncommitted
syncthing     658/658                   9235/0                     rejected/deferred at 96cb5683
```

## Findings

1. **Critical - there is still no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `audits/integration-status.md`, `porting.html`,
     `porting-summary.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit
     small reviewable slices with passing verification from a stable snapshot.
   - Evidence: during this audit the base moved from `4c1945f49852` through
     `96cb5683a606` to `748bc92968c2`, untracked-inclusive status rows moved
     from `23651` to `23695`, and dirty shortstat moved from `214 files
     changed, 186027 insertions(+), 23049 deletions(-)` to `219 files
     changed, 186713 insertions(+), 23107 deletions(-)`. The exact root gate
     was initially clear, then matched transient root PID `1961412 php
     tools/run-tests.php`, and a later pre-commit gate matched active root PID
     `1968710` owned by `claude`. The newest integration-owned root result
     failed `342` files / `47521` assertions / `243` failures. A root run
     started from this audit would either duplicate an active root harness, be
     tied to a moving tree, or retest known red aggregate state.

2. **Critical - the newest integration decisions reject, rather than accept, markerPDF and Syncthing handoffs.**
   - Paths: `audits/integration-status.md`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/difftastic/tests/TokenDifferTest.php`,
     `lanes/syncthing/tests/BepSessionTest.php`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/lane-status.json`, `lanes/syncthing/*`.
   - Goal requirement at risk: each lane must advance in small, reviewable,
     native PHP slices with focused upstream evidence and committed tests.
   - Evidence: `748bc929` records a coherent markerPDF lane-local
     searchable-PDF font/text handoff with green focused evidence, but the
     required serialized root harness failed outside markerPDF: Difftastic
     first on `Call to undefined method
     PortLibs\Difftastic\TokenDiffer::isDartLanguage()`, and later Syncthing
     on `Call to undefined function syncthing_session_outbound_frames()`.
     `96cb5683` records that the advertised Syncthing BEP Hello slice had
     focused green evidence, but the actual Syncthing dirty scope had `272`
     lane status rows, `29 files changed, 13445 insertions(+), 1048
     deletions(-)`, and many unrelated untracked artifacts. Neither handoff
     was integrated; no dashboard artifacts were regenerated and no support row
     was activated.

3. **Critical - live lane status still describes unaccepted worker output, not integrated port progress.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: dirty worker handoffs must not count as accepted
     native implementation progress.
   - Evidence: every primary lane except the accepted libsqlite slice has
     `pending`, `uncommitted`, `not committed`, or root/integrator acceptance
     pending in `latestCommit` or `blocker`. Recent history remains dominated
     by audit/status/rejection commits, including Syncthing at `96cb5683`,
     Quadrable at `6ff13fea`, markerPDF at `b5e907b5`, LightningCSS at
     `c4d28048`, and Gitoxide at `6bc3d986`.

4. **High - `porting.html` and `porting-summary.json` are stale relative to live manifests and current `HEAD`.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted
     upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit.
   - Evidence: `porting.html` publishes `Snapshot: main 0fa9ecafcd10` and
     older rows such as Difftastic `240/586`, Pandoc `619/2276`, rclone
     `458/2553`, and Syncthing `324/658`. Live dirty manifests now report
     Difftastic `1191/1307`, Pandoc `2276/2276`, rclone `505/1601`, and
     Syncthing `658/658`. The dashboard is a historical accepted snapshot plus
     stale metadata, not a current accepted status page.

5. **High - support-library tracking is visible but still not first-class coverage.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: support libraries require the same granularity
     as lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec-suite evidence as can actually run.
   - Evidence: all `37` dependency rows are backlog-only (`0` active); every
     row still has `upstreamDenominator: null`. The rows name the important
     rich-function libraries, but none is an accepted support-library port.

6. **High - Pandoc rich conversion remains overstated by the `2276/2276` mapped claim.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json`,
     `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion
     kernel with Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms, and
     WordPress block output backed by meaningful upstream parity and explicit
     blockers.
   - Evidence: the manifest reports `2276/2276` mapped while lane status shows
     `406` focused PHP behavior tests and the full Haskell runner remains
     unexecuted. Required Pandoc rich areas are visible as gated rows or reuse
     paths: DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, citations, math, tables, templates, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression. None has an accepted support manifest, PHP ledger,
     malformed/corrupt evidence, activation record, or bounded install-attempt
     note.

7. **High - active rich lane work crosses inactive support boundaries.**
   - Paths: `dependency-backlog.json`, `lanes/markerpdf/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/syncthing/lane-status.json`,
     `lanes/quadrable/lane-status.json`.
   - Goal requirement at risk: rich-function dependency work must not be
     counted as reusable support progress unless the bounded row is activated,
     tested, and evidenced.
   - Evidence: markerPDF is expanding PDF text/CMap/simple-font extraction
     while `pdf-text-dictionary-core` is inactive; rclone is mapping WebDAV
     XML/LOCK behavior while `webdav-protocol-core` and `xml-html5-dom-core`
     are inactive; Dolt/libsqlite JSON and SQL semantics sit ahead of
     `json-json5-document-core` and `sql-expression-semantics-core`;
     Syncthing BEP/DeviceID behavior sits ahead of `protobuf-wire-core`;
     Quadrable proof/sync transport sits ahead of
     `quadrable-proof-transport-codec-core`.

8. **High - markerPDF still has a weak denominator for its claimed PDF breadth.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/markerpdf/src/PdfTextExtractor.php`,
     `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native
     PDF-to-structured-content extraction pipeline; external runtime planning,
     supplied converter/model callbacks, and benchmark scaffolding cannot
     count as native conversion progress.
   - Evidence: the manifest maps `169` focused semantics against only `78`
     tracked upstream paths and `0` committed Python unit-test files. The
     native text work may be useful, but the current searchable-PDF
     font/text handoff was rejected because aggregate root is red outside the
     lane, and broader page/OCR/table claims remain gated by inactive support
     rows.

9. **Medium - manifest/status count units remain inconsistent across lanes.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: progress must track upstream denominator,
     mapped tests, PHP pass/fail, phase, blocker, and commit in comparable
     units.
   - Evidence: markerPDF maps more items than its denominator (`169/78`).
     Pandoc maps the whole denominator (`2276/2276`) while reporting `406`
     PHP behavior tests. Syncthing and Gitoxide `phpPass` values (`9235`,
     `7639`) are assertion-like, while Dolt/rclone/readability use
     behavior-case counts. Dashboard commit cells still contain truncated or
     non-commit prose such as `port-es`, `Port rc`, and `uncommi`.

10. **Medium - Syncthing URL/query encoding still lacks a reusable support route.**
    - Paths: `dependency-backlog.json`,
      `lanes/syncthing/lane-status.json`,
      `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: a missing bounded support row is a blocker once
      a base lane is ready for or blocked by the next essential rich
      capability.
    - Evidence: Syncthing status continues to claim global discovery lookup URL
      construction and Go-style query encoding, but
      `url-percent-encoding-core` lists `rclone`, `gitoxide`, `esbuild`,
      `lightningcss`, and `readability` only. Either add Syncthing as a gated
      consumer with spec/vector expectations or keep the evidence explicitly
      lane-local and non-reusable.

11. **Medium - several lanes remain broad dirty piles, so focused green evidence does not isolate an acceptable slice.**
    - Paths: `lanes/gitoxide/*`, `lanes/lightningcss/*`,
      `lanes/quadrable/*`, `lanes/readability/*`,
      `lanes/syncthing/*`, `audits/integration-status.md`.
    - Goal requirement at risk: prefer small correct slices over broad shallow
      ports and commit small reviewable slices with passing tests.
    - Evidence: Gitoxide says WorkTreeGitDir cannot be isolated from earlier
      unaccepted discovery files; Quadrable lists proof transport, noTrack,
      iterator/checkpoint, raw-LMDB, sync, and more in one dirty lane;
      Readability says six advertised slices are interleaved across the same
      tracked files; Syncthing was rejected for exactly this scope mismatch at
      `96cb5683`.

12. **Medium - bridge and shell-adjacent scaffolding still needs strict quarantine from progress credit.**
    - Paths: `lanes/difftastic/tests/TokenDifferTest.php`,
      `lanes/gitoxide/tests/*`, `lanes/markerpdf/src/BenchmarkRunner.php`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php`,
      `lanes/markerpdf/src/MarkerRuntimePlanner.php`.
    - Goal requirement at risk: bridge code, generated fixtures, and
      shell-outs may exist only as temporary fixture-generation or oracle
      tooling and must not count as native implementation progress.
    - Evidence: Difftastic/Gitoxide retain oracle helpers, and markerPDF still
      includes supplied conversion/runtime planner boundaries. These may be
      legitimate tests or preflight tools, but manifest/status counts must keep
      them out of native mapped/pass totals unless the native path is
      independently tested and the oracle role is explicit.

## Required Next Intervention

Freeze writers, status publishers, dashboard regeneration, and duplicate
focused/root loops until two consecutive polls show unchanged `HEAD`, tracked
status, default status, shortstat, and exact root gate. Treat `9784b10c` as
accepted libsqlite-only evidence, not a dirty-tree baseline; treat `96cb5683`
as a Syncthing rejection; and treat `748bc929` as a markerPDF rejection blocked
by aggregate Difftastic/Syncthing root failures. The next concrete intervention
is to fix or reject the root-red Difftastic `TokenDiffer::isDartLanguage()`
gap and the Syncthing `syncthing_session_outbound_frames()` gap before trying
to accept markerPDF again. Then accept or reject exactly one owner-free reduced
lane batch whose dirty files match its evidence, normalize manifest/status
count units, and regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from that same accepted commit. If the accepted slice is
ready for or blocked by a rich dependency, activate or extend exactly one
bounded support-library row with a dependency-specific upstream/spec
denominator, mapped fixtures, PHP ledger, malformed/corrupt cases where
relevant, and bounded `sudo -n` install-attempt notes where missing packages
are claimed. Only after `pgrep -af '^php tools/run-tests\.php$'` is empty and
the tree remains frozen should one serialized no-argument
`php tools/run-tests.php` run be used as repo-wide acceptance evidence.
