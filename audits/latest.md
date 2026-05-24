# Independent Audit - 2026-05-24T21:06Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, recent
`audits/integration-status.md`, and recent Git history through
`6ff13fea Record Quadrable handoff rejection`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T21:03:00Z -> 2026-05-24T21:06:10Z
HEAD moved during audit: 8a77a848a732 -> 6ff13feac4ed
recent history: 6ff13fea Record Quadrable handoff rejection; 8a77a848 Refresh independent audit status; b5e907b5 Record markerPDF handoff rejection; b520d611 Refresh independent audit status; c4d28048 Record LightningCSS handoff rejection
tracked status rows before audit edits: 223 -> 223
default status rows including untracked before audit edits: 23538 -> 23544
dirty shortstat before audit edits: 223 files changed, 186707 insertions(+), 23317 deletions(-) -> 223 files changed, 186845 insertions(+), 23314 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred
exact root process gate: initially clear, then active no-argument root PID 1900048
owner evidence: 1900048 claude 1885256 Rs 00:22 php tools/run-tests.php
root run by this audit: not started because the exact no-argument root harness became active and HEAD/default status/shortstat moved during audit sampling
```

Live manifest/status samples from the dirty worktree, not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1177/1307                 3785/0
dolt          613/613                   466/0
esbuild       225/2567                  226/0
gitoxide      1475/2877                 7626/0
libsqlite     222/1589                  222/0
LightningCSS  3014/3548                 4393/0
markerPDF     167/78                    272/0
pandoc        2276/2276                 405/0
quadrable     55/55                     261/0
rclone        498/1601                  498/0
Readability   1628/1984                 150/0
syncthing     658/658                   9202/0
```

## Findings

1. **Critical - repo-wide acceptance is currently blocked by a real Difftastic root failure plus an active root harness.**
   - Paths: `audits/integration-status.md`,
     `lanes/difftastic/tests/TokenDifferTest.php`,
     `tools/run-tests.php`, `progress.md`.
   - Goal requirement at risk: periodically run repo-wide tests, record
     failures honestly, and commit small reviewable slices only with passing
     verification.
   - Evidence: the 2026-05-24 21:01 markerPDF intake ran the no-argument root
     harness and got `340` test files, `49106` assertions, and `1` failure in
     `lanes/difftastic/tests/TokenDifferTest.php`, where the SCSS mixin/nested
     rule alignment path expected `$css["@mixinbuttons"][0]/{0}[0]` but the
     dirty Difftastic output reported `$css["@mixinbuttons"]["button"][0]`.
     During this audit, the exact root gate later matched active no-argument
     PID `1900048 php tools/run-tests.php` owned by `claude`, so I did not
     start a duplicate run. Until that Difftastic failure is fixed or rejected
     and a frozen root run is green, no reduced lane handoff should be accepted
     as repo-wide progress.

