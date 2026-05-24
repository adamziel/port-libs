# Independent Audit - 2026-05-24T21:11Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`,
recent `audits/integration-status.md`, and recent Git history through
`995e2e35 Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T21:11:02Z -> 2026-05-24T21:11:32Z
HEAD: 995e2e354bfa
recent history: 995e2e35 Refresh independent audit status; 6ff13fea Record Quadrable handoff rejection; 8a77a848 Refresh independent audit status; b5e907b5 Record markerPDF handoff rejection; c4d28048 Record LightningCSS handoff rejection
tracked status rows before audit edits: 223 -> 223
default status rows including untracked before audit edits: 23577 -> 23580
dirty shortstat before audit edits: 223 files changed, 187480 insertions(+), 23468 deletions(-) -> 223 files changed, 187481 insertions(+), 23468 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred
exact root process gate: initially clear, then active no-argument root PID 1916200
owner evidence: 1916200 claude 1904149 Rs 00:24 php tools/run-tests.php
root run by this audit: not started because the exact no-argument root harness became active and the default-status/shortstat moved during sampling
```

Live manifest/status samples from the dirty worktree, not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1177/1307                 3785/0
dolt          613/613                   466/0
esbuild       225/2567                  227/0
gitoxide      1477/2877                 7634/0
libsqlite     223/1589                  223/0
LightningCSS  3017/3548                 4400/0
markerPDF     168/78                    273/0
pandoc        2276/2276                 405/0
quadrable     55/55                     261/0
rclone        502/1601                  502/0
Readability   1645/1984                 151/0
syncthing     658/658                   9202/0
```

## Findings

1. **Critical - repo-wide acceptance is still blocked; the exact root harness is already running and the last known integration-owned root evidence was red.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md`,
     `lanes/difftastic/lane-status.json`,
     `lanes/difftastic/tests/TokenDifferTest.php`, `progress.md`.
   - Goal requirement at risk: periodically run repo-wide tests, record
     failures honestly, and commit small reviewable slices only with passing
     verification.
   - Evidence: the 2026-05-24 21:01 markerPDF intake root run failed in
     `lanes/difftastic/tests/TokenDifferTest.php` on SCSS mixin/nested-rule
     alignment. Difftastic now reports a focused local fix with `3785`
     assertions and `0` failures, but there is no accepted frozen no-argument
     root result for that repair. During this audit the required exact gate
     moved from clear to active root PID `1916200 php tools/run-tests.php`,
     owned by `claude`, so I did not start a duplicate.

