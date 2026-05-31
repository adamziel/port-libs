<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-config-include-conditional.php';

return [
    'activeBranch' => $fixture['activeBranch'],
    'remoteUrl' => $fixture['remoteUrl'],
    'preview' => $fixture['preview'],
    'conflictStyle' => $fixture['conflictStyle'],
    'httpExtraHeader' => $fixture['httpExtraHeader'],
    'transferFsckObjects' => $fixture['transferFsckObjects'],
    'escapedGitdirPolicy' => $fixture['escapedGitdirPolicy'],
    'recursiveGitdirPolicy' => $fixture['recursiveGitdirPolicy'],
    'slashClassRejectedPolicy' => $fixture['slashClassRejectedPolicy'],
    'bracketUrlPolicy' => $fixture['bracketUrlPolicy'],
    'sectionsLoaded' => $fixture['sectionsLoaded'],
];
