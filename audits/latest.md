# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate dashboard/status drift, current dirty worktree state, bridge/shell-out usage, and recent Git history through `628e02b` (`Stamp rclone lane status`). I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the required PHP harness is currently green, but the repository is still not an accepted integration checkpoint. The dirty worktree spans many lanes, and the generated dashboard/status surfaces are materially stale against the manifests and the latest root test result.

## Findings

1. **Critical - `porting.html` and `porting-summary.json` are stale against the current manifests.**
   - Paths: `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:6`, `porting-summary.json:23`, `porting-summary.json:40`, `porting-summary.json:57`, `porting-summary.json:74`, `porting-summary.json:91`, `porting-summary.json:108`.
   - Evidence: both generated files still show `Generated: 2026-05-22 15:40:20 UTC`. Dashboard mapped counts are Difftastic `15`, Dolt `5`, esbuild `16`, Gitoxide `737`, libsqlite `18`, LightningCSS `78`, markerPDF `11`, Pandoc `19`, Quadrable `24`, rclone `20`, Readability `89`, and Syncthing `27`. Current manifests report Difftastic `68`, Dolt `90`, esbuild `79`, Gitoxide `1052`, libsqlite `67`, LightningCSS `253`, markerPDF `78`, Pandoc `137`, Quadrable `55`, rclone `115`, Readability `492`, and Syncthing `97`. Quadrable is especially misleading: the dashboard still says the C++ runner fails, while the current manifest says `make -r test` passes all 34 upstream scenarios.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with current upstream denominators, mapped tests, PHP pass/fail counts, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: do not publish or use the dashboard for portfolio decisions until the accepted lane batches are integrated and the dashboard is regenerated from that same green state.

2. **High - the worktree is too broad and active to satisfy the small, reviewable-slice requirement.**
   - Paths: `lanes/difftastic/src/TokenDiffer.php`, `lanes/dolt/src/CommitLogTable.php`, `lanes/gitoxide/src/ReferenceName.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/rclone/src/SyncPlan.php`, `lanes/readability/src/ArticleExtractor.php`, `lanes/syncthing/src/BepWire.php`, `porting.html`, `porting-summary.json`, `progress.md`, `audits/integration-status.md`.
   - Evidence: `git diff --stat` currently shows 30 tracked files changed with about 3,338 insertions and 194 deletions, plus multiple untracked examples/fixtures/audit evidence files. The dirty set includes implementation, tests, manifests, notes, lane statuses, dashboard output, progress, and audit files across many lanes.
   - Goal requirement at risk: `goal.md` requires small reviewable slices, passing tests, progress generated from the accepted state, and cleanup of unrelated changes when integrating a slice.
   - Audit judgment: accept or reject dirty batches one lane at a time. A green root run is necessary, but not sufficient, for accepting this many unrelated lane changes.

3. **High - `progress.md` still has stale lane priorities and next tasks.**
   - Paths: `progress.md:31`, `progress.md:34`, `progress.md:38`, `progress.md:41`, `progress.md:194`, `progress.md:199`, `progress.md:230`, `progress.md:235`.
   - Evidence: the Active Lanes table still shows old next tasks such as libsqlite table-leaf row reads, Syncthing BEP hello/message work, and Dolt idle/deferred status even though current manifests and lane statuses have moved through later slices. I updated only the latest audit/root-suite and next-intervention text for this run; the lane table still needs a full supervisor refresh from accepted lane status data.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current lanes, blockers, next task per lane, percentage estimates, current owner/session, and audit status.
   - Audit judgment: do not treat the Active Lanes table as authoritative until the supervisor regenerates or reconciles it after lane-batch acceptance.

4. **High - lane status files carry conflicting root-suite evidence and blockers.**
   - Paths: `lanes/rclone/lane-status.json:10`, `lanes/rclone/lane-status.json:12`, `lanes/pandoc/lane-status.json:12`, `lanes/gitoxide/lane-status.json:10`, `lanes/gitoxide/lane-status.json:12`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/difftastic/lane-status.json:10`, `lanes/syncthing/lane-status.json:10`, `lanes/libsqlite/lane-status.json:10`.
   - Evidence: the current root run is `116 test files, 8955 assertions, 0 failures`. Rclone mentions that exact result, but Dolt cites `8947`, esbuild cites `8939`, Gitoxide and Readability still cite `8826`, and Difftastic/Syncthing/libsqlite/Quadrable still cite `8875` assertions.
   - Goal requirement at risk: `goal.md` requires precise blockers, current audit/status evidence, and honest repo-wide test recording.
   - Audit judgment: stale root-suite strings should not drive lane acceptance. Normalize lane status evidence after the dirty batches are integrated or rejected.

5. **High - upstream evidence and PHP counts are still modeled inconsistently.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:146`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:131`, `porting.html:56`, `porting.html:58`, `porting.html:63`.
   - Evidence: `runnerStatus` is a structured object in some manifests and a long string in Gitoxide/markerPDF/Quadrable. Several `total` fields are narrative strings rather than machine-checkable denominators. Dashboard PHP counts mix mapped checks, behavior tests, and assertion-like values; for example Gitoxide renders `1257 pass / 0 fail` while its manifest maps `1044`, and Readability's manifest says `mapped: 492` while its warning still says `459 local assertions`.
   - Goal requirement at risk: `goal.md` requires real upstream benchmark denominators, mapped upstream tests, PHP passing/failing counts, meaningful parity evidence, and clear dashboard columns.
   - Audit judgment: split full upstream pass parity, bounded runner evidence, static inventory, native behavior-test count, assertion count, and native failures into separate schema fields before using percentages for decisions.

6. **Medium - `mapped == total` still overstates native parity for inventory-shaped denominators.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:147`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`.
   - Evidence: markerPDF reports `78` mapped of `78`, but its denominator is repository/source-path inventory plus two benchmark PDF/reference pairs; full conversion still depends on Poetry, pdftext, pypdfium2, Surya/Texify/tabled model stacks, and benchmark runner execution. Quadrable reports `55` mapped of `55` and has a passing upstream C++ runner, but the manifest itself still says native PHP parity is partial and the full 500-trial persisted tracked-node fuzzer remains future evidence.
   - Goal requirement at risk: `goal.md` prohibits counting non-native or shallow evidence as implementation progress and requires hard features to be explicit blockers or future slices.
   - Audit judgment: these are useful evidence sets, but `mapped == total` must not imply full native parity unless the denominator is behavior-level and remaining semantic gaps are separately represented.

7. **Medium - several lanes still lack full upstream runner parity despite useful bounded evidence.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:75`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:146`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:138`.
   - Evidence: Difftastic, markerPDF, Pandoc, and Syncthing still have no full upstream runner pass. Gitoxide has bounded crate/subset evidence, not full workspace Cargo parity. Dolt and rclone have valuable bounded runner evidence, not full Go/BATS/provider/mount parity.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible and hard features must be marked as blockers or future slices.
   - Audit judgment: continue implementation only with these labels preserved; do not fold bounded/static evidence into full upstream parity.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Command: `php tools/run-tests.php`

```text
Exit status: 0
116 test files, 8955 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate active writers, then accept or reject dirty batches one lane at a time with `php tools/run-tests.php` after each accepted batch. Regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from the same accepted green state, then normalize the evidence schema before using dashboard percentages for priority decisions.
