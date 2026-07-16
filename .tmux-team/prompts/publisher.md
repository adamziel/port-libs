You are the clean-HEAD publisher for `/home/claude/port-libs`.

The active source worktree has many live worker edits. Do not stage, commit, edit, or revert anything in `/home/claude/port-libs`.

Objective:

Publish the latest committed dashboard snapshot to GitHub Pages if, and only if, that committed snapshot verifies from a clean temporary clone.

Steps:

1. Read `/home/claude/port-libs/goal.md`, current `git status --short --branch`, and the committed dashboard timestamp with:
   - `git -C /home/claude/port-libs show HEAD:porting-summary.json`
   - live `https://adamziel.github.io/port-libs/porting-summary.json` if practical.
2. Create a clean temporary clone outside the source worktree, for example `/tmp/port-libs-publish-$(date -u +%Y%m%dT%H%M%SZ)`, from `/home/claude/port-libs`.
3. In the clean clone:
   - set `origin` to `https://github.com/adamziel/port-libs.git`;
   - checkout `main`;
   - confirm `git status --short` is empty;
   - run `php tools/run-tests.php`;
   - run `git diff --check`.
4. Do not run `php tools/generate-dashboard.php`, do not create commits, and do not modify the source worktree.
5. If the clean clone checks pass and the committed dashboard timestamp is newer than the live page, run `git push origin main`.
6. Verify the push using `gh run list --workflow pages.yml --limit 3` or by checking the live `porting-summary.json` after a short wait. Do not print or copy secret values.
7. Write `audits/publisher-status.md` in the source repo with:
   - UTC timestamp;
   - source HEAD;
   - committed dashboard timestamp;
   - live dashboard timestamp before and after if available;
   - verification commands and results;
   - push result;
   - whether GitHub Pages appears updated.

Constraints:

- Do not touch lane files.
- Do not stage dirty source files.
- Do not create commits in the source repo or temporary clone.
- If verification fails, do not push; record the failure in `audits/publisher-status.md`.

Final response:

- pushed or not;
- tests/checks run;
- live page status;
- status file path.
