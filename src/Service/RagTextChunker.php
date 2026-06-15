<?php

namespace App\Service;

use App\Dto\RagTextChunk;

final readonly class RagTextChunker
{
    private const TARGET_CHARS = 15;
    private const MAX_CHARS = 50;

    /**
     * @return RagTextChunk[]
     */
    public function chunk(string $text): array
    {
        $text = $this->normalizeText($text);

        if ($text === '') {
            return [];
        }

        $blocks = $this->splitIntoBlocks($text);

        $chunks = [];
        $buffer = '';
        $currentHeading = null;
        $chunkNo = 1;

        foreach ($blocks as $block) {
            if ($this->isHeading($block)) {
                if (trim($buffer) !== '') {
                    $chunks[] = new RagTextChunk(
                        chunkNo: $chunkNo++,
                        content: trim($buffer),
                        heading: $currentHeading,
                    );

                    $buffer = '';
                }

                $currentHeading = trim(ltrim($block, "# \t"));

                continue;
            }

            $candidate = trim($buffer . "\n\n" . $block);

            if (mb_strlen($candidate, 'UTF-8') > self::MAX_CHARS && trim($buffer) !== '') {
                $chunks[] = new RagTextChunk(
                    chunkNo: $chunkNo++,
                    content: trim($buffer),
                    heading: $currentHeading,
                );

                $buffer = $block;

                continue;
            }

            $buffer = $candidate;
        }

        if (trim($buffer) !== '') {
            $chunks[] = new RagTextChunk(
                chunkNo: $chunkNo,
                content: trim($buffer),
                heading: $currentHeading,
            );
        }

        return $this->splitOversizedChunks($chunks);
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return string[]
     */
    private function splitIntoBlocks(string $text): array
    {
        $blocks = preg_split("/\n\s*\n/u", $text, -1, PREG_SPLIT_NO_EMPTY);

        if (!$blocks) {
            return [];
        }

        return array_values(array_map('trim', $blocks));
    }

    private function isHeading(string $block): bool
    {
        if (str_starts_with(trim($block), '#')) {
            return true;
        }

        $length = mb_strlen($block, 'UTF-8');

        return $length <= 80 && !str_contains($block, '.') && !str_contains($block, '?');
    }

    /**
     * @param RagTextChunk[] $chunks
     * @return RagTextChunk[]
     */
    private function splitOversizedChunks(array $chunks): array
    {
        $result = [];
        $chunkNo = 1;

        foreach ($chunks as $chunk) {
            if (mb_strlen($chunk->content, 'UTF-8') <= self::MAX_CHARS) {
                $result[] = new RagTextChunk(
                    chunkNo: $chunkNo++,
                    content: $chunk->content,
                    heading: $chunk->heading,
                    startOffset: $chunk->startOffset,
                    endOffset: $chunk->endOffset,
                );

                continue;
            }

            $sentences = preg_split('/(?<=[.!?])\s+/u', $chunk->content, -1, PREG_SPLIT_NO_EMPTY);
            $buffer = '';

            foreach ($sentences ?: [] as $sentence) {
                $candidate = trim($buffer . ' ' . $sentence);

                if (mb_strlen($candidate, 'UTF-8') > self::TARGET_CHARS && trim($buffer) !== '') {
                    $result[] = new RagTextChunk(
                        chunkNo: $chunkNo++,
                        content: trim($buffer),
                        heading: $chunk->heading,
                    );

                    $buffer = $sentence;

                    continue;
                }

                $buffer = $candidate;
            }

            if (trim($buffer) !== '') {
                $result[] = new RagTextChunk(
                    chunkNo: $chunkNo++,
                    content: trim($buffer),
                    heading: $chunk->heading,
                );
            }
        }

        return $result;
    }
}