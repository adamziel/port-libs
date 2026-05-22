# Independent Audit - 2026-05-22 23:44 UTC

Scope reviewed: `goal.md`, `progress.md`, current `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, bridge/shell-out usage, current dirty worktree state, and recent Git history through latest current `HEAD` `fa41c8b`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: this checkout kept moving during the audit. `HEAD` advanced from `752845b` to `eb91109` during the first root test run, then through `7722e9a`, `ed67562`, and rewritten esbuild/markerPDF history to current `fa41c8b`. A transient `97d116d` markerPDF commit was also observed and then no longer appeared in recent history before finalization. The latest observed dirty state before finalizing this audit was `190` `git status --short` entries, including `33` tracked modified entries; `git diff --shortstat` reported `31 files changed, 5157 insertions(+), 203 deletions(-)`. Treat the root test result below as diagnostic evidence on a moving aggregate, not an accepted integration checkpoint.

## Findings

1. **Critical - the repository is still a moving dirty aggregate, not a reviewable integration checkpoint.**
   - Paths: `.tmux-team/prompts/dashboard-updater.md`, `audits/integration-status.md`, `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/quadrable/src/QuadbStore.php`, `lanes/rclone/src/MemoryProvider.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`.
   - Evidence: commits landed while this audit and `php tools/run-tests.php` were running, and the dirty set changed from `39` tracked modified entries to `26`, then `29`, `30`, `24`, `31`, `27`, and finally `33`. The latest observed status still spans prompts, audit/status files, manifests, lane implementation files, tests, examples, and generated dashboard files.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, cleanup of unrelated changes, precise blockers, and latest commit tracking.
   - Audit judgment: freeze or explicitly coordinate writers, then accept or reject one lane batch at a time with a fresh root rerun after each accepted batch.

2. **High - `porting.html` and `porting-summary.json` are stale against current manifests and lane statuses.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:56`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:3`, `porting-summary.json:57`, `porting-summary.json:108`, `porting-summary.json:125`, `porting-summary.json:176`.
   - Evidence: the dashboard still says average progress is `14.3%` and was generated at `2026-05-22 15:40:20 UTC`, while current lane statuses average about `51.2%`. Examples: Difftastic is `8%` on the dashboard vs `38%` in `lanes/difftastic/lane-status.json:4`; Gitoxide is `66%` vs `88%`; LightningCSS is `14%` vs `51%`; markerPDF is `10%` vs `46%`; Pandoc is `10%` vs `52%`; Quadrable is `8%` vs `61%`; Readability is `12%` vs `45%`; Syncthing is `8%` vs `51%`. The summary also shows old mapped/denominator values such as markerPDF `11 / 27` while the manifest now reports `78 / 78`, Pandoc `19 / 1979` while the manifest reports `223 / 2028`, and Readability `89 / 1984` while the manifest reports `729 / 1984`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: do not publish or trust the dashboard until it is regenerated from a single accepted green snapshot.

3. **High - `progress.md` is stale as a coordination document.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:40`, `progress.md:41`, `progress.md:42`, `progress.md:230`.
   - Evidence: the Active Lanes table still carries old estimates and next tasks such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, Quadrable `8%`, Syncthing `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`. The audit/status paragraph still described the prior `149` files / `13548` assertions / `203` status-entry audit boundary before this update.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the audit status and next intervention text. The lane table should be refreshed by the supervisor only after dirty lane batches are accepted or rejected.

4. **High - lane blocker and latest-commit fields are not reliable coordination data.**
   - Paths: `lanes/gitoxide/lane-status.json:13`, `lanes/libsqlite/lane-status.json:13`, `lanes/markerpdf/lane-status.json:13`, `lanes/quadrable/lane-status.json:13`, `lanes/readability/lane-status.json:13`, `lanes/rclone/lane-status.json:13`, `lanes/syncthing/lane-status.json:13`, `porting-summary.json:20`, `porting-summary.json:71`, `porting-summary.json:105`, `porting-summary.json:156`, `porting-summary.json:190`, `porting-summary.json:207`.
   - Evidence: several `latestCommit` fields contain prose such as `pending markerPDF chunk conversion planner lane commit`, `current run`, `current batch`, or multi-commit narrative instead of an accepted commit hash. Dashboard commits are even older, for example Difftastic still shows `eaf28e1` while lane status now points to `42318fb`, and Gitoxide still shows `a184e4a` while current status points to the later thin-pack slice and subsequent audit commits.
   - Goal requirement at risk: `goal.md` requires precise blockers, honest repo-wide test records, audit status, and latest commit.
   - Audit judgment: normalize lane status after integration. Until then, these fields are narrative notes, not machine-readable truth.

