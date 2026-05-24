# Independent Audit - 2026-05-24T21:20Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`,
recent `audits/integration-status.md`, and recent Git history through
`d7d1528e Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T21:18:14Z -> 2026-05-24T21:19:23Z
HEAD: d7d1528e7753
recent history: d7d1528e Refresh independent audit status; 9784b10c Port libsqlite JSON scalar functions; 995e2e35 Refresh independent audit status; 6ff13fea Record Quadrable handoff rejection; 8a77a848 Refresh independent audit status
tracked status rows before audit edits: 216 -> 219
default status rows including untracked before audit edits: 23595 -> 23600
dirty shortstat before audit edits: 216 files changed, 185829 insertions(+), 23228 deletions(-) -> 219 files changed, 186038 insertions(+), 23366 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred; every sampled row still has upstreamDenominator null
exact root process gate: early samples clear; final pre-finish gate matched active no-argument root PID 1939400
owner evidence: 1939400 claude 1930747 Rs 00:14 php tools/run-tests.php
root run by this audit: not started because the worktree and dashboard/status metadata moved during sampling, and the final exact gate was occupied by another root harness
```

Live manifest/status samples from the dirty worktree, not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1191/1307                 3785/0
dolt          613/613                   467/0
esbuild       227/2567                  227/0
gitoxide      1477/2877                 7634/0
libsqlite     223/1589                  223/0
LightningCSS  3022/3548                 4406/0
markerPDF     168/78                    273/0
pandoc        2276/2276                 406/0
quadrable     55/55                     261/0
rclone        505/1601                  505/0
Readability   1645/1984                 151/0
syncthing     658/658                   9235/0
```

## Findings

1. **Critical - aggregate acceptance is still unsafe because the tree is moving while root/dashboard/status evidence is being produced.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `audits/integration-status.md`, `porting.html`,
     `porting-summary.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit
     small reviewable slices with passing verification from a stable snapshot.
   - Evidence: the early exact root gate was clear, but the repository moved
     from `23595` to `23600` default status rows, from `216` to `219` tracked
     rows, and from `216 files changed, 185829 insertions(+), 23228
     deletions(-)` to `219 files changed, 186038 insertions(+), 23366
     deletions(-)` before audit edits. A final pre-finish gate then matched
     active root PID `1939400 php tools/run-tests.php` with owner evidence
     `1939400 claude 1930747 Rs 00:14 php tools/run-tests.php`. Starting
     another no-argument root run here would either duplicate the active root
     or produce another moving-target result, not an acceptance baseline.

2. **Critical - the latest accepted root result is lane-scoped, not a general green light for the dirty tree.**
   - Paths: `audits/integration-status.md`, `progress.md`,
     `lanes/libsqlite/*`, `tools/run-tests.php`.
   - Goal requirement at risk: a passing root harness must be tied honestly to
     the accepted slice and must not validate unrelated dirty lane output.
   - Evidence: `9784b10c` accepted only the held libsqlite JSON scalar/path
     stack. The integration note explicitly says the global dirty aggregate
     moved while other lanes ran and that the root result is evidence only for
     the held libsqlite batch. Other lane statuses still advertise pending or
     uncommitted handoffs that have not been accepted by that root run.

3. **Critical - live lane status files still describe unaccepted worker output, not integrated native port progress.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: native PHP slices must be integrated
     incrementally, reviewed, tested, and committed; dirty worker handoffs must
     not count as accepted progress.
   - Evidence: current status fields say `pending`, `uncommitted`, `not
     committed`, or root/integrator acceptance pending for every primary lane
     except the newly accepted libsqlite slice. Recent history remains
     rejection-heavy: Quadrable at `6ff13fea`, markerPDF at `b5e907b5`,
     LightningCSS at `c4d28048`, Gitoxide at `6bc3d986`, and Syncthing at
     `2944e876` and `6bd136e9`.

4. **High - generated dashboard artifacts are dirty, claim a `d7d1528e7753` snapshot, and include unaccepted pending lane metadata.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted
     upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit.
   - Evidence: `porting.html` now says it was generated at
     `2026-05-24 21:18:22 UTC` from `main d7d1528e7753`, but the files are
     uncommitted and rows include pending/uncommitted lane work such as
     Difftastic `1177 / 1307 mapped` with commit `pending`, Gitoxide
     `pending`, markerPDF `pending`, Readability `uncommitted`, and
     LightningCSS `HEAD d7...`. This is not a clean accepted publication
     snapshot.

5. **High - support-library tracking is visible but still not first-class coverage.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: support libraries require the same granularity
     as lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec-suite evidence as can actually run.
   - Evidence: all `37` rows are backlog-only (`0` active) and every sampled
     row has `upstreamDenominator: null`. The rows cover the important rich
     functions, but they are not accepted support ports.

6. **High - current rich lane work crosses inactive support boundaries.**
   - Paths: `dependency-backlog.json`, `lanes/rclone/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/syncthing/lane-status.json`,
     `lanes/quadrable/lane-status.json`.
   - Goal requirement at risk: rich-function dependency work must not be
     counted as reusable support progress unless the bounded row is activated,
     tested, and evidenced.
   - Evidence: rclone is mapping WebDAV XML while `webdav-protocol-core` and
     `xml-html5-dom-core` are inactive; markerPDF is expanding PDF font/text
     extraction while `pdf-text-dictionary-core` is inactive; Dolt/libsqlite
     JSON and SQL semantics reference `json-json5-document-core` and
     `sql-expression-semantics-core`, but only libsqlite base-lane work was
     accepted; LightningCSS target/color work still sits ahead of
     `browser-compat-target-data-core`; Syncthing BEP/DeviceID behavior sits
     ahead of `protobuf-wire-core`; Quadrable proof transport is still ahead of
     `quadrable-proof-transport-codec-core`.

