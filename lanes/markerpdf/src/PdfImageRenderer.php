<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfImageRenderer
{
    private BboxGeometry $geometry;

    public function __construct(?BboxGeometry $geometry = null)
    {
        $this->geometry = $geometry ?? new BboxGeometry();
    }

    /**
     * Native boundary for marker.pdf.images::render_image scale calculation.
     */
    public function renderScale(float $dpi): float
    {
        if ($dpi <= 0.0) {
            throw new InvalidArgumentException('DPI must be greater than zero.');
        }

        return $dpi / 72.0;
    }

    /**
     * Derives the rendered page pixel dimensions that pypdfium reports after
     * page.render(scale=dpi / 72).to_pil().size for a PDF-point page box.
     *
     * @param list<float|int> $pageBbox
     * @return array{width: int, height: int}
     */
    public function renderedImageSize(array $pageBbox, float $dpi): array
    {
        $bbox = $this->bbox($pageBbox);
        $scale = $this->renderScale($dpi);

        return [
            'width' => (int) round(max(0.0, $bbox[2] - $bbox[0]) * $scale),
            'height' => (int) round(max(0.0, $bbox[3] - $bbox[1]) * $scale),
        ];
    }

    /**
     * Native boundary for marker.pdf.images::render_bbox_image crop scaling.
     *
     * @param list<float|int> $pageBbox
     * @param array{width?: int|float, height?: int|float}|list<int|float> $renderedImageSize
     * @param list<float|int> $bbox
     * @return list<float>
     */
    public function cropBboxForRenderedImage(array $pageBbox, array $renderedImageSize, array $bbox): array
    {
        $pageBbox = $this->bbox($pageBbox);
        $imageBbox = $this->renderedImageBbox($renderedImageSize);

        return $this->geometry->rescaleBbox($pageBbox, $imageBbox, $bbox);
    }

    /**
     * Plans the metadata around marker.pdf.images::render_bbox_image without
     * rasterizing through pypdfium2/PIL.
     *
     * @param list<float|int> $pageBbox
     * @param list<float|int> $bbox
     * @param array{width?: int|float, height?: int|float}|list<int|float>|null $renderedImageSize
     * @return array{dpi: float, scale: float, draw_annots: false, color_mode: string, rendered_image_bbox: list<float>, crop_bbox: list<float>, crop_width: float, crop_height: float}
     */
    public function renderBboxImagePlan(
        array $pageBbox,
        array $bbox,
        float $dpi = 96.0,
        ?array $renderedImageSize = null
    ): array {
        $size = $renderedImageSize ?? $this->renderedImageSize($pageBbox, $dpi);
        $imageBbox = $this->renderedImageBbox($size);
        $cropBbox = $this->cropBboxForRenderedImage($pageBbox, $size, $bbox);

        return [
            'dpi' => $dpi,
            'scale' => $this->renderScale($dpi),
            'draw_annots' => false,
            'color_mode' => 'RGB',
            'rendered_image_bbox' => $imageBbox,
            'crop_bbox' => $cropBbox,
            'crop_width' => max(0.0, $cropBbox[2] - $cropBbox[0]),
            'crop_height' => max(0.0, $cropBbox[3] - $cropBbox[1]),
        ];
    }

    /**
     * @param array{width?: int|float, height?: int|float}|list<int|float> $renderedImageSize
     * @return list<float>
     */
    private function renderedImageBbox(array $renderedImageSize): array
    {
        $width = $renderedImageSize['width'] ?? $renderedImageSize[0] ?? null;
        $height = $renderedImageSize['height'] ?? $renderedImageSize[1] ?? null;

        if ((!is_int($width) && !is_float($width)) || (!is_int($height) && !is_float($height))) {
            throw new InvalidArgumentException('Rendered image size must include numeric width and height.');
        }
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Rendered image width and height must be greater than zero.');
        }

        return [0.0, 0.0, (float) $width, (float) $height];
    }

    /**
     * @param array<mixed> $bbox
     * @return list<float>
     */
    private function bbox(array $bbox): array
    {
        $values = array_values($bbox);
        if (count($values) !== 4) {
            throw new InvalidArgumentException('bbox must have 4 elements');
        }

        foreach ($values as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new InvalidArgumentException('bbox values must be numeric');
            }
        }

        return array_map(static fn (float|int $value): float => (float) $value, $values);
    }
}
