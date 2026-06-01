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
    'posixUrlPolicy' => $fixture['posixUrlPolicy'],
    'escapedHyphenUrlPolicy' => $fixture['escapedHyphenUrlPolicy'],
    'reversedRangeStartUrlPolicy' => $fixture['reversedRangeStartUrlPolicy'],
    'reversedRangeMiddleUrlPolicy' => $fixture['reversedRangeMiddleUrlPolicy'],
    'legacyBytePolicy' => $fixture['legacyBytePolicy'],
    'literalTildePathPolicy' => $fixture['literalTildePathPolicy'],
    'installPrefixPathPolicy' => $fixture['installPrefixPathPolicy'],
    'literalPrefixPathPolicy' => $fixture['literalPrefixPathPolicy'],
    'backslashUrlSlashPolicy' => $fixture['backslashUrlSlashPolicy'],
    'backslashUrlLiteralPolicy' => $fixture['backslashUrlLiteralPolicy'],
    'unboundedDoubleStarRejectedPolicy' => $fixture['unboundedDoubleStarRejectedPolicy'],
    'invalidPosixPolicy' => $fixture['invalidPosixPolicy'],
    'unclosedBracketPolicy' => $fixture['unclosedBracketPolicy'],
    'trailingBackslashUrlPolicy' => $fixture['trailingBackslashUrlPolicy'],
    'optionalPrefixPolicy' => $fixture['optionalPrefixPolicy'],
    'backslashGitdirSlashPolicy' => $fixture['backslashGitdirSlashPolicy'],
    'backslashGitdirWildcardPolicy' => $fixture['backslashGitdirWildcardPolicy'],
    'symlinkGitdirSupported' => $fixture['symlinkGitdirSupported'],
    'symlinkRealpathPolicy' => $fixture['symlinkRealpathPolicy'],
    'symlinkLiteralPolicy' => $fixture['symlinkLiteralPolicy'],
    'symlinkIcasePolicy' => $fixture['symlinkIcasePolicy'],
    'sectionsLoaded' => $fixture['sectionsLoaded'],
];
