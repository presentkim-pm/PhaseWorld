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

namespace kim\present\phaseworld;

use kim\present\phaseworld\command\PhaseWorldInstanceCreateCommand;
use kim\present\phaseworld\command\PhaseWorldInstanceListCommand;
use kim\present\phaseworld\command\PhaseWorldTemplateListCommand;
use kim\present\phaseworld\command\PhaseWorldTemplateReloadCommand;
use kim\present\phaseworld\data\TemplateData;
use kim\present\phaseworld\data\TemplateDataEnum;
use kim\present\phaseworld\task\AsyncDirectoryDeleteTask;
use kim\present\phaseworld\world\PhaseProviderEntry;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\WorldProviderManagerEntry;
use pocketmine\world\World;

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

        $this->getServer()->getCommandMap()->registerAll($this->getName(), [
            new PhaseWorldTemplateReloadCommand($this),
            new PhaseWorldTemplateListCommand($this),
            new PhaseWorldInstanceCreateCommand($this),
            new PhaseWorldInstanceListCommand($this)
        ]);
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

    /**
     * Create phase world instance from template name.
     *
     * @param string $templateName
     *
     * @return string|null If create successfully, it returns world name, or null
     */
    public function createInstance(string $templateName) : ?string{
        // Generate a unique ID for the instance
        $instanceId = $templateName . "#" . substr(hash('sha256', (string) mt_rand()), 0, 12);

        // Use relative path to hide it in .phase_instance folder
        $worldName = self::PHASE_INSTANCE_DIR . $instanceId;
        $instancePath = $this->getServer()->getDataPath() . "worlds/" . $worldName;

        if(!is_dir(dirname($instancePath))){
            mkdir(dirname($instancePath), 0777, true);
        }

        // create directory
        if(!mkdir($instancePath)){
            return null;
        }

        // Load the world
        if(!$this->getServer()->getWorldManager()->loadWorld($worldName)){
            return null;
        }

        $this->instances[$worldName] = $templateName;
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

        // Use the first matching provider
        /** @var WorldProviderManagerEntry $entry */
        $entry = reset($matching);

        try{
            $provider = $entry->fromPath($path, $this->getLogger());
        }catch(\Throwable $e){
            $this->getLogger()->error("Failed to open template provider: " . $e->getMessage());
            return false;
        }

        $this->getLogger()->info("Caching template '$name' from '$path'...");
        $chunks = [];
        $count = 0;

        // Iterate all chunks
        // getAllChunks returns generator with key=[x, z] and value=LoadedChunkData
        foreach($provider->getAllChunks(true, $this->getLogger()) as $xz => $chunkData){
            [$x, $z] = $xz;
            $chunks[World::chunkHash($x, $z)] = self::cloneChunkData($chunkData->getData());
            $count++;
        }

        $this->worldTemplates[$name] = new TemplateData(
            clone $provider->getWorldData(),
            $chunks,
            $provider->getWorldMinY(),
            $provider->getWorldMaxY()
        );
        TemplateDataEnum::getInstance()->register($name);

        $provider->close();
        $this->getLogger()->info("Cached $count chunks for template '$name'.");
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

    /** @return array<string, TemplateData> template name => template data */
    public function getTemplates() : array{
        return $this->worldTemplates;
    }
}