2. **Critical - the live lane status files describe unaccepted worker output, not integrated native port progress.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`,
     `audits/integration-status.md`.
   - Goal requirement at risk: native PHP slices must be integrated
     incrementally, reviewed, tested, and committed; dirty worker handoffs must
     not count as accepted progress.
   - Evidence: current lane statuses are full of `pending`, `uncommitted`, and
     root/integrator acceptance blockers. Recent history is rejection-heavy:
     Quadrable at `6ff13fea`, markerPDF at `b5e907b5`, LightningCSS at
     `c4d28048`, Gitoxide at `6bc3d986`, and Syncthing at `2944e876` and
     `6bd136e9`. The live worktree still has `223` tracked dirty rows and more
     than `23500` untracked-inclusive status rows.

3. **High - support-library tracking is visible but not yet first-class coverage.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: support libraries require the same granularity
     as lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec-suite evidence as can actually run.
   - Evidence: `dependency-backlog.json` has `37` rows but `0` active support
     ports, and every sampled support row has `upstreamDenominator: null`.
     Important rich-function rows exist for Gitoxide (`git-wire-protocol-core`),
     LightningCSS/esbuild (`browser-compat-target-data-core`,
     `source-map-v3-core`, `js-package-resolution-core`), markerPDF/Pandoc
     (`pdf-text-dictionary-core`, `pdf-page-render-plan-core`,
     `layout-ocr-result-core`, `table-geometry-core`), libsqlite/Dolt
     (`json-json5-document-core`, `sql-expression-semantics-core`,
     `sql-storage-codec-core`), Readability (`xml-html5-dom-core`,
     `url-percent-encoding-core`, `unicode-text-repair-width`,
     `charset-encoding-core`), Quadrable (`quadrable-proof-transport-codec-core`,
     `sequence-diff-merge-core`), Syncthing (`protobuf-wire-core`,
     `qr-code-matrix-core`), Difftastic (`tree-sitter-grammar-subset`), and
     rclone (`webdav-protocol-core`, `provider-metadata-normalization-core`).
     These are backlog declarations, not accepted dependency ports.

4. **High - current lane-local rich behavior crosses inactive support boundaries.**
   - Paths: `dependency-backlog.json`, `lanes/rclone/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/syncthing/lane-status.json`,
     `lanes/quadrable/lane-status.json`.
   - Goal requirement at risk: rich-function dependency work must not be
     counted as reusable support progress unless the bounded row is activated,
     tested, and evidenced.
   - Evidence: rclone is mapping WebDAV PROPFIND/PROPPATCH XML while
     `webdav-protocol-core` and `xml-html5-dom-core` remain inactive;
     markerPDF is adding ToUnicode/WinAnsi/Differences searchable-PDF text
     behavior while `pdf-text-dictionary-core` is inactive; Dolt/libsqlite are
     expanding JSON and SQL scalar semantics while `json-json5-document-core`
     and `sql-expression-semantics-core` are inactive; LightningCSS is adding
     supports/color target behavior while `browser-compat-target-data-core`
     is deferred; Syncthing is adding DeviceID protobuf behavior while
     `protobuf-wire-core` is inactive; Quadrable is adding proof transport
     markers while `quadrable-proof-transport-codec-core` is inactive. These
     can remain lane-local while unaccepted, but they must not be credited as
     shared support-library progress.

5. **High - Pandoc rich conversion remains overstated by the `2276/2276` mapped claim.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json`,
     `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion
     kernel with Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms, and
     WordPress block output, backed by meaningful upstream parity and explicit
     blockers.
   - Evidence: the manifest reports `2276/2276` mapped, but lane status shows
     only `405` focused PHP behavior tests and the full Haskell runner remains
     unexecuted. Required rich areas are visible as rows or reuse paths: DOC,
     DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     citations, math, tables, templates, package containers, XML/HTML,
     Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression. None has an accepted support manifest, PHP ledger,
     malformed/corrupt evidence, activation record, or bounded install-attempt
     note.

6. **High - `porting.html` and `porting-summary.json` are stale accepted-snapshot artifacts and materially disagree with live metadata.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted
     upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit.
   - Evidence: the dashboard publishes source snapshot `5e46840f9573`,
     generated `2026-05-24 20:23:24 UTC`. Live dirty metadata has moved to
     Difftastic `1177/1307`, Gitoxide `1477/2877`, LightningCSS `3017/3548`,
     markerPDF `168/78`, rclone `502/1601`, and Readability `1645/1984`,
     while the dashboard still shows older accepted counts such as Difftastic
     `240/586`, Gitoxide `1432/2877`, LightningCSS `886/3532`, markerPDF
     `165/78`, rclone `458/2553`, and Readability `1563/1984`. It is useful
     as the last accepted snapshot, not as current progress.

7. **High - markerPDF's denominator is too weak for its current rich PDF breadth.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/markerpdf/src/PdfTextExtractor.php`,
     `lanes/markerpdf/src/BenchmarkRunner.php`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php`,
     `lanes/markerpdf/src/MarkerRuntimePlanner.php`,
     `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native
     PDF-to-structured-content extraction pipeline; external runtime planning,
     supplied converter/model callbacks, and benchmark scaffolding cannot
     count as native conversion progress.
   - Evidence: the manifest maps `168` focused semantics against only `78`
     tracked upstream paths and `0` committed Python unit tests. The current
     searchable PDF work is promising native text extraction, but the lane is
     still pending root acceptance and broader PDF text/page/OCR/table claims
     remain gated by inactive support rows. Planner classes and supplied
     converter/model boundaries must stay quarantined from native mapped/pass
     counts unless independently tested through native extraction.

