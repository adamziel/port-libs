<?php

declare(strict_types=1);

use PortLibs\Gitoxide\ReferenceName;

$defaultBranch = 'refs/heads/main';
$remoteBranch = 'refs/remotes/origin/main';
$linkedReviewBranch = 'worktrees/staging/refs/heads/review';

return [
    'references' => [
        $defaultBranch,
        $remoteBranch,
        'refs/tags/wp-release-2026.05',
        'HEAD',
        'main-worktree/HEAD',
        $linkedReviewBranch,
        'refs/worktree/bisect-state',
    ],
    'defaultBranch' => $defaultBranch,
    'remoteBranch' => $remoteBranch,
    'namespace' => 'site-a',
    'expectedNamespacedHead' => 'refs/namespaces/site-a/refs/heads/main',
    'refIterationPrefix' => 'refs/heads/review/',
    'expectedNamespacedRefIterationPrefix' => 'refs/namespaces/site-a/refs/heads/review/',
    'pluginReviewBranchBase' => 'refs/heads/review',
    'pluginReviewBranchComponent' => 'plugins/gutenberg',
    'expectedJoinedPluginReviewBranch' => 'refs/heads/review/plugins/gutenberg',
    'unsafePluginReviewComponent' => 'plugins/seo suite.lock',
    'expectedSanitizedPluginReviewComponent' => 'plugins/seo-suite',
    'expectedSanitizedPluginReviewBranch' => 'refs/heads/review/plugins/seo-suite',
    'remoteReviewBranchBase' => 'refs/remotes',
    'remoteReviewBranchComponent' => 'origin/review/plugins/gutenberg',
    'expectedJoinedRemoteReviewBranch' => 'refs/remotes/origin/review/plugins/gutenberg',
    'relativeDeploymentRef' => 'review/plugins/gutenberg',
    'expectedCategories' => [
        $defaultBranch => ReferenceName::CATEGORY_LOCAL_BRANCH,
        $remoteBranch => ReferenceName::CATEGORY_REMOTE_BRANCH,
        'refs/tags/wp-release-2026.05' => ReferenceName::CATEGORY_TAG,
        'HEAD' => ReferenceName::CATEGORY_PSEUDO_REF,
        'main-worktree/HEAD' => ReferenceName::CATEGORY_MAIN_PSEUDO_REF,
        $linkedReviewBranch => ReferenceName::CATEGORY_LINKED_REF,
        'refs/worktree/bisect-state' => ReferenceName::CATEGORY_WORKTREE_PRIVATE,
    ],
    'expectedShortNames' => [
        $defaultBranch => 'main',
        $remoteBranch => 'origin/main',
        'refs/tags/wp-release-2026.05' => 'wp-release-2026.05',
        'HEAD' => 'HEAD',
        'main-worktree/HEAD' => 'HEAD',
        $linkedReviewBranch => 'refs/heads/review',
        'refs/worktree/bisect-state' => 'worktree/bisect-state',
    ],
    'expectedRemoteTracking' => [
        $defaultBranch => false,
        $remoteBranch => true,
        'refs/tags/wp-release-2026.05' => false,
        'HEAD' => false,
        'main-worktree/HEAD' => false,
        $linkedReviewBranch => false,
        'refs/worktree/bisect-state' => false,
    ],
    'expectedWorktreePrivate' => [
        $defaultBranch => false,
        $remoteBranch => false,
        'refs/tags/wp-release-2026.05' => false,
        'HEAD' => true,
        'main-worktree/HEAD' => true,
        $linkedReviewBranch => false,
        'refs/worktree/bisect-state' => true,
    ],
    'wordpressUse' => 'A WordPress deployment tool can classify active, remote-tracking, tag, linked-worktree, and namespaced refs before fetch or push planning without invoking git for-each-ref.',
];
