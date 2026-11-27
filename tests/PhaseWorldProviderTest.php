<?php

declare(strict_types=1);

namespace kim\present\phaseworld\tests;

use kim\present\phaseworld\data\TemplateData;
use kim\present\phaseworld\PhaseWorld;
use kim\present\phaseworld\world\PhaseWorldProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;
use pocketmine\world\format\io\data\BedrockWorldData;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\WorldCreationOptions;

final class PhaseWorldProviderTest extends TestCase{

    private string $dir;

    protected function setUp() : void{
        $this->dir = sys_get_temp_dir() . "/phaseworld-test-" . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown() : void{
        foreach(glob($this->dir . "/*") ?: [] as $file){
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function createWorldData(string $name) : WorldData{
        BedrockWorldData::generate($this->dir, $name, new WorldCreationOptions());
        return new BedrockWorldData($this->dir . "/level.dat");
    }

    public function testClonedWorldDataIsIndependentOfTheTemplate() : void{
        $template = $this->createWorldData("template");
        $template->setTime(100);
        $template->setSpawn(new Vector3(1, 2, 3));

        $a = PhaseWorldProvider::cloneWorldData($template);
        $b = PhaseWorldProvider::cloneWorldData($template);
        $a->setName("a");
        $a->setTime(500);
        $a->setSpawn(new Vector3(10, 20, 30));
        $b->setName("b");

        self::assertSame("template", $template->getName());
        self::assertSame(100, $template->getTime());
        self::assertSame(1, $template->getSpawn()->getFloorX());
        self::assertSame("a", $a->getName());
        self::assertSame(500, $a->getTime());
        self::assertSame("b", $b->getName());
        self::assertSame(100, $b->getTime());
        self::assertSame(1, $b->getSpawn()->getFloorX());
    }

    public function testTemplateDataKeepsTheWorldDataBounds() : void{
        $worldData = $this->createWorldData("template");
        $provider = (new \ReflectionClass(PhaseWorldProvider::class))->newInstanceWithoutConstructor();
        $template = new TemplateData($provider, $worldData, -64, 320);

        self::assertSame($worldData, $template->getWorldData());
        self::assertSame(-64, $template->getMinY());
        self::assertSame(320, $template->getMaxY());
    }

    public function testInstanceDirectoryNameFormat() : void{
        self::assertSame(".phase_instance/", PhaseWorld::PHASE_INSTANCE_DIR);
    }
}
