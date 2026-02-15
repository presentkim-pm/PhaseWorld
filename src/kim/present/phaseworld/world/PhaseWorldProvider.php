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

namespace kim\present\phaseworld\world;

use kim\present\phaseworld\data\TemplateData;
use kim\present\phaseworld\PhaseWorld;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\LoadedChunkData;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\format\io\WritableWorldProvider;
use pocketmine\world\World;

final class PhaseWorldProvider implements WritableWorldProvider{
    private ?TemplateData $template = null;
    private WorldData $worldData;
    private int $minY = 0;
    private int $maxY = 256;
    private bool $closed = false;

    public function __construct(
        private readonly string $path,
        private readonly PhaseWorld $plugin
    ){
        $parts = explode("#", basename($path));
        if(count($parts) !== 2){
            throw new \RuntimeException("Invalid PhaseWorld path format: $path");
        }
        $templateName = $parts[0];

        $this->template = $this->plugin->getTemplate($templateName);
        if($this->template === null){
            throw new \RuntimeException("Template '$templateName' not loaded (requested by $path)");
        }

        // Clone world data for this instance
        $this->worldData = clone $this->template->getWorldData();
        $this->worldData->setName(basename($path));

        $this->minY = $this->template->getMinY();
        $this->maxY = $this->template->getMaxY();
    }

    public function getWorldMinY() : int{
        return $this->minY;
    }

    public function getWorldMaxY() : int{
        return $this->maxY;
    }

    public function getPath() : string{
        return $this->path;
    }

    public function loadChunk(int $chunkX, int $chunkZ) : ?LoadedChunkData{
        if($this->template === null){
            return null;
        }

        $chunk = $this->template->getChunk($chunkX, $chunkZ);
        if($chunk === null){
            return null;
        }

        return new LoadedChunkData(
            $this->plugin->cloneChunkData($chunk),
            false,
            LoadedChunkData::FIXER_FLAG_NONE
        );
    }

    public function saveChunk(int $chunkX, int $chunkZ, ChunkData $chunkData, int $dirtyFlags) : void{
        // Zero-IO: Do nothing. Changes are kept in memory only and discarded on unload.
    }

    public function doGarbageCollection() : void{}

    public function getWorldData() : WorldData{
        return $this->worldData;
    }

    public function close() : void{
        if($this->closed){
            return;
        }
        $this->closed = true;

        $this->plugin->removeInstance(basename($this->path));
    }

    public function getAllChunks(bool $skipCorrupted = false, ?\Logger $logger = null) : \Generator{
        if($this->template === null){
            return;
        }

        foreach($this->template->getChunks() as $hash => $chunkData){
            $x = null;
            $z = null;
            World::getXZ($hash, $x, $z);
            yield [$x, $z] => new LoadedChunkData(PhaseWorld::cloneChunkData($chunkData), false, 0);
        }
    }

    public function calculateChunkCount() : int{
        return $this->template ? count($this->template->getChunks()) : 0;
    }
}
