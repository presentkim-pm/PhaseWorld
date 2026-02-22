<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\phaseworld\data;

use kim\present\phaseworld\PhaseWorld;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\format\io\WorldProvider;
use pocketmine\world\World;

class TemplateData{
    /** @var array<int, ?ChunkData> Index: World::chunkHash($x, $z) */
    private array $chunks = [];

    public function __construct(
        private readonly WorldProvider $provider,
        private readonly WorldData $worldData,
        private readonly int $minY,
        private readonly int $maxY
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
        $hash = World::chunkHash($x, $z);
        if(array_key_exists($hash, $this->chunks)){
            return $this->chunks[$hash];
        }

        $loadedChunk = $this->provider->loadChunk($x, $z);
        if($loadedChunk !== null){
            $data = PhaseWorld::cloneChunkData($loadedChunk->getData());
            $this->chunks[$hash] = $data;
            return $data;
        }

        $this->chunks[$hash] = null;
        return null;
    }

    public function getAllChunks() : \Generator{
        return $this->provider->getAllChunks();
    }

    public function calculateChunkCount() : int{
        return $this->provider->calculateChunkCount();
    }

    public function close() : void{
        $this->provider->close();
    }
}
