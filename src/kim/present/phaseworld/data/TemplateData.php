<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\phaseworld\data;

use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\World;

final readonly class TemplateData{
    /**
     * @param WorldData   $worldData
     * @param ChunkData[] $chunks Index: World::chunkHash($x, $z)
     * @param int         $minY
     * @param int         $maxY
     */
    public function __construct(
        private WorldData $worldData,
        private array $chunks = [],
        private int $minY = 0,
        private int $maxY = 256
    ){}

    public function getWorldData() : WorldData{
        return $this->worldData;
    }

    public function getMinY() : int{
        return $this->minY;
    }

    public function getMaxY() : int{
        return $this->maxY;
    }

    public function getChunk(int $x, int $z) : ?ChunkData{
        return $this->chunks[World::chunkHash($x, $z)] ?? null;
    }

    /** @return ChunkData[] */
    public function getChunks() : array{
        return $this->chunks;
    }
}
