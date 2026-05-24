# Independent Audit - 2026-05-24T19:39Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `57e13cae Record Readability handoff rejection`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 19:34-19:39
HEAD moved during this audit window: 64cf436db560 -> 57e13cae6849
recent history: 57e13cae Record Readability handoff rejection; 64cf436d Record rclone handoff rejection; 303bf14e Refresh independent audit status; ca5c7111 Record markerPDF handoff rejection; 4af74d41 Refresh independent audit status; 49b5a511 Record Quadrable handoff rejection; eebc7e29 Refresh independent audit status; 7370ac38 Record libsqlite handoff rejection
default status rows including untracked moved during this run: 22145 -> 22197 -> 22017
dirty shortstat moved during this run: 241 files changed, 214161 insertions(+), 26289 deletions(-) -> 241 files changed, 214230 insertions(+), 26289 deletions(-) -> 235 files changed, 209158 insertions(+), 26542 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows, 0 active (blocked 1, candidate 25, deferred 11)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required exact pre-root process gate:

```text
2026-05-24T19:34:56Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:36:43Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:39:24Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact root gate was clear, but
the checkout was not stable enough: dirty row count and shortstat changed
during this audit window, recent integration status is rejection-led, and the
latest accepted frozen-snapshot root result does not exist.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1106/1246                 3714/0
dolt          613/613                   456/0
esbuild       477/2567                  477/0
gitoxide      1454/2877                 7547/0
libsqlite     213/1589                  213/0
LightningCSS  3000/3548                 3956/0
markerPDF     163/78                    268/0
pandoc        2276/2276                 398/0
quadrable     55/55                     257/0
rclone        458/2553                  458/0
Readability   1984/1984                 3881/0
syncthing     658/658                   9059/0
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:49-52`,
     `audits/integration-status.md:3-83`, `lanes/*/lane-status.json:12-14`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verified handoff cleanup, and honest
     repo-wide verification before progress is accepted.
   - Evidence: while this audit was being written, history advanced from
     `64cf436d Record rclone handoff rejection` to `57e13cae Record Readability
     handoff rejection`. The final sampled dirty state is still enormous and
     moving: default status rows changed `22145 -> 22197 -> 22017` and dirty
     shortstat changed from `241 files changed, 214161 insertions(+), 26289
     deletions(-)` through `241 files changed, 214230 insertions(+), 26289
     deletions(-)` to `235 files changed, 209158 insertions(+), 26542
     deletions(-)` during this run. This is not a stable integration point.

