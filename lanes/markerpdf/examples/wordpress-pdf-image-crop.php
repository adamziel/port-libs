<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();
$cropPlan = $renderer->renderBboxImagePlan(
    [0.0, 0.0, 600.0, 800.0],
    [60.0, 100.0, 280.0, 220.0],
    96.0,
    ['width' => 1200, 'height' => 1600]
);

$crop = htmlspecialchars(implode(',', $cropPlan['crop_bbox']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$scale = htmlspecialchars(number_format($cropPlan['scale'], 6, '.', ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-crop="' . $crop . '" data-marker-render-scale="' . $scale . '">';
echo '<img src="0_image_0.png" alt="Extracted PDF figure"/></figure>' . "\n";
echo "<!-- /wp:image -->\n";
