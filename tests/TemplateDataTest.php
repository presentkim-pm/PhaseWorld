<?php

declare(strict_types=1);

namespace kim\present\phaseworld\tests;

use kim\present\phaseworld\data\TemplateData;
use kim\present\phaseworld\PhaseWorld;
use PHPUnit\Framework\TestCase;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\data\BedrockWorldData;
use pocketmine\world\format\io\LoadedChunkData;
use pocketmine\world\format\io\WorldProvider;
use pocketmine\world\WorldCreationOptions;

final class TemplateDataTest extends TestCase{

    private string $dir;

    protected function setUp() : void{
        $this->dir = sys_get_temp_dir() . "/phaseworld-template-" . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown() : void{
        foreach(glob($this->dir . "/*") ?: [] as $file){
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function createChunkData() : ChunkData{
        return new ChunkData([], true, [CompoundTag::create()->setInt("id", 1)], [CompoundTag::create()->setInt("x", 2)]);
    }

    /** @param array<int, ChunkData> $chunks */
    private function createProvider(array $chunks, int &$loads) : WorldProvider{
        return new class($chunks, $loads) implements WorldProvider{
            /** @param array<int, ChunkData> $chunks */
            public function __construct(private array $chunks, private int &$loads){}

            public function getWorldMinY() : int{ return 0; }

            public function getWorldMaxY() : int{ return 256; }

            public function getPath() : string{ return ""; }

            public function loadChunk(int $chunkX, int $chunkZ) : ?LoadedChunkData{
                ++$this->loads;
                $data = $this->chunks[$chunkX] ?? null;
                return $data === null ? null : new LoadedChunkData($data, false, LoadedChunkData::FIXER_FLAG_NONE);
            }

            public function doGarbageCollection() : void{}

            public function getWorldData() : \pocketmine\world\format\io\WorldData{ throw new \LogicException(); }

            public function close() : void{}

            public function getAllChunks(bool $skipCorrupted = false, ?\Logger $logger = null) : \Generator{
                yield from [];
            }

            public function calculateChunkCount() : int{ return count($this->chunks); }
        };
    }

    public function testCloneChunkDataCopiesNbt() : void{
        $source = $this->createChunkData();
        $clone = PhaseWorld::cloneChunkData($source);

        self::assertTrue($clone->isPopulated());
        self::assertEquals($source->getEntityNBT(), $clone->getEntityNBT());
        self::assertNotSame($source->getEntityNBT()[0], $clone->getEntityNBT()[0]);
        self::assertNotSame($source->getTileNBT()[0], $clone->getTileNBT()[0]);
    }

    public function testChunksAreLoadedOnceAndMissingChunksAreCached() : void{
        $loads = 0;
        $provider = $this->createProvider([0 => $this->createChunkData()], $loads);
        BedrockWorldData::generate($this->dir, "t", new WorldCreationOptions());
        $template = new TemplateData($provider, new BedrockWorldData($this->dir . "/level.dat"), 0, 256);

        $first = $template->getChunk(0, 0);
        self::assertNotNull($first);
        self::assertSame($first, $template->getChunk(0, 0));
        self::assertSame(1, $loads);

        self::assertNull($template->getChunk(5, 0));
        self::assertNull($template->getChunk(5, 0));
        self::assertSame(2, $loads);

        self::assertSame(1, $template->calculateChunkCount());
    }
}
