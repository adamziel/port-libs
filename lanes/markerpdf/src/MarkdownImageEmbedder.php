<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use Stringable;

final class MarkdownImageEmbedder
{
    /**
     * Native boundary for marker_app.py::markdown_insert_images.
     *
     * @param array<string, mixed> $images Values may be raw PNG bytes, Stringable bytes, or arrays with
     *                                     a `bytes`, `data`, or `content` string.
     */
    public function markdownInsertImages(string $markdown, array $images): string
    {
        preg_match_all(
            '/(!\[(?P<image_title>[^\]]+)\]\((?P<image_path>[^\)"\s]+)\s*([^\)]*)\))/',
            $markdown,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $imageMarkdown = $match[1];
            $imageAlt = $match['image_title'];
            $imagePath = $match['image_path'];

            if (!array_key_exists($imagePath, $images)) {
                continue;
            }

            $markdown = str_replace($imageMarkdown, $this->imageToHtml($images[$imagePath], $imageAlt), $markdown);
        }

        return $markdown;
    }

    public function imageToHtml(mixed $image, string $imageAlt): string
    {
        $encoded = base64_encode($this->imageBytes($image));

        return '<img src="data:image/png;base64,' . $encoded . '" alt="' . $imageAlt . '" style="max-width: 100%;">';
    }

    private function imageBytes(mixed $image): string
    {
        if (is_string($image)) {
            return $image;
        }
        if ($image instanceof Stringable) {
            return (string) $image;
        }
        if (is_array($image)) {
            foreach (['bytes', 'data', 'content'] as $key) {
                if (isset($image[$key]) && is_string($image[$key])) {
                    return $image[$key];
                }
            }
        }

        throw new InvalidArgumentException('Image payload must be PNG bytes for marker app embedding.');
    }
}
