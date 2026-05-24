# Independent Audit - 2026-05-24T20:58Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, recent
`audits/integration-status.md`, and recent Git history through
`b520d611 Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T20:58:10Z -> 2026-05-24T21:01:32Z
HEAD: b520d6114305
recent history: b520d611 Refresh independent audit status; c4d28048 Record LightningCSS handoff rejection; 61762309 Refresh independent audit status; 7a578dd1 Record Quadrable handoff rejection; 6bc3d986 Record Gitoxide handoff rejection
tracked status rows before audit edits: 221 -> 221
default status rows including untracked before audit edits: 23369 -> 23369
dirty shortstat before audit edits: 221 files changed, 186052 insertions(+), 23298 deletions(-) -> 221 files changed, 186052 insertions(+), 23298 deletions(-)
final precommit worktree sample after audit edits: 226 tracked rows, 23468 untracked-inclusive rows, 226 files changed, 186547 insertions(+), 23432 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred
exact root process gate: active no-argument root PID 1851359 at 20:58; no exact root match at 21:01
owner evidence: 1851359 claude 1815492 Rs 00:39 php tools/run-tests.php
focused shards observed separately: 1853361 claude 1853299 R+ 00:17 php tools/run-tests.php lanes/quadrable/tests; later 1868572 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
root run by this audit: not started because the exact no-argument root harness was already active at the audit gate, and the checkout moved again before finish
```

Live manifest/status samples from the dirty worktree, not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1177/1307                 3777/0
dolt          613/613                   466/0
esbuild       225/2567                  225/0
gitoxide      1475/2877                 7626/0
libsqlite     222/1589                  222/0
LightningCSS  3014/3548                 4393/0
markerPDF     167/78                    272/0
pandoc        2276/2276                 405/0
quadrable     55/55                     260/0
rclone        498/1601                  498/0
Readability   1609/1984                 149/0
syncthing     658/658                   9190/0
```

## Findings

1. **Critical - repo-wide acceptance evidence is still blocked by an active root run over a dirty aggregate.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests, record
     failures honestly, and commit only small reviewable slices with passing
     verification.
   - Evidence: the required exact gate matched active no-argument root PID
     `1851359 php tools/run-tests.php` owned by `claude`; I therefore did not
     start a duplicate. A focused Quadrable shard (`1853361 php
     tools/run-tests.php lanes/quadrable/tests`) was also active but is not a
     duplicate root harness. By the final precommit sample the exact root gate
     had cleared, but the checkout had moved from `221` tracked dirty rows and
     `23369` untracked-inclusive status rows before audit edits to `226` and
     `23468`, with a focused Syncthing shard active. Any root result from this
     moving aggregate must be treated as slice-scoped unless the integrator can
     prove the tested snapshot was frozen and exactly matches one accepted
     batch.

2. **Critical - current lane statuses remain unaccepted handoffs, not integrated port progress.**
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
   - Evidence: every sampled lane status still uses `pending`,
     `uncommitted`, or supervisor/integrator/root-acceptance-pending wording in
     `latestCommit` or `blocker`. Recent history reinforces this: Gitoxide,
     Quadrable, Syncthing, and LightningCSS handoffs were recorded as
     rejected/deferred in recent integration commits (`6bc3d986`,
     `7a578dd1`, `2944e876`, `c4d28048`). Near-complete estimates such as
     Difftastic 99, Dolt 99, Pandoc 99, Quadrable 99, and Syncthing 99 should
     not be read as accepted native/root parity.

3. **High - `porting.html` is a stale accepted-snapshot dashboard and materially disagrees with live metadata.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted
     upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit.
   - Evidence: `porting.html` and `porting-summary.json` still publish source
     snapshot `5e46840f9573`, generated `2026-05-24 20:23:24 UTC`. Live dirty
     metadata is far ahead or has changed units: Difftastic dashboard
     `240/586` versus live `1177/1307`; Dolt `303/613` versus `613/613`;
     Gitoxide `1432/2877` versus `1475/2877`; LightningCSS `886/3532` versus
     `3014/3548`; Pandoc `619/2276` versus `2276/2276`; rclone denominator
     `2553` versus live `1601`; Syncthing PHP `324` versus live `9190`. The
     dashboard is useful only as the last accepted snapshot, not as current
     progress.

4. **High - support-library tracking remains backlog-only while current lanes cross rich-function dependency boundaries.**
   - Paths: `dependency-backlog.json`, `lanes/rclone/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: support libraries need the same lane-level
     rigor as base ports: bounded native component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and no
     whole-application or shell-out credit.
   - Evidence: `dependency-backlog.json` has 37 rows and `0` active support
     ports. Current lane-local work already touches these boundaries: rclone
     WebDAV mutation/property behavior while `webdav-protocol-core` is
     inactive; markerPDF searchable text/ToUnicode/WinAnsi while
     `pdf-text-dictionary-core` is inactive; Dolt/libsqlite JSON and SQL
     scalar semantics while `json-json5-document-core` and
     `sql-expression-semantics-core` are inactive; LightningCSS target-aware
     fallback behavior while `browser-compat-target-data-core` is inactive;
     Syncthing global-discovery query encoding while `url-percent-encoding-core`
     does not list Syncthing as a consumer. These can stay lane-local while
     unaccepted, but they should not be accepted as reusable rich-function
     progress without activating, extending, or explicitly declining the
     relevant bounded row.

