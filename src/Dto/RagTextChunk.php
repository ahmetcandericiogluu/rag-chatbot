<?php

namespace App\Dto;

final readonly class RagTextChunk
{
    public function __construct(
        public int $chunkNo,
        public string $content,
        public ?string $heading = null,
        public ?int $startOffset = null,
        public ?int $endOffset = null,
    ) {
    }
}