2. **Critical - current lane status files still describe unaccepted worker handoffs, not integrated port progress.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`,
     `audits/integration-status.md`.
   - Goal requirement at risk: native PHP implementation slices must be
     integrated incrementally, reviewed, tested, and committed without counting
     dirty worker output as accepted progress.
   - Evidence: recent integration history is rejection-dominated:
     `6ff13fea` rejected Quadrable, `b5e907b5` rejected markerPDF, and
     `c4d28048` rejected LightningCSS. The lane statuses still use
     `pending`, `uncommitted`, root/integrator acceptance pending, or
     mixed-dirty-pile blocker wording. Near-complete percentages such as
     Difftastic 99, Dolt 99, Pandoc 99, Quadrable 99, and Syncthing 99 remain
     lane-local estimates, not accepted native/root parity.

3. **High - `porting.html` is a stale accepted-snapshot dashboard and materially disagrees with live metadata.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted
     upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit.
   - Evidence: `porting.html` and `porting-summary.json` still publish source
     snapshot `5e46840f9573`, generated `2026-05-24 20:23:24 UTC`, while live
     dirty metadata has advanced or changed units: Difftastic dashboard
     `240/586` versus live `1177/1307`; Gitoxide `1432/2877` versus
     `1475/2877`; LightningCSS `886/3532` versus `3014/3548`; Pandoc
     `619/2276` versus `2276/2276`; rclone denominator `2553` versus live
     `1601`; Syncthing PHP `324` versus live `9202`. The dashboard is only the
     last accepted snapshot, not current progress.

4. **High - support-library tracking remains backlog-only while current lanes cross rich-function dependency boundaries.**
   - Paths: `dependency-backlog.json`, `lanes/rclone/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/syncthing/lane-status.json`,
     `lanes/quadrable/lane-status.json`.
   - Goal requirement at risk: support libraries need the same lane-level
     rigor as base ports: bounded native component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and no
     whole-application or shell-out credit.
   - Evidence: `dependency-backlog.json` has 37 rows and `0` active support
     ports. Current lane-local work already touches inactive boundaries:
     rclone WebDAV properties while `webdav-protocol-core` is inactive;
     markerPDF ToUnicode/WinAnsi/searchable text while `pdf-text-dictionary-core`
     is inactive; Dolt/libsqlite JSON and SQL scalar semantics while
     `json-json5-document-core` and `sql-expression-semantics-core` are
     inactive; LightningCSS target fallback while `browser-compat-target-data-core`
     is inactive; Syncthing DeviceID protobuf while `protobuf-wire-core` is
     inactive; Quadrable proof marker transport while
     `quadrable-proof-transport-codec-core` is inactive. These can stay
     lane-local while unaccepted, but must not be counted as reusable
     rich-function progress without activating or explicitly declining the
     relevant bounded row.

5. **High - Syncthing global-discovery URL/query encoding is a missing support-row consumer.**
   - Paths: `dependency-backlog.json`, `lanes/syncthing/lane-status.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: missing bounded support rows become blockers
     once a base lane is ready for or blocked by the next essential rich
     capability.
   - Evidence: Syncthing status now claims global discovery lookup URL
     construction including Go query encoding, but `url-percent-encoding-core`
     lists `rclone`, `gitoxide`, `esbuild`, `lightningcss`, and `readability`
     only. If Syncthing's query encoding is intended as accepted reusable
     behavior, the support tracker must either add Syncthing to that row with a
     concrete gate and vector/spec expectations or keep the behavior explicitly
     lane-local and non-reusable.

6. **High - Pandoc rich conversion coverage is visible but overstated by the `2276/2276` mapped claim.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json`,
     `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion
     kernel with Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms, and
     WordPress block output, backed by meaningful upstream parity and explicit
     blockers.
   - Evidence: the Pandoc manifest reports `2276/2276` mapped, while lane
     status reports only `405` focused PHP behavior tests and the full Haskell
     runner remains unexecuted. The latest required rich areas are at least
     visible as gated rows: DOC/CFB, DOCX/OpenXML, PDF input and output
     handoff, EPUB, ODT/OpenDocument, templates, citations, math, tables,
     package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression. None has an accepted support
     manifest, PHP ledger, malformed/corrupt evidence, activation record, or
     bounded install-attempt note.

7. **High - markerPDF's denominator still cannot carry its current rich PDF breadth.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/markerpdf/src/PdfTextExtractor.php`,
     `lanes/markerpdf/src/BenchmarkRunner.php`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php`,
     `lanes/markerpdf/src/MarkerRuntimePlanner.php`,
     `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native
     PDF-to-structured-content extraction pipeline; external runtime planning
     and supplied converter/model callbacks cannot count as native conversion
     progress.
   - Evidence: the manifest maps `167` focused source/dependency semantics
     against only `78` tracked upstream paths and `0` committed Python unit
     tests. The current ToUnicode/WinAnsi simple-font searchable text work is
     useful native work, but it was rejected/deferred by root red. Broader PDF
     text/page/OCR/table claims remain behind inactive
     `pdf-text-dictionary-core`, `pdf-page-render-plan-core`,
     `layout-ocr-result-core`, and `table-geometry-core` rows. Benchmark,
     chunk, runtime, and supplied-converter planners remain quarantine
     boundaries unless native extraction is independently tested.

