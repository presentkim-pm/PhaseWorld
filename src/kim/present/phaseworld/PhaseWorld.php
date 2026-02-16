<?php

declare(strict_types=1);

namespace kim\present\phaseworld;

use kim\present\phaseworld\command\PhaseWorldCommand;
use kim\present\phaseworld\data\TemplateData;
use kim\present\phaseworld\data\TemplateDataEnum;
use kim\present\phaseworld\event\PhaseWorldInstanceCreateEvent;
use kim\present\phaseworld\task\AsyncDirectoryDeleteTask;
use kim\present\phaseworld\world\PhaseProviderEntry;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\ChunkData;

final class PhaseWorld extends PluginBase{
    use SingletonTrait;

    public const PHASE_INSTANCE_DIR = ".phase_instance/";

    /** @var array<string, TemplateData> template name => template data */
    private array $worldTemplates = [];

    /** @var array<string, string> instance_id => template_name */
    private array $instances = [];

    protected function onLoad() : void{
        self::setInstance($this);
    }

    protected function onEnable() : void{
        $this->getServer()->getWorldManager()->getProviderManager()
             ->addProvider(new PhaseProviderEntry($this), "phase");
        $this->getLogger()->debug("Registered 'phase' world provider.");

        // Setup template directory
        $templateDir = $this->getDataFolder() . "templates/";
        if(!is_dir($templateDir)){
            mkdir($templateDir, 0777, true);
        }

        // Delay loading to ensure all custom block plugins are loaded
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($templateDir) : void{
                // Auto-load templates
                $this->loadTemplatesFromDirectory($templateDir);

                // Cleanup stale phase instances
                $this->cleanupStaleInstances();
            }
        ), 1);

        $this->getServer()->getCommandMap()->register($this->getName(), new PhaseWorldCommand($this));
    }

    protected function onDisable() : void{
        foreach($this->worldTemplates as $template){
            $template->close();
        }
        $this->worldTemplates = [];
    }

    public function loadTemplatesFromDirectory(string $dir) : void{
        foreach(array_diff(scandir($dir), ['.', '..']) as $file){
            $path = $dir . $file;
            if(is_dir($path)){
                $this->getLogger()->info("Auto-loading template: $file");
                if($this->loadTemplate($file, $path)){
                    $this->getLogger()->info("Loaded template: $file");
                }else{
                    $this->getLogger()->warning("Failed to load template: $file");
                }
            }
        }
    }

    private function cleanupStaleInstances() : void{
        $phaseBaseDir = $this->getServer()->getDataPath() . "worlds/" . self::PHASE_INSTANCE_DIR;
        if(!is_dir($phaseBaseDir)){
            return;
        }

        foreach(array_diff(scandir($phaseBaseDir), ['.', '..']) as $file){
            if(is_dir($phaseBaseDir . $file)){
                $this->removeInstance($file);
            }
        }
    }

    public function createInstance(string $templateName) : ?string{
        $instanceId = $templateName . "#" . substr(hash('sha256', (string) mt_rand()), 0, 12);
        $worldName = self::PHASE_INSTANCE_DIR . $instanceId;
        $instancePath = $this->getServer()->getDataPath() . "worlds/" . $worldName;

        if(!is_dir(dirname($instancePath))){
            mkdir(dirname($instancePath), 0777, true);
        }

        if(!mkdir($instancePath)){
            return null;
        }

        if(!$this->getServer()->getWorldManager()->loadWorld($worldName)){
            $this->getServer()->getAsyncPool()->submitTask(new AsyncDirectoryDeleteTask($instancePath));
            return null;
        }

        $this->instances[$worldName] = $templateName;

        $world = $this->getServer()->getWorldManager()->getWorldByName($worldName);
        if($world !== null){
            (new PhaseWorldInstanceCreateEvent($world, $templateName))->call();
        }

        return $worldName;
    }

    public function removeInstance(string $worldName) : void{
        if(isset($this->instances[$worldName])){
            unset($this->instances[$worldName]);
        }

        $worldDir = $this->getServer()->getDataPath() . "worlds/" . self::PHASE_INSTANCE_DIR . $worldName;
        if(is_dir($worldDir)){
            $this->getServer()->getAsyncPool()->submitTask(new AsyncDirectoryDeleteTask($worldDir));
        }
    }

    public function getTemplate(string $name) : ?TemplateData{
        return $this->worldTemplates[$name] ?? null;
    }

    public function loadTemplate(string $name, string $path) : bool{
        $name = strtolower($name);
        if(isset($this->worldTemplates[$name])){
            return false;
        }

        $matching = Server::getInstance()->getWorldManager()->getProviderManager()->getMatchingProviders($path);
        if(count($matching) === 0){
            $this->getLogger()->error("No suitable provider found for template path: $path");
            return false;
        }

        $entry = reset($matching);

        try{
            $provider = $entry->fromPath($path, $this->getLogger());
        }catch(\Throwable $e){
            $this->getLogger()->error("Failed to open template provider: " . $e->getMessage());
            return false;
        }

        $this->getLogger()->info("Opened template '$name' from '$path'. (Lazy Loading)");

        $this->worldTemplates[$name] = new TemplateData(
            $provider,
            clone $provider->getWorldData(),
            $provider->getWorldMinY(),
            $provider->getWorldMaxY()
        );
        TemplateDataEnum::getInstance()->register($name);

        return true;
    }

    public static function cloneChunkData(ChunkData $source) : ChunkData{
        return new ChunkData(
            Utils::cloneObjectArray($source->getSubChunks()),
            $source->isPopulated(),
            Utils::cloneObjectArray($source->getEntityNBT()),
            Utils::cloneObjectArray($source->getTileNBT())
        );
    }

    public function getInstances() : array{
        return $this->instances;
    }

    public function getTemplates() : array{
        return $this->worldTemplates;
    }
}
