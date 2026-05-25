<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$before = <<<'PHP'
<?php

final class Acme_Block_Renderer
{
    public function render(array $attributes): string
    {
        return render_block($attributes);
    }
}
PHP;

$after = <<<'PHP'
<?php

final class Acme_Block_Renderer
{
    public function render(array $attributes): string
    {
        $this->enqueue_assets();
        return render_block($this->normalize_attributes($attributes));
    }
}
PHP;

echo (new JsonDiffRenderer())->renderFileDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/src/BlockRenderer.php',
    'PHP',
    ['language' => 'php'],
);