2. **Critical - root-harness evidence is still not an accepted frozen-snapshot result.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:89-128`,
     `progress.md:51`, `lanes/*/lane-status.json:12-14`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording.
   - Evidence: the latest integration-owned markerPDF root run completed with
     `378` test files, `58733` assertions, and `1` failure while `HEAD` moved.
     This audit's exact no-argument root gate returned no rows at `19:34:56Z`,
     `19:36:43Z`, and `19:39:24Z`, but the tree was still moving, so starting a
     new root harness would only create another non-acceptance anecdote.

3. **Critical - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:1-8`, `porting-summary.json:11-213`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes snapshot `89260857cc71`,
     generated `2026-05-24 12:29:46 UTC`, while current `HEAD` is
     `57e13cae6849`. Current lane metadata differs materially: Difftastic is
     `1106/1246` and `3714` pass units versus dashboard `851/1077` and `3245`;
     Gitoxide is `1454/2877` and `7547` versus dashboard `2877/2877` and
     `7152`; markerPDF is `163/78` and `268` versus dashboard `347/396` and
     `484`; rclone is `458/2553` and `458` versus dashboard `906/1601` and
     `906`; Pandoc is `398` pass units versus dashboard `362`; Syncthing is
     `9059` versus dashboard `7902`.

4. **High - the newest Readability and rclone rejections confirm handoffs still do not match their dirty scope.**
   - Paths: `audits/integration-status.md:3-83`,
     `audits/integration-status.md:85-151`,
     `lanes/readability/lane-status.json:4-13`,
     `lanes/rclone/lane-status.json:4-13`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12-20`,
     `dependency-backlog.json:43-59`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35-40`, and
     `goal.md:48` require focused, reviewable slices with evidence that matches
     the committed files.
   - Evidence: the latest Readability intake rejected/deferred a narrow boolean
     option truthiness claim because `ArticleExtractor.php` and the test diff
     contain a broad accumulated parser, URL, JSON-LD, scoring, sibling/table,
     serializer, media, and fixture rewrite. The rclone intake immediately
     before it rejected/deferred a WebDAV DELETE partial `RemoveAll` claim
     because the tracked diff was an older OneDrive permission-planner batch and
     the advertised WebDAV files were inside a broad 248-file untracked pile.
     The inactive support rows remain correct; no Readability or WebDAV
     support-library progress should be counted from these lane-local piles.

5. **High - support-library coverage is visible but still not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:7-42`,
     `dependency-backlog.json:81-230`,
     `dependency-backlog.json:233-426`,
     `dependency-backlog.json:629-646`, `porting.html:72-129`,
     `progress.md:17-36`.
   - Goal requirement at risk: `goal.md:35-40` require real denominators,
     meaningful fixture parity, edge-case coverage, and honest blockers. The
     latest support-library directive requires bounded native components,
     activation gates, dependency-specific upstream/spec denominators, mapped
     fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant,
     and bounded install-attempt notes before missing packages become final
     blockers.
   - Evidence: Pandoc's DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression needs are visible as gated rows. Other rich-function
     support rows are also present for WebDAV, URL/path handling, source maps,
     tree-sitter, sequence diff/merge, Protobuf, QR, checksums, SQL/storage,
     and provider metadata. But all 37 rows remain `candidate`, `deferred`, or
     one `blocked`; there are still 0 active support rows, no accepted support
     manifests, no dependency-specific PHP ledgers, no malformed/corrupt
     evidence records, no accepted activation records, and no bounded
     install-attempt notes. Fixture-only lane-local helpers must not receive
     support-library progress credit.

6. **High - Pandoc remains far short of the original rich conversion-kernel goal despite 99% status language.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:414-416`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1440-1443`,
     `lanes/pandoc/lane-status.json:4-13`,
     `dependency-backlog.json:81-230`,
     `dependency-backlog.json:233-426`,
     `dependency-backlog.json:629-646`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc records `2276/2276` over a static inventory and `398`
     focused PHP pass units, but full upstream Haskell runner parity is
     unexecuted. The current slice explicitly excludes upstream Pandoc
     invocation, live fetches, browser tooling, converter shell-outs, PDF
     processing, ZIP/package parsers, citation/CSL engines, PlainMath/MathML
     full conversion, broader XML/HTML DOM support, TeX math/ref conversion
     beyond embedded annotations, and broader syntax-highlighting support.
     Those gaps are central to the requested rich conversion kernel, not
     optional polish.

7. **High - markerPDF still overstates denominator progress and mixes native extraction with runtime/application planning.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:218`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:406`,
     `lanes/markerpdf/lane-status.json:4-13`,
     `audits/integration-status.md:153-229`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35-40` say wrappers, shell-outs, whole applications, runtime
     launchers, and plan-only behavior must not count as native implementation
     progress.
   - Evidence: the manifest reports `mapped: 163` against a `total: 78`
     repository-path denominator, so its numerator exceeds its stated
     denominator. The warning still lists Python/PDF/model stack dependencies
     and live runtime/application surfaces such as Streamlit, FastAPI/Uvicorn,
     pypdfium/PDF rendering, Surya/OCR/Texify/Torch/Nougat, and benchmark or
     conversion scripts. Native `PdfTextExtractor` slices can count only within
     their bounded PDF-text evidence, not as progress on those external runtime
     systems or support libraries.

8. **High - Gitoxide's current reduced handoff no longer supports the dashboard's full-mapped claim.**
   - Paths: `goal.md:7`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13-22`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:116`,
     `lanes/gitoxide/lane-status.json:4-13`, `porting.html:59`,
     `porting-summary.json:61-76`.
   - Goal requirement at risk: `goal.md:7`, `goal.md:25`, and
     `goal.md:35-40` require a Git implementation with packfiles, refs,
     commits, object database, protocol v2, sparse/partial clone, push, merge,
     server-oriented primitives, and a real upstream denominator.
   - Evidence: current Gitoxide manifest says `1454/2877` mapped for a reduced
     `gix-discover` HEAD validation slice and says root aggregate verification,
     full Cargo workspace parity, and bounded gix-ref selectors remain outside
     the lane-worker handoff. The dashboard still claims `2877/2877` mapped and
     `98%`, hiding the reduced scope and the original-priority surface still at
     risk.

9. **Medium - manifest/status ledgers still use inconsistent count units and percentages.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json:12-20`,
     `lanes/*/lane-status.json:4-13`, `porting.html:56-67`,
     `porting-summary.json:11-213`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by upstream denominator,
     mapped tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: markerPDF uses repository paths as denominator while mapped
     behaviors exceed that denominator. Difftastic reports mapped behavior
     artifacts while lane status reports assertion units. Dolt reports PASS
     cases while its manifest denominator is executable upstream files. Pandoc
     reports `2276/2276` mapped static artifacts with only `397` focused PHP
     behavior tests. Most lanes still show `95-99%` while `latestCommit` is
     pending/uncommitted and root acceptance is absent.

## Required Next Intervention

Freeze writers/runners/status publishers long enough for two stable polls of
`HEAD`, dirty status rows, shortstat, active root PIDs, and relevant handoff/log
mtimes. Do not add more lane breadth while handoffs are still accumulated.
Triage the latest failed integration-owned root result, then accept or reject
one owner-free reduced lane batch whose dirty files match its evidence exactly.
Normalize manifest/status units for that lane in the same atomic change,
regenerate `porting.html` and `porting-summary.json` from the accepted commit,
then run exactly one serialized no-argument `php tools/run-tests.php` only if
`pgrep -af '^php tools/run-tests\.php$'` stays empty on that frozen snapshot.
Do not activate a support-library row until the base lane is accepted-ready or
accepted-blocked on that exact bounded component with its own denominator,
mapped fixtures, malformed/corrupt cases, PHP pass/fail ledger, and
install-attempt notes where missing packages matter.
