# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, representative tests/examples, upstream cache state, `tmux list-sessions`, bridge/shell-out search, and recent Git history through `650ace0` (`Stamp libsqlite and readability status`). The worktree was clean at audit start. No lane implementation files were edited.

## Findings

1. **Critical - The portfolio baseline is still blocked by missing real upstream denominators and runner parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13-20`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:13-34`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:13-67`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-37`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13-20`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13-57`, `porting.html:48-59`, `progress.md:11-15`
   - Evidence: six manifests still use `static-seed` with `total` set to `pending full upstream inventory`. The other six are stronger inventories, but none is upstream runner parity: Gitoxide is a tree inventory, libsqlite/LightningCSS/Readability are cloned or static inventories with runners not executed, markerPDF has 6 benchmark document names but 0 mapped documents, and Quadrable is a static `check.cpp` count with `make test` not executed.
   - Goal requirement at risk: "Build an `UPSTREAM_TEST_MANIFEST.json` that maps the real upstream benchmark denominator" and "Reach the required baseline for every lane."
   - Audit judgment: the repo has useful scaffolding, but it should not claim baseline completion for any lane until denominator-backed upstream behavior is mapped and runner gaps are explicit.

2. **High - Dashboard pass/fail values are local PHP micro-test counts, not upstream parity results.**
   - Paths: `porting.html:36-38`, `porting.html:52-58`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-16`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13-16`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:13-16`, `progress.md:58`
   - Evidence: markerPDF shows upstream denominator `6`, mapped tests `0`, and dashboard PHP pass/fail `5 / 0`. Readability shows denominator `1984`, mapped `7`, and PHP pass/fail `7 / 0`. LightningCSS shows denominator `241`, mapped `7`, and PHP pass/fail `6 / 0`. These are legitimate local smoke tests, but they are displayed beside upstream denominators without enough provenance to prevent parity misreading.
   - Goal requirement at risk: "`porting.html` must show ... upstream denominator, mapped tests, PHP pass/fail" and "Passing tests are not enough."
   - Audit judgment: dashboard/test terminology should label these as local PHP passes until each case points to an upstream fixture ID, test name, or benchmark artifact.

3. **High - Active coordination state drifted from actual tmux state.**
   - Paths: `progress.md:29-42`, `progress.md:74-78`, `porting.html:48-59`, `lanes/pandoc/lane-status.json:5-14`, `lanes/syncthing/lane-status.json:5-14`
   - Evidence: `tmux list-sessions` returned `port-auditor`, `port-pandoc`, and `port-syncthing`. Before this audit update, `progress.md` said all worker sessions were stopped/none active, while `porting.html` still marked every non-deferred lane as needing independent auditor review. Pandoc and Syncthing are active even though both manifests remain static seeds.
   - Goal requirement at risk: "`progress.md` must include ... current owner/session" and "Restart dead tmux agents unless they completed their assigned slice cleanly."
   - Audit judgment: the supervisor should reconcile live worker assignments before accepting more commits; live lower-priority seed lanes can hide drift while higher-priority denominator gaps remain unresolved.

4. **High - Existing upstream caches for Pandoc and Syncthing are not being converted into manifests and are currently dirty/empty worktrees.**
   - Paths: `.upstream-cache/pandoc`, `.upstream-cache/syncthing`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-18`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12-18`, `progress.md:38`, `progress.md:42`
   - Evidence: `.upstream-cache/pandoc` is at manifest commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and `.upstream-cache/syncthing` is at `3962a237232473c20a44945a6c8ce8c930375360`, but both worktrees report every tracked file deleted (`2781` status entries for Pandoc, `940` for Syncthing) and contain no present test files. `git ls-tree` still shows rough static denominators of `1977` test-ish Pandoc paths and `202` test-ish Syncthing paths, but the manifests remain `static-seed`.
   - Goal requirement at risk: "Replace seed manifests with full cloned/tested upstream benchmark denominators" and "Keep all generated artifacts reproducible."
   - Audit judgment: either restore/recount these caches into reproducible static inventories or remove them from consideration; do not let active workers build feature slices on unusable upstream checkouts.

5. **Medium - Recent native slices are valid bootstraps but remain far below the requested port scope.**
   - Paths: `lanes/libsqlite/tests/SQLiteHeaderTest.php:26-109`, `lanes/readability/tests/ArticleExtractorTest.php:8-76`, `lanes/markerpdf/tests/PdfTextExtractorTest.php:7-39`, `lanes/lightningcss/tests/CssMinifierTest.php:9-34`, `lanes/gitoxide/notes/upstream-inventory.md:22-25`
   - Evidence: libsqlite parses headers, varints, and b-tree page headers but not schema records or table cells. Readability tests synthetic snippets and one WordPress fixture, not a Mozilla `test-pages` source/expected/metadata fixture. markerPDF extracts text from synthetic/minimal PDFs, not an upstream benchmark PDF/reference pair. LightningCSS covers minification/value behavior, not selectors, at-rules, transforms, prefixing, or bundling. Gitoxide covers loose objects and commit parsing, not refs, trees, packs, protocol, merge, or push.
   - Goal requirement at risk: each named lane's requested native port scope plus the quality bar requiring meaningful fixture parity, edge-case coverage, and upstream behavior.
   - Audit judgment: additional breadth should be denominator-backed. The next useful slice is not another synthetic feature unless it maps to an upstream test or fixture.

6. **Medium - License and upstream architecture verification is still incomplete for five seed lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:5-10`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:5-10`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:5-10`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:5-10`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:5-10`
   - Evidence: these manifests still say `license: pending verification from upstream checkout`. Pandoc also says pending in the manifest, although its upstream cache has `COPYING.md` and related license files visible through `git ls-tree`; the cache is not in a usable restored worktree.
   - Goal requirement at risk: "Identify the best upstream source repo, version/commit, license, architecture, and test suite."
   - Audit judgment: license verification should be bundled with denominator work so implementation agents do not carry ambiguous upstream obligations forward.

