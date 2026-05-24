# Independent Audit - 2026-05-24T20:51Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, recent
`audits/integration-status.md`, and recent Git history through
`61762309 Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T20:49:35Z -> 2026-05-24T20:50:58Z
HEAD: 617623095838
recent history: 61762309 Refresh independent audit status; 7a578dd1 Record Quadrable handoff rejection; 6bc3d986 Record Gitoxide handoff rejection; 57fc4d7d Refresh independent audit status; 2944e876 Record Syncthing handoff rejection
tracked status rows: 220 -> 221
default status rows including untracked: 23234 -> 23241
dirty shortstat: 220 files changed, 184932 insertions(+), 23275 deletions(-) -> 221 files changed, 185127 insertions(+), 23287 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active
exact root process gate: no rows in audit samples
root run by this audit: not started because the checkout changed during the gate window
```

Live manifest/status samples from the dirty worktree, not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1167/1307                 3777/0
dolt          613/613                   464/0
esbuild       225/2567                  225/0
gitoxide      1472/2877                 7609/0
libsqlite     221/1589                  221/0
LightningCSS  3014/3548                 4393/0
markerPDF     167/78                    272/0
pandoc        2276/2276                 404/0
quadrable     55/55                     260/0
rclone        492/1601                  492/0
Readability   1609/1984                 149/0
syncthing     658/658                   9170/0
```

## Findings

1. **Critical - no repo-wide acceptance evidence can be trusted from this moving dirty aggregate.**
   - Paths: `progress.md`, `audits/latest.md`,
     `lanes/*/lane-status.json`, `tools/run-tests.php`.
   - Goal requirement at risk: commit small reviewable slices with passing
     tests, periodically run repo-wide tests, and record failures honestly.
   - Evidence: the exact root gate was clear in my samples, but the checkout
     was not stable enough to run the no-argument harness. Between
     `20:49:35Z` and `20:50:58Z`, tracked dirty rows changed `220 -> 221`,
     untracked-inclusive status rows changed `23234 -> 23241`, and dirty
     shortstat changed from `220 files changed, 184932 insertions(+), 23275
     deletions(-)` to `221 files changed, 185127 insertions(+), 23287
     deletions(-)`. A root run from that state would prove only another moving
     anecdote, not an accepted snapshot.

2. **Critical - current lane statuses are still unaccepted handoffs, not integrated lane progress.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`,
     `audits/integration-status.md`.
   - Goal requirement at risk: implement native PHP incrementally and commit
     reviewable slices only after focused and root verification.
   - Evidence: every sampled lane status uses `pending`, `uncommitted`, or
     supervisor/integrator/root acceptance pending wording in `latestCommit` or
     `blocker`. Recent history confirms rejection rather than acceptance for
     Gitoxide and Quadrable (`6bc3d986`, `7a578dd1`), and the live dirty tree
     still spans all 12 lanes. Near-complete estimates such as Difftastic 99,
     Dolt 99, Pandoc 99, Quadrable 99, and Syncthing 99 are misleading until
     the pending handoffs are split, accepted, and tied to a single root result.

3. **High - `porting.html` is a stale accepted-snapshot dashboard and now materially disagrees with live metadata.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show at-a-glance upstream
     denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and
     commit for the current accepted state.
   - Evidence: the dashboard still publishes source snapshot
     `main 5e46840f9573`, generated `2026-05-24 20:23:24 UTC`. Live dirty
     metadata is far ahead: Difftastic dashboard `240/586` versus live
     `1167/1307`; Dolt dashboard `303/613` versus live `613/613`; LightningCSS
     dashboard `886/3532` versus live `3014/3548`; markerPDF dashboard
     `165/78` versus live `167/78`; Pandoc dashboard `619/2276` versus live
     `2276/2276`; rclone dashboard denominator `2553` versus live `1601`; and
     Syncthing dashboard PHP `324` versus live `9170`. It is useful only as the
     last accepted snapshot, not as current progress.

4. **High - support-library coverage remains backlog-only while current lane work crosses support boundaries.**
   - Paths: `dependency-backlog.json`, `lanes/rclone/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/libsqlite/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: support libraries need lane-level granularity:
     bounded native component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and no shell-out or
     whole-application credit.
   - Evidence: `dependency-backlog.json` has 37 rows and 0 active support
     ports. Current lane-local work is already touching support boundaries: rclone
     WebDAV LOCK/UNLOCK while `webdav-protocol-core` is inactive; markerPDF
     searchable PDF text/ToUnicode/WinAnsi while `pdf-text-dictionary-core` is
     inactive; Dolt and libsqlite JSON scalar work while `json-json5-document-core`
     and `sql-expression-semantics-core` are inactive; Syncthing global
     discovery query encoding while `url-percent-encoding-core` does not list
     Syncthing as a consumer. These slices can stay lane-local while
     unaccepted, but they should not be accepted as rich-function progress
     without activating, extending, or explicitly declining the relevant
     bounded row.

5. **High - Pandoc rich document-conversion coverage is visible but overstated by the `2276/2276` mapped manifest claim.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json`,
     `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion kernel
     with Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms, and
     WordPress block output, backed by meaningful upstream parity and explicit
     blockers.
   - Evidence: the Pandoc manifest reports `mapped: 2276` against a denominator
     of `2276`, while lane-status reports only `404` focused PHP behavior
     tests and the full Haskell runner remains unexecuted. The required rich
     areas are visible as gated rows, including DOC/CFB, DOCX/OpenXML, PDF
     input/text extraction and output handoff, EPUB, ODT/OpenDocument,
     templates, citations, math, tables, package containers, XML/HTML,
     Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression. None has an accepted support manifest, PHP ledger,
     malformed/corrupt evidence, activation record, or bounded install-attempt
     note.

6. **High - markerPDF's denominator still cannot support the breadth of its current rich PDF claims.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/markerpdf/src/PdfTextExtractor.php`,
     `lanes/markerpdf/src/BenchmarkRunner.php`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php`,
     `lanes/markerpdf/src/MarkerRuntimePlanner.php`,
     `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured
     content extraction pipeline; external runtime planning and supplied
     converter/model callbacks cannot count as native conversion progress.
   - Evidence: the manifest now maps `167` focused semantics against only `78`
     tracked upstream paths and `0` committed Python unit tests. The WinAnsi
     simple-font and ToUnicode text work is the right native direction, but it
     remains pending and broader PDF text/page/OCR/table work is still behind
     inactive `pdf-text-dictionary-core`, `pdf-page-render-plan-core`,
     `layout-ocr-result-core`, and `table-geometry-core` rows. Runtime,
     benchmark, chunk, and supplied-converter planners are quarantine
     boundaries unless native extraction is independently tested.

7. **Medium - manifest, status, and dashboard count units remain mixed.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: progress must track upstream denominator,
     mapped tests, PHP pass/fail, phase, blocker, and commit in comparable
     units.
   - Evidence: markerPDF reports more mappings than denominator (`167/78`).
     Pandoc reports full mapped coverage (`2276/2276`) while the PHP evidence
     is 404 behavior tests. Libsqlite uses an upstream veryquick denominator of
     `1589` inventory units while status prose also cites `329670` upstream
     tests. Several `phpPass` values are assertions, some are behavior cases,
     and some are test-file ledgers. Percent estimates cannot be audited until
     upstream artifacts, mapped behavior units, PHP assertions, PHP behavior
     cases, and accepted commit state are split explicitly.

8. **Medium - bridge and shell-adjacent scaffolding still needs strict quarantine from progress credit.**
   - Paths: `lanes/difftastic/tests/TokenDifferTest.php`,
     `lanes/gitoxide/tests/GitIndexUntrackedCacheTest.php`,
     `lanes/gitoxide/tests/GitUrlTest.php`,
     `lanes/gitoxide/tests/GitIndexTest.php`,
     `lanes/gitoxide/tests/FetchV2SessionTest.php`,
     `lanes/gitoxide/tests/FetchResponseTest.php`,
     `lanes/gitoxide/src/CredentialProgram.php`,
     `lanes/gitoxide/src/SshReceivePackTransport.php`,
     `lanes/markerpdf/src/BenchmarkRunner.php`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php`,
     `lanes/markerpdf/src/MarkerRuntimePlanner.php`.
   - Goal requirement at risk: bridge code, generated fixtures, and shell-outs
     may exist only as temporary fixture-generation or oracle tooling and must
     not count as native implementation progress.
   - Evidence: `rg` still finds `shell_exec` example invocations in
     Difftastic tests, `proc_open`-based Git oracle helpers in Gitoxide tests,
     Git credential/SSH command builders, and markerPDF benchmark/runtime
     planners. Some are legitimate oracles or command plans, but manifests and
     status rows must keep them out of native pass/mapped counts unless the
     native path is independently tested and the bridge role is explicit.

## Required Next Intervention

Freeze writers/status publishers and handoff markers long enough for two stable
polls, then accept or reject exactly one owner-free reduced lane batch whose
dirty files match its evidence. Normalize that lane's manifest/status count
units before publishing it. If the accepted slice is ready for or blocked by a
rich dependency, activate or extend one bounded support-library row with its own
upstream/spec denominator, mapped fixtures, PHP ledger, and malformed/corrupt
cases. Regenerate `progress.md`, `porting.html`, and `porting-summary.json` from
the accepted commit. Only after the exact `pgrep -af '^php tools/run-tests\.php$'`
gate is empty and the tree remains frozen should one serialized no-argument
`php tools/run-tests.php` run be used as repo-wide acceptance evidence.