7. **High - Pandoc rich conversion remains overstated by the `2276/2276` mapped claim.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json`,
     `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion
     kernel with Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms, and
     WordPress block output, backed by meaningful upstream parity and explicit
     blockers.
   - Evidence: the manifest reports `2276/2276` mapped while lane status shows
     `406` focused PHP behavior tests and the full Haskell runner remains
     unexecuted. Required Pandoc rich areas are visible as rows or reuse paths:
     DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     citations, math, tables, templates, package containers, XML/HTML,
     Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression. None has an accepted support manifest, PHP ledger,
     malformed/corrupt evidence, activation record, or bounded install-attempt
     note.

8. **High - markerPDF's denominator is too weak for its current rich PDF breadth.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/markerpdf/src/PdfTextExtractor.php`,
     `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native
     PDF-to-structured-content extraction pipeline; external runtime planning,
     supplied converter/model callbacks, and benchmark scaffolding cannot
     count as native conversion progress.
   - Evidence: the manifest maps `168` focused semantics against only `78`
     tracked upstream paths and no committed Python unit-test denominator. The
     native PDF text work is useful, but the latest `/Differences` handoff is
     still pending root/integrator acceptance and broader PDF text/page/OCR/table
     claims remain gated by inactive support rows.

9. **Medium - manifest/status count units are still inconsistent across lanes.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: progress must track upstream denominator,
     mapped tests, PHP pass/fail, phase, blocker, and commit in comparable
     units.
   - Evidence: markerPDF maps more items than its denominator (`168/78`).
     Pandoc maps the whole denominator (`2276/2276`) while reporting `406`
     PHP behavior tests. Syncthing and Gitoxide status `phpPass` values
     (`9235`, `7634`) are assertion-like, while Dolt/rclone/readability use
     behavior-case counts. Dashboard commit cells truncate or publish
     non-commit prose such as `pending`, `uncommitted`, and `HEAD d7...`.

10. **Medium - Syncthing URL/query encoding still lacks a reusable support route.**
    - Paths: `dependency-backlog.json`,
      `lanes/syncthing/lane-status.json`,
      `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: a missing bounded support row is a blocker
      once a base lane is ready for or blocked by the next essential rich
      capability.
    - Evidence: Syncthing status continues to claim global discovery lookup
      URL construction and Go-style query encoding, but
      `url-percent-encoding-core` lists `rclone`, `gitoxide`, `esbuild`,
      `lightningcss`, and `readability` only. Either add Syncthing as a gated
      consumer with spec/vector expectations or keep the evidence explicitly
      lane-local and non-reusable.

11. **Medium - several lanes remain broad dirty piles, so focused green evidence does not isolate an acceptable slice.**
    - Paths: `lanes/lightningcss/*`, `lanes/quadrable/*`,
      `lanes/readability/*`, `lanes/gitoxide/*`,
      `audits/integration-status.md`.
    - Goal requirement at risk: prefer small correct slices over broad shallow
      ports and commit small reviewable slices with passing tests.
    - Evidence: current statuses still describe mixed piles: Quadrable lists
      proof transport, noTrack, iterator/checkpoint, raw-LMDB, sync, and other
      slices together; Readability says six advertised slices are interleaved
      across the same tracked files; Gitoxide says WorkTreeGitDir cannot be
      isolated from earlier unaccepted discovery files; LightningCSS remains a
      dirty shared lane despite focused green evidence.

12. **Medium - bridge and shell-adjacent scaffolding still needs strict quarantine from progress credit.**
    - Paths: `lanes/difftastic/tests/TokenDifferTest.php`,
      `lanes/gitoxide/tests/*`, `lanes/markerpdf/src/BenchmarkRunner.php`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php`,
      `lanes/markerpdf/src/MarkerRuntimePlanner.php`.
    - Goal requirement at risk: bridge code, generated fixtures, and
      shell-outs may exist only as temporary fixture-generation or oracle
      tooling and must not count as native implementation progress.
    - Evidence: Difftastic/Gitoxide still retain oracle helpers, and markerPDF
      still has supplied conversion/runtime planner boundaries. These may be
      legitimate test or scenario tools, but manifests and statuses must keep
      them out of native mapped/pass counts unless the native path is
      independently tested and the oracle role is explicit.

## Required Next Intervention

Freeze writers, status publishers, dashboard regeneration, and duplicate
focused/root loops until two consecutive polls show unchanged `HEAD`, tracked
status, default status, shortstat, and exact root gate. Treat `9784b10c` as
accepted libsqlite-only evidence, not a dirty-tree baseline. Then accept or
reject exactly one owner-free reduced lane batch whose dirty files match its
evidence, normalize manifest/status count units, and regenerate
`progress.md`, `porting.html`, and `porting-summary.json` from that same
accepted commit. If the accepted slice is ready for or blocked by a rich
dependency, activate or extend exactly one bounded support-library row with a
dependency-specific upstream/spec denominator, mapped fixtures, PHP ledger,
malformed/corrupt cases where relevant, and bounded `sudo -n` install-attempt
notes where missing packages are claimed. Only after
`pgrep -af '^php tools/run-tests\.php$'` is empty and the tree remains frozen
should one serialized no-argument `php tools/run-tests.php` run be used as
repo-wide acceptance evidence.
