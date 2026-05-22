# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, current dirty worktree state, process-execution bridge usage, and recent Git history through `f71f145` (`Update esbuild lane status for return super slice`). I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the required PHP harness is green on the current checkout, but `HEAD` moved during this audit from `4ef72c8` to `f71f145`, and the worktree remains dirty after several lane batches landed. Treat this as green test evidence, not an accepted integration checkpoint.

## Findings

1. **Critical - the integration state is moving and too dirty to review as one unit.**
   - Paths: `lanes/difftastic/src/TokenDiffer.php`, `lanes/dolt/src/CommitLogRenderer.php`, `lanes/gitoxide/src/ReferenceStore.php`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/markerpdf/src/TableFormatter.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/quadrable/src/QuadbStore.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`.
   - Evidence: current `git status --porcelain=v1` has `89` entries: `22` modified tracked files and `67` untracked files. The tracked dirty diff is `22 files changed, 1256 insertions(+), 136 deletions(-)`. Recent history advanced during the audit to `f71f145`, after starting at `4ef72c8`.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, cleanup of unrelated changes, and coordination status generated from accepted state.
   - Audit judgment: freeze or explicitly coordinate writers before integration. Accept or reject one lane batch at a time, rerunning the root suite after each accepted batch.

2. **Critical - `porting.html` and `porting-summary.json` are stale enough to misdirect portfolio decisions.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:3`.
   - Evidence: both generated files still say `Generated: 2026-05-22 15:40:20 UTC` and average progress `14.3%`. Current manifests report materially different mapped counts: Difftastic `80 / 404` but dashboard `15 / 404`; Dolt `97 / 613` but `5 / 613`; esbuild `95 / 2,567` but `16 / 2,567`; Gitoxide `1076 / 2877` but `737 / 2877`; libsqlite `81 / 1454` but `18 / 1454`; LightningCSS `322 / 3532` but dashboard denominator is still `312` and mapped `78`; markerPDF `78 / 78` but `11 / 27`; Pandoc `153 / 2028` but `19 / 1979`; Quadrable `55 / 55` but `24 / 55`; rclone `143 / 327` but `20 / 327`; Readability `552 / 1984` but `89 / 1984`; Syncthing `124 / 264` but `27 / 264`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit status, current work, blocker, and commit for every lane.
   - Audit judgment: do not publish or plan from the dashboard until it is regenerated from the same accepted green state as the lane batches.

3. **High - `progress.md` still presents stale per-lane estimates and next tasks.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:39`, `progress.md:40`, `progress.md:41`, `progress.md:42`.
   - Evidence: the Active Lanes table still shows old estimates/tasks such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Quadrable `8%`, Dolt `5%`, and esbuild `8%`. Current manifests/status files describe much broader mapped slices, including LightningCSS `322 / 3532`, markerPDF `78 / 78`, Pandoc `153 / 2028`, Quadrable `55 / 55`, Dolt `97 / 613`, Syncthing `124 / 264`, rclone `143 / 327`, libsqlite `81 / 1454`, and esbuild `95 / 2,567`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the latest audit/test/intervention lines in `progress.md`; the lane table needs a supervisor refresh from accepted manifest/status state.

4. **High - lane status files contain contradictory root-suite evidence.**
   - Paths: `lanes/readability/lane-status.json:5`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/markerpdf/lane-status.json:10`, `lanes/dolt/lane-status.json:10`, `lanes/dolt/lane-status.json:13`, `lanes/quadrable/lane-status.json:10`, `lanes/quadrable/lane-status.json:12`, `lanes/syncthing/lane-status.json:10`, `lanes/syncthing/lane-status.json:12`.
   - Evidence: the current required root run is green with `126 test files, 11197 assertions, 0 failures`, but lane statuses still cite incompatible root states: LightningCSS says root failed twice outside the lane; Gitoxide says `124` files and `10948` assertions; rclone says `124` files and `10969` assertions; libsqlite says `125` files and `11056` assertions; Syncthing says `125` files and `11002` assertions; Readability says `125` files and `11020` assertions; Difftastic says a first root attempt ended red before a green `125`-file rerun; Quadrable and esbuild say `126` files and `11229` assertions.
   - Goal requirement at risk: `goal.md` requires precise blockers and honest repo-wide test recording.
   - Audit judgment: normalize lane status audit/blocker strings after the current dirty batches are accepted or rejected, not piecemeal while writers are still moving `HEAD`.

5. **High - upstream and native test evidence is still inconsistently modeled.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:154`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`.
   - Evidence: `benchmarkDenominator.total` is numeric in some manifests and prose in others; `runnerStatus` is sometimes an object, sometimes a string, and sometimes absent from the top-level summary; no manifest exposes a consistent native `phpPass` / `phpFail` / `assertions` split; `phpBehaviorTests` exists only in some lanes.
   - Goal requirement at risk: `goal.md` requires real upstream benchmark denominators, mapped upstream tests, PHP passing/failing counts, and honest dashboard columns.
   - Audit judgment: normalize the evidence schema before mapped percentages drive planning or public status.

6. **High - `mapped == total` can overstate native parity for inventory-shaped denominators.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:154`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:252`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`.
   - Evidence: markerPDF reports `78 / 78` mapped while its denominator is repository/path inventory plus CI benchmark pairs and its full benchmark runner remains `not-executed`. Quadrable reports `55 / 55` and has useful upstream `make -r test` evidence, but the denominator is still a compact path/scenario inventory, not full native LMDB/quadb CLI feature parity.
   - Goal requirement at risk: `goal.md` says hard features must not be silently skipped and bridge/generated/shallow evidence must not count as native implementation progress.
   - Audit judgment: represent these as complete evidence inventories, not complete native ports, unless remaining semantic gaps are tracked in separate fields.

7. **Medium - several lanes still lack full upstream runner parity despite better bounded evidence.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:81`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:154`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:158`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:307`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:156`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:428`.
   - Evidence: Difftastic, markerPDF, Pandoc, and Syncthing still have no full upstream runner pass. Gitoxide has bounded crate/subset evidence, not full workspace Cargo parity. rclone and Dolt have useful bounded runner evidence, not full provider/mount or full Go/BATS parity. esbuild has core `make test` evidence but not release-extra `make test-all`; libsqlite has `veryquick`, not full `all`/`release` permutations.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible and hard features must be marked as blockers or future slices.
   - Audit judgment: preserve the bounded/static/full-runner distinction in every status surface before continuing breadth-first implementation.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Required command: `php tools/run-tests.php`

```text
Exit status: 0
126 test files, 11197 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate writers, then accept or reject the dirty lane batches one lane at a time. After each accepted batch, rerun `php tools/run-tests.php`; after the accepted state is green, regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that same state. Then normalize evidence fields so full upstream runner parity, bounded runner evidence, static inventory, native PHP behavior-test counts, assertion counts, and failures are separate fields before relying on mapped percentages.