5. **High - upstream denominator and mapped-count units remain inconsistent across manifests.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:21`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`.
   - Evidence: Difftastic counts mixed behavior artifacts; Dolt combines executable files, BATS cases, and fixtures; Gitoxide uses upstream files plus targeted test attributes; LightningCSS counts helper invocations as behavior checks; markerPDF counts repository paths plus benchmark pairs with `0` committed Python unit tests; Pandoc counts files/artifacts; Quadrable mixes tracked paths, top-level scenarios, `verify` calls, and runner pass; Readability maps local assertions against Mocha tests. These are useful inventories, but the units are not comparable.
   - Goal requirement at risk: `goal.md` requires real upstream benchmark denominators, mapped upstream tests, PHP passing/failing counts, and honest percentage estimates.
   - Audit judgment: split the schema into separate fields for upstream files/artifacts, upstream test cases, mapped upstream cases, native PHP behavior tests, assertions, failures, full-runner parity, bounded-runner evidence, and static inventory.

6. **Medium - high percentages can still be misread as upstream parity.**
   - Paths: `lanes/gitoxide/lane-status.json:4`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:20`, `lanes/pandoc/lane-status.json:4`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/syncthing/lane-status.json:4`, `lanes/syncthing/lane-status.json:12`, `lanes/markerpdf/lane-status.json:4`, `lanes/markerpdf/lane-status.json:12`, `lanes/difftastic/lane-status.json:4`, `lanes/difftastic/lane-status.json:12`.
   - Evidence: Gitoxide reports `88%` while full Cargo workspace tests are not run. Pandoc reports `52%` without the full Haskell upstream runner. Syncthing reports `51%` with bounded focused runner evidence but no full `go test ./...`. markerPDF reports `46%` while the full benchmark/conversion runner is not executed. Difftastic reports `38%` without upstream runner parity.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible, passing tests are not enough, and hard features must be marked as blockers or future slices.
   - Audit judgment: percentages should be labeled as native/local slice progress, not upstream parity.

7. **Medium - markerPDF still risks counting supplied model-boundary scaffolding as native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:20`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:24`, `lanes/markerpdf/lane-status.json:5`, `lanes/markerpdf/lane-status.json:11`, `lanes/markerpdf/lane-status.json:12`.
   - Evidence: the denominator remains a `78`-path repository inventory with `0` committed Python unit test files, two actual CI PDF/reference pairs, and many supplied-boundary mappings for pdftext, pypdfium, Surya, Texify, tabled, image preview, debug data, and conversion callbacks. Those boundaries are useful oracle scaffolding, but they are not native PDF-to-structured-content extraction parity.
   - Goal requirement at risk: `goal.md` requires a native PDF-to-structured-content extraction pipeline and says bridge code, generated fixtures, or shell-outs must not count as progress unless explicitly temporary oracle tooling.
   - Audit judgment: use the acquired benchmark pairs to drive document-level native extraction parity before increasing markerPDF status based on more supplied model/output boundaries.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Required command: `php tools/run-tests.php`

Exact result for this audit run:

```text
Exit status: 0
151 test files, 13763 assertions, 0 failures
```

This is not worse than the prior recorded green audit state, but it is green on a moving dirty aggregate checkout. Treat it as diagnostic evidence only until lane changes are integrated or rejected one batch at a time.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Pick one dirty lane batch, verify it in isolation, commit it with a fresh root rerun, and repeat. Before regenerating `porting.html` or `porting-summary.json`, clear stale lane-status blockers and latest-commit prose, then normalize the status schema so upstream denominator units, mapped upstream cases, PHP behavior tests, assertions, failures, and runner parity are separate fields.