8. **Medium - manifest/status count units and prose are still internally inconsistent.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: progress must track upstream denominator,
     mapped tests, PHP pass/fail, phase, blocker, and commit in comparable
     units.
   - Evidence: markerPDF reports more mappings than denominator (`167/78`).
     Pandoc reports full mapped coverage (`2276/2276`) while PHP evidence is
     `405` behavior tests. Syncthing's `phpPass` is `9202` assertions while
     other lanes use behavior-test counts or selected-file ledgers. Gitoxide's
     `phpPass` is `7626`, also assertion-like rather than case-like. Percent
     estimates are not auditable until upstream artifacts, mapped behavior
     units, PHP assertions, PHP behavior cases, and accepted commit state are
     split explicitly.

9. **Medium - several lanes are still accumulated dirty piles, so focused green evidence does not identify an acceptable slice.**
   - Paths: `lanes/lightningcss/*`, `lanes/quadrable/*`,
     `lanes/readability/*`, `lanes/gitoxide/*`, `audits/integration-status.md`.
   - Goal requirement at risk: prefer small correct slices over broad shallow
     ports and commit small reviewable slices with passing tests.
   - Evidence: the latest Quadrable rejection records a stable but too-broad
     lane scope of `128` status rows and `42 files changed, 13796
     insertions(+), 2705 deletions(-)` plus numerous untracked examples,
     helpers, and tests, while the advertised `0x60` proof-marker slice is
     much smaller. LightningCSS was rejected for an accumulated `17` tracked
     files plus `203` untracked files. Readability status says five advertised
     slices are interleaved across the same tracked files. Gitoxide status says
     the WorkTreeGitDir slice cannot be isolated from the dirty worktree.

10. **Medium - bridge and shell-adjacent scaffolding still needs strict quarantine from progress credit.**
    - Paths: `lanes/difftastic/tests/TokenDifferTest.php`,
      `lanes/gitoxide/tests/GitIndexUntrackedCacheTest.php`,
      `lanes/gitoxide/tests/GitUrlTest.php`,
      `lanes/gitoxide/tests/GitIndexTest.php`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php`,
      `lanes/gitoxide/tests/FetchResponseTest.php`,
      `lanes/markerpdf/src/BenchmarkRunner.php`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php`,
      `lanes/markerpdf/src/MarkerRuntimePlanner.php`.
    - Goal requirement at risk: bridge code, generated fixtures, and
      shell-outs may exist only as temporary fixture-generation or oracle
      tooling and must not count as native implementation progress.
    - Evidence: `shell_exec()` and `proc_open()` oracle helpers remain present
      in Difftastic/Gitoxide test scaffolding, while markerPDF still uses
      supplied conversion/runtime planners as boundaries. Some of this is
      legitimate oracle or scenario tooling, but manifest/status progress must
      keep it out of native mapped/pass counts unless the native path is
      independently tested and the bridge role is explicit.

## Required Next Intervention

Stop accepting new lane behavior until the Difftastic SCSS root failure is
fixed or rejected with an explicit rollback/split decision. Freeze writers,
status publishers, and duplicate broad/focused test loops long enough for two
stable polls. Then accept or reject exactly one owner-free reduced lane batch
whose dirty files match its evidence, with normalized manifest/status count
units. If the accepted slice is ready for or blocked by a rich dependency,
activate or extend exactly one bounded support-library row with its own
upstream/spec denominator, mapped fixtures, PHP ledger, malformed/corrupt cases
where relevant, and bounded `sudo -n` install-attempt notes where missing
packages are claimed. Regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the accepted commit. Only after the exact
`pgrep -af '^php tools/run-tests\.php$'` gate is empty and the tree remains
frozen should one serialized no-argument `php tools/run-tests.php` run be used
as repo-wide acceptance evidence.