7. **Low - WordPress scenarios are present but unevenly executable.**
   - Paths: `lanes/*/notes/wordpress-scenarios.md`, `lanes/libsqlite/examples/wordpress-options-root-page.php:10-52`, `lanes/markerpdf/examples/wordpress-import.php:9-27`, `lanes/readability/examples/wordpress-migration-blocks.php`, `lanes/lightningcss/examples/wordpress-block-theme.php`
   - Evidence: several lanes now have executable examples or WordPress-flavored tests, but the seed lanes still mostly rely on notes plus micro-tests. Even the executable examples are mostly synthetic and not tied to upstream fixture parity.
   - Goal requirement at risk: "Add focused WordPress scenarios that explain why the port matters for Playground, Data Liberation, SQLite, Git-backed workflows, migration tools, block editing, local-first sync, document import, or shared-hosting constraints."
   - Audit judgment: acceptable for bootstrap, not enough for baseline. Each lane needs at least one scenario that exercises real upstream-mapped behavior.

## Bridge / Shell-Out Check

Searched `lanes`, `tools`, `scripts`, and `.tmux-team` for `shell_exec`, `exec(`, `passthru`, `proc_open`, and `system(`. No matches were found, so I did not find committed bridge code or shell-outs being counted as native progress.

## Test Run

Command: `php tools/run-tests.php`

Result:

```text
14 test files, 166 assertions, 0 failures
```

Exit status: 0.

Note: a prior run during the audit, before active worker sessions added uncommitted Pandoc/Syncthing changes, also passed with `14 test files, 127 assertions, 0 failures`. The final result above reflects the current working tree at the end of this audit run.

## Recommended Next Intervention

Reconcile the active `port-pandoc` and `port-syncthing` sessions with the roadmap: stop feature breadth until their upstream caches are restored/recounted and the seed manifests become reproducible static inventories, or redirect active capacity back to higher-priority denominator-backed slices. In parallel, relabel dashboard PHP pass/fail counts as local until they are tied to upstream fixture IDs.
