<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\ReferenceName;

$fixture = require __DIR__ . '/../fixtures/wordpress-reference-categories.php';

$references = [];
foreach ($fixture['references'] as $name) {
    $classification = ReferenceName::categoryAndShortName($name);
    $references[] = [
        'name' => $name,
        'category' => $classification['category'] ?? null,
        'shortName' => ReferenceName::shorten($name),
        'fileName' => ReferenceName::fileName($name),
        'worktreeName' => $classification['worktreeName'] ?? null,
        'remoteTracking' => ReferenceName::isRemoteTrackingBranch($name),
        'worktreePrivate' => ReferenceName::isWorktreePrivate($name),
    ];
}

$namespacedHead = ReferenceName::prefixNamespace($fixture['defaultBranch'], $fixture['namespace']);

return [
    'references' => $references,
    'activeBranch' => ReferenceName::toFullName(ReferenceName::CATEGORY_LOCAL_BRANCH, ReferenceName::shorten($fixture['defaultBranch'])),
    'remoteTrackingBranch' => ReferenceName::toFullName(ReferenceName::CATEGORY_REMOTE_BRANCH, ReferenceName::shorten($fixture['remoteBranch'])),
    'namespacedHead' => $namespacedHead,
    'namespaceStrippedHead' => ReferenceName::stripNamespace($namespacedHead, $fixture['namespace']),
];
