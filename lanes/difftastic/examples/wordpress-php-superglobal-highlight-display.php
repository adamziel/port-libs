<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'PHP'
<?php

function acme_card_rest_nonce(): string
{
    return sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
}
PHP;

$after = <<<'PHP'
<?php

function acme_card_rest_nonce(): string
{
    $nonce = sanitize_text_field(wp_unslash($_REQUEST['nonce'] ?? ''));
    $referer = sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'] ?? ''));

    return $nonce . ':' . $referer;
}
PHP;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/includes/rest-nonce.php',
    'PHP',
    ['language' => 'php'],
);
