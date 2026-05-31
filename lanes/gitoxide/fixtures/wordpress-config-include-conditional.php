<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitConfig;

$root = sys_get_temp_dir() . '/port-libs-wordpress-config-' . bin2hex(random_bytes(6));
$repo = $root . '/wp-content.git';
$gitDir = $repo . '/.git';
mkdir($gitDir, 0777, true);

$write = static function (string $path, string $contents): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $contents);
};

$write($repo . '/deploy-branch.config', <<<CFG
[wordpress]
preview = enabled
[merge]
conflictStyle = zdiff3
CFG);

$write($repo . '/remote-policy.config', <<<CFG
[http]
extraHeader = X-WP-Deploy: staging
[transfer]
fsckObjects = true
CFG);

$write($gitDir . '/config', <<<CFG
[core]
repositoryformatversion = 0
[merge]
conflictStyle = diff3
[remote "origin"]
url = https://git.example.test/wp-content.git
[includeIf "onbranch:deploy/"]
path = ../deploy-branch.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/**"]
path = ../remote-policy.config
CFG);

$config = GitConfig::fromFile($gitDir . '/config', [
    'gitDir' => $gitDir,
    'homeDir' => $root,
    'branchName' => 'refs/heads/deploy/site-a',
]);

return [
    'activeBranch' => 'refs/heads/deploy/site-a',
    'remoteUrl' => $config->value('remote', 'origin', 'url'),
    'preview' => $config->value('wordpress', null, 'preview'),
    'conflictStyle' => $config->value('merge', null, 'conflictStyle'),
    'httpExtraHeader' => $config->value('http', null, 'extraHeader'),
    'transferFsckObjects' => $config->value('transfer', null, 'fsckObjects'),
    'sectionsLoaded' => array_map(
        static fn (array $section): string => $section['subsection'] === null
            ? $section['name']
            : $section['name'] . '.' . $section['subsection'],
        $config->sections(),
    ),
];
