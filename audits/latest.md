# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, current dirty worktree state, bridge/shell-out usage, and recent Git history through `b29048e`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the current PHP harness is green, but the repository is still a broad dirty integration target. Treat the green run as test evidence, not proof that all dirty lane batches have been reviewed, accepted, and published into the dashboard.

## Findings

1. **Critical - the visible coordination surfaces are stale against the current manifests and latest root test run.**
   - Paths: `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `progress.md:194`, `progress.md:199`, `progress.md:230`, `progress.md:235`.
   - Evidence: `porting.html` and `porting-summary.json` were generated at `2026-05-22 15:40:20 UTC` and still show mapped counts `15/5/16/737/18/78/11/19/24/20/89/27`. Current manifests report difftastic `58`, Dolt `74`, esbuild `68`, Gitoxide `1001`, libsqlite `58`, LightningCSS `198`, markerPDF `76`, Pandoc `127`, Quadrable `55`, rclone `96`, Readability `352`, and Syncthing `90`. The latest root run is green with `109 test files, 7887 assertions, 0 failures`. Before this audit update, `progress.md` still recorded the older `108 test files / 7759 assertions` result, and its Active Lanes table still has stale phases/next tasks.
   - Goal requirement at risk: `goal.md` requires `progress.md` and `porting.html` to show current mapped upstream tests, PHP pass/fail counts, phase, audit status, blocker, current work, and commit.
   - Audit judgment: regenerate `porting.html`, `porting-summary.json`, lane statuses, and the progress lane table only after the remaining dirty batches are explicitly accepted or rejected from this same green baseline.

2. **High - the worktree is still too broad to be a reviewable integration checkpoint.**
   - Paths: `audits/integration-status.md`, `lanes/esbuild/src/JsLexer.php`, `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/gitoxide/src/ReferenceName.php`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/lightningcss/src/CssMinifier.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/readability/src/ArticleExtractor.php`, `lanes/syncthing/src/ReceiveEncrypted.php`, `porting.html`, `porting-summary.json`, `progress.md`.
   - Evidence: `git status --short` still shows modified implementation, test, fixture, manifest, note, status, dashboard, and audit files across multiple lanes, plus untracked fixtures/examples and audit files. `git diff --stat` reports 32 changed tracked files with 3022 insertions and 186 deletions before this audit update.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with passing tests and visible progress generated from the accepted state.
   - Audit judgment: accept or reject dirty batches one lane at a time. Rerun `php tools/run-tests.php` after each accepted batch before publishing the dashboard.

3. **High - upstream runner evidence is still modeled inconsistently.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:48`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:132`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `porting-summary.json:64`.
   - Evidence: `runnerStatus` is an object in some manifests, a string in Gitoxide/Quadrable/markerPDF, and absent/null for Pandoc. Dolt marks `executed: true` while its reason starts by saying full upstream runners were not executed. Dashboard PHP fields also mix behavior-test counts and assertion counts, for example Gitoxide renders `1257 pass / 0 fail` while the current Gitoxide lane status describes `1726 assertions`.
   - Goal requirement at risk: `goal.md` requires defensible upstream denominators, mapped upstream tests, PHP passing/failing counts, and precise blockers when upstream runners cannot execute.
   - Audit judgment: split the schema into full upstream pass parity, bounded upstream runner evidence, static inventory, native behavior tests, native assertions, and failures before using percentages for portfolio decisions.

4. **High - Gitoxide remains over-broad relative to upstream runner proof for the priority-1 lane.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/gitoxide/lane-status.json:10`, `lanes/gitoxide/lane-status.json:12`, `porting.html:56`.
   - Evidence: the manifest reports `1001 / 2877` mapped items across a very broad Git surface, but full workspace Cargo parity remains unexecuted. The bounded runner proof covers focused `gix-object`, `gix-ref`, and `gix-validate` slices; actual SSH auth/channel integration, broader git-daemon runtime integration, delta reuse/search heuristics, thin-pack repair, sparse-index writing, broader owned-vs-borrowed tag/commit writer parity, and full merge semantics remain open.
   - Goal requirement at risk: `goal.md` scopes Gitoxide as the highest-priority Git implementation with packfiles, refs, commits, object database, protocol v2, sparse/partial clone, push, merge, and server primitives, with upstream tests as the source of truth where possible.
   - Audit judgment: the next Gitoxide intervention should be a controlled upstream crate/integration runner expansion or a narrower high-risk semantic slice, not more percentage inflation.

5. **Medium - several lanes remain static inventory or bounded-runner evidence, not full upstream parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:69`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:132`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:122`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:123`.
   - Evidence: Difftastic, Pandoc, markerPDF, and Syncthing still do not have full upstream runner parity. rclone and Dolt have useful bounded runner evidence, not full provider/full BATS parity. markerPDF has two real CI benchmark pairs, but full benchmark execution and live extraction still require heavy Python/model/runtime dependencies.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible, generated fixtures or bridge calls must not count as native implementation progress, and hard features must be marked as blockers or future slices.
   - Audit judgment: keep these lanes moving, but label static inventory, bounded runner evidence, and full parity distinctly.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match was `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Command: `php tools/run-tests.php`

```text
Exit status: 0
109 test files, 7887 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate writers, then accept or reject the remaining dirty lane batches one at a time with a fresh root run after each accepted batch. Regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that accepted green state. Then normalize the evidence schema before continuing the highest-priority parity gaps: controlled Gitoxide upstream runner expansion and markerPDF native extraction parity from the two concrete CI benchmark PDF/reference pairs.