5. **High - Pandoc rich conversion coverage is visible but overstated by the `2276/2276` manifest claim.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json`,
     `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion
     kernel with Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms, and
     WordPress block output, backed by meaningful upstream parity and explicit
     blockers.
   - Evidence: the Pandoc manifest reports `mapped: 2276` of `2276`, while
     lane status reports only `405` focused PHP behavior tests and the full
     Haskell runner remains unexecuted. The latest required rich-function
     areas are at least visible as gated rows: DOC/CFB, DOCX/OpenXML, PDF input
     and output handoff, EPUB, ODT/OpenDocument, templates, citations, math,
     tables, package containers, XML/HTML, Unicode/charset, JSON/YAML
     metadata, syntax highlighting, and archive/compression. None has an
     accepted support manifest, PHP ledger, malformed/corrupt evidence,
     activation record, or bounded install-attempt note.

6. **High - markerPDF's denominator still cannot carry its current rich PDF breadth.**
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
     tests. The current WinAnsi simple-font path is useful native work, but it
     remains pending and broader PDF text/page/OCR/table claims are still
     behind inactive `pdf-text-dictionary-core`, `pdf-page-render-plan-core`,
     `layout-ocr-result-core`, and `table-geometry-core` rows. Benchmark,
     chunk, runtime, and supplied-converter planners remain quarantine
     boundaries unless native extraction is independently tested.

7. **Medium - manifest/status count units and prose are still internally inconsistent.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: progress must track upstream denominator,
     mapped tests, PHP pass/fail, phase, blocker, and commit in comparable
     units.
   - Evidence: Difftastic's manifest field says `mapped: 1177`, while its
     manifest warning text still says `1167` mappings. markerPDF reports more
     mappings than denominator (`167/78`). Pandoc reports full mapped coverage
     (`2276/2276`) while PHP evidence is `405` behavior tests. Syncthing's
     `phpPass` is `9190` assertions while other lanes use behavior-test counts
     or selected-file ledgers. Percent estimates are not auditable until
     upstream artifacts, mapped behavior units, PHP assertions, PHP behavior
     cases, and accepted commit state are split explicitly.

8. **Medium - several lanes are still accumulated dirty piles, so focused green evidence does not identify an acceptable slice.**
   - Paths: `lanes/lightningcss/*`, `lanes/quadrable/*`,
     `lanes/readability/*`, `audits/integration-status.md`.
   - Goal requirement at risk: prefer small correct slices over broad shallow
     ports and commit small reviewable slices with passing tests.
   - Evidence: the latest LightningCSS integration rejection records `17`
     tracked files plus `203` untracked files under the lane, with the
     advertised style-attribute target fallback coupled to older visitor,
     bundle, CSS Modules, SVG, grid, media-query, border, background,
     selector, prefixer, and minifier work. Quadrable was similarly rejected as
     a `42`-tracked-file accumulated patch. Readability status explicitly says
     four advertised slices are interleaved across the same tracked files
     (AO3, UTF-16 byline length, negative heading cleanup, and WebMD
     `textArea` root promotion). Focused lane evidence is not enough until
     each accepted dirty scope exactly matches the evidence.

9. **Medium - bridge and shell-adjacent scaffolding still needs strict quarantine from progress credit.**
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
   - Evidence: `rg` still finds many `shell_exec()` example invocations in
     Difftastic tests and `proc_open()` Git oracle helpers in Gitoxide tests.
     markerPDF status still relies on supplied conversion callbacks and
     runtime/benchmark planners as boundaries. Some of this is legitimate
     oracle or scenario tooling, but manifest/status progress must keep it out
     of native mapped/pass counts unless the native path is independently
     tested and the bridge role is explicit.

## Required Next Intervention

Freeze writers, status publishers, handoff markers, and duplicate broad/focused
test loops long enough for two stable polls. Then accept or reject exactly one
owner-free reduced lane batch whose dirty files match its evidence. Normalize
that lane's manifest/status count units before publishing it. If the accepted
slice is ready for or blocked by a rich dependency, activate or extend one
bounded support-library row with its own upstream/spec denominator, mapped
fixtures, PHP ledger, malformed/corrupt cases where relevant, and bounded
`sudo -n` install-attempt notes where missing packages are claimed. Regenerate
`progress.md`, `porting.html`, and `porting-summary.json` from the accepted
commit. Only after the exact `pgrep -af '^php tools/run-tests\.php$'` gate is
empty and the tree remains frozen should one serialized no-argument
`php tools/run-tests.php` run be used as repo-wide acceptance evidence.
