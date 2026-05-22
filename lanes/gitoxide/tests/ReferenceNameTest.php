<?php

declare(strict_types=1);

use PortLibs\Gitoxide\ReferenceName;

return [
    'reference names expose upstream fullname file category and short name helpers' => static function (TestRunner $t): void {
        $cases = [
            ['refs/tags/tag-name', 'tag-name', ReferenceName::CATEGORY_TAG, false, false],
            ['refs/heads/main', 'main', ReferenceName::CATEGORY_LOCAL_BRANCH, false, false],
            ['refs/remotes/origin/main', 'origin/main', ReferenceName::CATEGORY_REMOTE_BRANCH, false, true],
            ['refs/notes/note-name', 'notes/note-name', ReferenceName::CATEGORY_NOTE, false, false],
            ['HEAD', 'HEAD', ReferenceName::CATEGORY_PSEUDO_REF, true, false],
            ['FETCH_HEAD', 'FETCH_HEAD', ReferenceName::CATEGORY_PSEUDO_REF, true, false],
            ['main-worktree/HEAD', 'HEAD', ReferenceName::CATEGORY_MAIN_PSEUDO_REF, true, false],
            ['main-worktree/FETCH_HEAD', 'FETCH_HEAD', ReferenceName::CATEGORY_MAIN_PSEUDO_REF, true, false],
            ['main-worktree/refs/heads/main', 'refs/heads/main', ReferenceName::CATEGORY_MAIN_REF, false, false],
            ['main-worktree/refs/notes/note', 'refs/notes/note', ReferenceName::CATEGORY_MAIN_REF, false, false],
            ['worktrees/name/HEAD', 'HEAD', ReferenceName::CATEGORY_LINKED_PSEUDO_REF, true, false],
            ['worktrees/name/FETCH_HEAD', 'FETCH_HEAD', ReferenceName::CATEGORY_LINKED_PSEUDO_REF, true, false],
            ['worktrees/name/refs/heads/main', 'refs/heads/main', ReferenceName::CATEGORY_LINKED_REF, false, false],
            ['worktrees/name/refs/notes/note', 'refs/notes/note', ReferenceName::CATEGORY_LINKED_REF, false, false],
            ['refs/bisect/good', 'bisect/good', ReferenceName::CATEGORY_BISECT, true, false],
            ['refs/rewritten/123456', 'rewritten/123456', ReferenceName::CATEGORY_REWRITTEN, true, false],
            ['refs/worktree/private', 'worktree/private', ReferenceName::CATEGORY_WORKTREE_PRIVATE, true, false],
        ];

        foreach ($cases as [$name, $shortName, $category, $isWorktreePrivate, $isRemoteTrackingBranch]) {
            $classification = ReferenceName::categoryAndShortName($name);

            $t->same($category, $classification['category']);
            $t->same($shortName, $classification['shortName']);
            $t->same($shortName, ReferenceName::shorten($name));
            $t->same($category, ReferenceName::category($name));
            $t->same($isWorktreePrivate, ReferenceName::isWorktreePrivate($name));
            $t->same($isRemoteTrackingBranch, ReferenceName::isRemoteTrackingBranch($name));
        }

        $t->same('main', ReferenceName::fileName('refs/heads/main'));
        $t->same(null, ReferenceName::category('hello/world'));
        $t->same('hello/world', ReferenceName::shorten('hello/world'));
    },
    'complete reference validation follows upstream slash and standalone rules' => static function (TestRunner $t): void {
        foreach ([
            'refs/heads/main',
            'etc/foo',
            'this./totally./works',
            'refs/heads/你好吗',
            'MAIN',
            'NEW_HEAD',
        ] as $name) {
            $t->same(true, ReferenceName::isValid($name), "valid complete {$name}");
            ReferenceName::assertValid($name);
        }

        foreach (['main', 'Main', '(null)', 'refs//heads/main', '/etc/foo', 'refs/heads/main/'] as $name) {
            $t->same(false, ReferenceName::isValid($name), "invalid complete {$name}");
            $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::assertValid($name));
        }
    },
    'reference categories construct full names like upstream Category to_full_name' => static function (TestRunner $t): void {
        $t->same(
            'refs/heads/full',
            ReferenceName::toFullName(ReferenceName::CATEGORY_LOCAL_BRANCH, 'refs/heads/full'),
        );
        $t->same(
            'refs/heads/refs/remotes/origin/other',
            ReferenceName::toFullName(ReferenceName::CATEGORY_LOCAL_BRANCH, 'refs/remotes/origin/other'),
        );
        $t->same(
            'refs/heads/HEAD',
            ReferenceName::toFullName(ReferenceName::CATEGORY_LOCAL_BRANCH, 'HEAD'),
        );
        $t->same(
            'main-worktree/refs/heads/main',
            ReferenceName::toFullName(ReferenceName::CATEGORY_MAIN_REF, 'refs/heads/main'),
        );
        $t->same(
            'worktrees/staging/refs/heads/review',
            ReferenceName::toFullName(ReferenceName::CATEGORY_LINKED_REF, 'refs/heads/review', 'staging'),
        );
        $t->same(
            'worktrees/staging/HEAD',
            ReferenceName::toFullName(ReferenceName::CATEGORY_LINKED_PSEUDO_REF, 'HEAD', 'staging'),
        );

        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::toFullName(ReferenceName::CATEGORY_LOCAL_BRANCH, 'invalid/'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::toFullName(ReferenceName::CATEGORY_LINKED_REF, 'refs/heads/main'));
    },
    'reference namespace helpers expand prefix and strip like gix ref namespace' => static function (TestRunner $t): void {
        $t->same('refs/namespaces/foo/', ReferenceName::expandNamespace('foo'));
        $t->same('refs/namespaces/foo/refs/namespaces/bar/', ReferenceName::expandNamespace('foo/bar'));
        $t->same(
            'refs/namespaces/site-a/refs/heads/main',
            ReferenceName::prefixNamespace('refs/heads/main', 'site-a'),
        );
        $t->same(
            'refs/heads/main',
            ReferenceName::stripNamespace('refs/namespaces/site-a/refs/heads/main', 'site-a'),
        );
        $t->same(
            'refs/heads/main',
            ReferenceName::stripNamespace('refs/heads/main', 'site-a'),
        );
        $t->same(
            'refs/namespaces/foo/prefix',
            ReferenceName::intoNamespacedPrefix('foo', 'prefix'),
        );
        $t->same(
            'refs/namespaces/foo/prefix/',
            ReferenceName::intoNamespacedPrefix('foo', 'prefix/'),
        );
        $t->same(
            'refs/namespaces/site-a/refs/heads/review/',
            ReferenceName::intoNamespacedPrefix('site-a', 'refs/heads/review/'),
        );

        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::expandNamespace(''));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::expandNamespace('foo/'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::expandNamespace('foo//bar'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::intoNamespacedPrefix('foo', '/refs'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::intoNamespacedPrefix('foo', '.git/refs'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::intoNamespacedPrefix('foo', 'refs\\heads'));
    },
    'partial reference names join components using upstream validation' => static function (TestRunner $t): void {
        $t->same('refs/heads/main', ReferenceName::joinPartial('refs/heads', 'main'));
        $t->same('refs/remotes/origin/main', ReferenceName::joinPartial('refs/remotes', 'origin/main'));
        $t->same('worktrees/staging/HEAD', ReferenceName::joinPartial('worktrees/staging', 'HEAD'));
        $t->same('heads/你好吗', ReferenceName::joinPartial('heads', '你好吗'));
        $t->same('(null)/fallback', ReferenceName::joinPartial('(null)', 'fallback'));

        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::joinPartial('', 'main'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::joinPartial('refs/heads', ''));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::joinPartial('refs/heads', '/main'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::joinPartial('refs/heads', 'feature lock'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::joinPartial('refs/heads', '.hidden'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::joinPartial('refs/heads', 'main.lock'));
    },
    'partial reference names sanitize with gix validate byte rules' => static function (TestRunner $t): void {
        $cases = [
            'refs/heads/main' => 'refs/heads/main',
            'main-worktree/HEAD' => 'main-worktree/HEAD',
            'worktrees/id/refs/bisect/good' => 'worktrees/id/refs/bisect/good',
            'heads/你好吗' => 'heads/你好吗',
            '(null)' => '(null)',
            '@' => '@',
            'refs/../somewhere' => 'refs/-/somewhere',
            '.refs/somewhere' => '-refs/somewhere',
            '..refs/somewhere' => '-refs/somewhere',
            'refs/./still-inside-but-not-cool' => 'refs/-/still-inside-but-not-cool',
            '/refs/heads/main' => 'refs/heads/main',
            'refs/heads/main/' => 'refs/heads/main',
            'refs/heads/main///' => 'refs/heads/main',
            'refs//heads////name with spaces' => 'refs/heads/name-with-spaces',
            'refs\\heads/name with spaces' => 'refs-heads/name-with-spaces',
            'refs/heads/main.lock' => 'refs/heads/main',
            'refs/heads/main.lock.lock' => 'refs/heads/main',
            'refs/heads/bad@{reflog}' => 'refs/heads/bad@-reflog}',
            'refs/heads/feature*review' => 'refs/heads/feature-review',
            '' => '-',
        ];

        foreach ($cases as $input => $expected) {
            $actual = ReferenceName::sanitizePartial($input);
            $t->same($expected, $actual, "sanitize {$input}");
            $t->same(true, ReferenceName::isValidPartial($actual), "sanitized partial is valid for {$input}");
        }
    },
    'branch reference validation rejects upstream reserved HEAD branch' => static function (TestRunner $t): void {
        $t->same(true, ReferenceName::isValidBranchName('refs/heads/main'));
        $t->same(true, ReferenceName::isValidBranchName('refs/heads/HEAd'));
        ReferenceName::assertValidBranchName('refs/heads/main');

        $t->same(false, ReferenceName::isValidBranchName('refs/heads/HEAD'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::assertValidBranchName('refs/heads/HEAD'));
        $t->same(false, ReferenceName::isValidBranchName('refs//heads/main'));
        $t->throws(InvalidArgumentException::class, static fn () => ReferenceName::assertValidBranchName('refs//heads/main'));
    },
    'wordpress fixture classifies deployment refs without git binary' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-reference-categories.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-reference-categories.php';

        $t->same($fixture['expectedCategories'], array_column($summary['references'], 'category', 'name'));
        $t->same($fixture['expectedShortNames'], array_column($summary['references'], 'shortName', 'name'));
        $t->same($fixture['expectedRemoteTracking'], array_column($summary['references'], 'remoteTracking', 'name'));
        $t->same($fixture['expectedWorktreePrivate'], array_column($summary['references'], 'worktreePrivate', 'name'));
        $t->same($fixture['expectedNamespacedHead'], $summary['namespacedHead']);
        $t->same($fixture['expectedNamespacedRefIterationPrefix'], $summary['namespacedRefIterationPrefix']);
        $t->same($fixture['defaultBranch'], $summary['activeBranch']);
        $t->same($fixture['remoteBranch'], $summary['remoteTrackingBranch']);
        $t->same($fixture['expectedJoinedPluginReviewBranch'], $summary['joinedPluginReviewBranch']);
        $t->same($fixture['expectedJoinedRemoteReviewBranch'], $summary['joinedRemoteReviewBranch']);
        $t->same($fixture['expectedSanitizedPluginReviewComponent'], $summary['sanitizedPluginReviewComponent']);
        $t->same($fixture['expectedSanitizedPluginReviewBranch'], $summary['sanitizedPluginReviewBranch']);
        $t->same(true, $summary['sanitizedPluginReviewComponentValid']);
        $t->same(true, $summary['sanitizedPluginReviewBranchAllowed']);
        $t->same(false, $summary['reservedHeadBranchAllowed']);
        $t->same(true, $summary['relativeDeploymentRefAllowed']);
        $t->same(false, $summary['lowercaseStandaloneRefAllowed']);
    },
];