8. **Medium - manifest/status count units are still inconsistent across lanes.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: progress must track upstream denominator,
     mapped tests, PHP pass/fail, phase, blocker, and commit in comparable
     units.
   - Evidence: markerPDF maps more items than its denominator (`168/78`).
     Pandoc reports full mapped coverage (`2276/2276`) while PHP evidence is
     `405` behavior tests. Syncthing and Gitoxide status `phpPass` values
     (`9202`, `7634`) are assertion-like while Dolt/rclone/readability use
     behavior-case counts. The dashboard truncates or stale-publishes several
     commit values (`current`, `pending`, `uncommi`, `Port rc`). Percentages
     are not auditable until upstream artifacts, mapped behavior units, PHP
     assertions, PHP behavior cases, and accepted commit state are separated.

9. **Medium - Syncthing URL/query encoding is still not routed through the URL support row.**
   - Paths: `dependency-backlog.json`,
     `lanes/syncthing/lane-status.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: a missing bounded support row is a blocker once
     a base lane is ready for or blocked by the next essential rich capability.
   - Evidence: Syncthing status claims global discovery lookup URL construction
     and Go-style query encoding, but `url-percent-encoding-core` lists
     `rclone`, `gitoxide`, `esbuild`, `lightningcss`, and `readability` only.
     If this behavior is meant to be reusable support-library coverage, add
     Syncthing to that row with a concrete gate and vector/spec expectations;
     otherwise keep the evidence explicitly lane-local and non-reusable.

10. **Medium - several lanes remain accumulated dirty piles, so focused green evidence does not isolate an acceptable slice.**
    - Paths: `lanes/lightningcss/*`, `lanes/quadrable/*`,
      `lanes/readability/*`, `lanes/gitoxide/*`,
      `audits/integration-status.md`.
    - Goal requirement at risk: prefer small correct slices over broad shallow
      ports and commit small reviewable slices with passing tests.
    - Evidence: recent integration status records Quadrable as `42` tracked
      dirty files plus many untracked files, LightningCSS as `17` tracked files
      plus `203` untracked files, Readability as five interleaved advertised
      slices across the same tracked files, and Gitoxide's WorkTreeGitDir slice
      as inseparable from earlier unaccepted discovery files. Focused tests can
      be green while the patch is still too broad to accept honestly.

11. **Medium - bridge and shell-adjacent scaffolding still needs strict quarantine from progress credit.**
    - Paths: `lanes/difftastic/tests/TokenDifferTest.php`,
      `lanes/gitoxide/tests/GitIndexUntrackedCacheTest.php`,
      `lanes/gitoxide/tests/GitUrlTest.php`,
      `lanes/gitoxide/tests/GitIndexTest.php`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php`,
      `lanes/gitoxide/tests/FetchResponseTest.php`,
      `lanes/markerpdf/src/BenchmarkRunner.php`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php`,
      `lanes/markerpdf/src/MarkerRuntimePlanner.php`.
    - Goal requirement at risk: bridge code, generated fixtures, and shell-outs
      may exist only as temporary fixture-generation or oracle tooling and must
      not count as native implementation progress.
    - Evidence: `shell_exec()` and `proc_open()` oracle helpers remain present
      in Difftastic/Gitoxide test scaffolding, and markerPDF still uses
      supplied conversion/runtime planners. Some of this is legitimate oracle
      or scenario tooling, but manifests and lane statuses must keep it out of
      native mapped/pass counts unless the native path is independently tested
      and the oracle role is explicit.

## Required Next Intervention

Do not accept more lane behavior until the active no-argument root run
finishes and Difftastic's SCSS repair has either a green frozen root result or
an explicit rejection/rollback decision. Freeze writers, status publishers,
and duplicate broad/focused test loops long enough for two stable polls. Then
accept or reject exactly one owner-free reduced lane batch whose dirty files
match its evidence, with normalized manifest/status count units. If the
accepted slice is ready for or blocked by a rich dependency, activate or extend
exactly one bounded support-library row with its own upstream/spec denominator,
mapped fixtures, PHP ledger, malformed/corrupt cases where relevant, and
bounded `sudo -n` install-attempt notes where missing packages are claimed.
Regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
accepted commit. Only after `pgrep -af '^php tools/run-tests\.php$'` is empty
and the tree remains frozen should one serialized no-argument
`php tools/run-tests.php` run be used as repo-wide acceptance evidence.
