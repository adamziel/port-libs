# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, representative tests/examples, upstream cache state, `tmux list-sessions`, bridge/shell-out search, and recent Git history through `9aefcef` (`Stamp pandoc lane status`). The worktree was clean at audit start. No lane implementation files were edited.

## Findings

1. **Critical - The portfolio baseline is still blocked by missing upstream runner parity and incomplete real denominators.**
   - Paths: `progress.md:11-15`, `progress.md:65-75`, `porting.html:48-59`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13-20`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:13-67`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-37`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:13-34`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13-57`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13-20`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13-50`
   - Evidence: four manifests remain `static-seed` with `total` set to `pending full upstream inventory`: difftastic, rclone, dolt, and esbuild. The other eight lanes have static or cloned inventories, but every runner is still unexecuted or blocked. That is a useful scaffold, not upstream parity.
   - Goal requirement at risk: "Build an `UPSTREAM_TEST_MANIFEST.json` that maps the real upstream benchmark denominator" and "Reach the required baseline for every lane."
   - Audit judgment: no lane should be treated as baseline-complete. The next progress claims must distinguish static inventory, mapped upstream fixture parity, and runner parity.

2. **High - Live coordination state disagrees with `progress.md`, and active capacity is on lower-priority seed lanes.**
   - Paths: `progress.md:39-40`, `progress.md:80-88`, `porting.html:48`, `porting.html:57`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13-17`
   - Evidence: `tmux list-sessions` returned `port-auditor`, `port-difftastic`, and `port-rclone`, while `progress.md` still said difftastic/rclone were stopped, the auditor was stopped, and no workers were active. Difftastic and rclone are priorities 9 and 10 and still have seed manifests while higher-priority lanes remain below baseline.
   - Goal requirement at risk: "`progress.md` must include ... current owner/session" and the priority-ordered supervised lane workflow.
   - Audit judgment: this is a coordination bug, not an implementation bug. The active sessions should be redirected to denominator/license/manifest reconciliation before feature breadth.

3. **High - Pandoc and Syncthing cloned-static inventories are not reproducible from their current cache worktrees.**
   - Paths: `.upstream-cache/pandoc`, `.upstream-cache/syncthing`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13-50`, `progress.md:58-59`, `progress.md:72-75`
   - Evidence: `.upstream-cache/pandoc` reports 2,781 tracked deletions and `.upstream-cache/syncthing` reports 940 tracked deletions. Their manifests cite `git ls-tree` inventories, but the checked-out files are dirty/empty enough that targeted reads or runner attempts are not reproducible from the working trees.
   - Goal requirement at risk: "Keep all generated artifacts reproducible" and "If the upstream runner cannot execute, create a defensible static inventory and mark it clearly as such."
   - Audit judgment: the static inventory counts may still be derivable from Git object metadata, but the caches should be restored or recloned before another worker uses them as evidence.

4. **High - Difftastic and rclone have clean upstream caches at manifest commits, but the manifests and dashboard still claim seed status.**
   - Paths: `.upstream-cache/difftastic`, `.upstream-cache/rclone`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:8-17`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:8-17`, `porting.html:48`, `porting.html:57`
   - Evidence: `.upstream-cache/difftastic` is at `7ccfcb315f7e46fd015809416c7d7dffa5be7078` and `.upstream-cache/rclone` is at `28d6b0b7b906da70afdc036ba5bb21f3c86613b8`, matching their manifests. Both worktrees are clean, but both manifests still say `static-seed`, `pending full upstream inventory`, and `license: pending verification from upstream checkout`.
   - Goal requirement at risk: "Identify the best upstream source repo, version/commit, license, architecture, and test suite" and "Replace seed manifests with full cloned/tested upstream benchmark denominators."
   - Audit judgment: these lanes do not need more PHP feature code first. They need the existing caches converted into counted, reproducible manifests.

5. **High - Dashboard PHP pass/fail counts remain easy to misread as upstream parity.**
   - Paths: `porting.html:36-38`, `porting.html:48-59`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-15`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:13-16`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13-16`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-16`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13-16`
   - Evidence: markerPDF shows denominator `6`, mapped tests `0`, and PHP pass/fail `5 / 0`. LightningCSS shows denominator `241`, mapped `7`, and PHP pass/fail `6 / 0`. Pandoc shows denominator `1979`, mapped `5`, and PHP pass/fail `5 / 0`. These are local PHP micro-tests, not upstream pass results.
   - Goal requirement at risk: "`porting.html` must show ... upstream denominator, mapped tests, PHP pass/fail" and "Passing tests are not enough."
   - Audit judgment: the dashboard should label these counts as local PHP until each passing test points to an upstream fixture, test name, or benchmark artifact.

6. **Medium - markerPDF remains especially weak for a priority-3 lane because no upstream benchmark PDF/reference pair is mapped.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-37`, `lanes/markerpdf/tests/PdfTextExtractorTest.php:7-38`, `porting.html:54`
   - Evidence: the manifest identifies six benchmark document names and two CI score thresholds, but `mapped` is `0`. The PHP tests construct tiny PDFs or use a local WordPress fixture; none compare against the upstream benchmark PDFs, score thresholds, or committed reference-like Markdown artifacts.
   - Goal requirement at risk: markerPDF's requested "PDF-to-structured-content extraction pipeline" and the quality bar requiring meaningful fixture parity.
   - Audit judgment: the next markerPDF work should acquire one benchmark/reference pair or make a clearly documented local surrogate tied to upstream scoring.

7. **Medium - Recent native slices are valid bootstraps but still mostly synthetic and far below the requested port scopes.**
   - Paths: `lanes/difftastic/tests/TokenDifferTest.php:8-19`, `lanes/rclone/tests/MemoryProviderTest.php:9-23`, `lanes/readability/tests/ArticleExtractorTest.php:8-76`, `lanes/pandoc/tests/MarkdownReaderTest.php:9-53`, `lanes/libsqlite/tests/SQLiteHeaderTest.php:26-109`
   - Evidence: difftastic has token diffs but no parser/tree-sitter fixture parity; rclone has an in-memory provider and checksum plan but no filter/provider contract denominator; Readability uses synthetic snippets and one WordPress fixture rather than a Mozilla `test-pages` source/expected/metadata fixture; Pandoc has a small Markdown AST slice with no golden `.native` comparison; libsqlite parses headers and page headers but not schema records or table cells.
   - Goal requirement at risk: each lane's named native port scope plus "meaningful fixture parity, edge-case coverage, error behavior, docs/examples, and WordPress-oriented scenarios."
   - Audit judgment: future implementation slices should be accepted only when they map to an upstream fixture/test ID or explicitly close a named manifest gap.

## Bridge / Shell-Out Check

Searched `lanes`, `tools`, `scripts`, and `.tmux-team` for `shell_exec`, `exec(`, `passthru`, `proc_open`, and `system(`. No matches were found, so I did not find committed bridge code or shell-outs being counted as native progress.

## Test Run

Command: `php tools/run-tests.php`

Exact result:

```text
14 test files, 166 assertions, 0 failures
```

Exit status: 0.

## Recommended Next Intervention

Redirect the active `port-difftastic` and `port-rclone` sessions to convert their clean upstream caches into counted manifests with verified licenses and runner blockers. Then restore or reclone `.upstream-cache/pandoc` and `.upstream-cache/syncthing` before relying on their cloned-static inventories. Keep dashboard PHP pass/fail values treated as local until tied to upstream fixture IDs.
