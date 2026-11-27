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

use kim\present\phaseworld\command\PhaseWorldCommand;
use kim\present\phaseworld\data\TemplateData;
use kim\present\phaseworld\event\PhaseWorldInstanceCreateEvent;
use kim\present\phaseworld\task\AsyncDirectoryDeleteTask;
use kim\present\phaseworld\world\PhaseProviderEntry;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\ChunkData;

final class PhaseWorld extends PluginBase{
    use SingletonTrait;

    public const PHASE_INSTANCE_DIR = ".phase_instance/";

    /** @var array<string, TemplateData> template name => template data */
    private array $worldTemplates = [];

    /** @var array<string, string> world name (with the instance directory prefix) => template name */
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
        $files = scandir($dir);
        if($files === false){
            return;
        }

        foreach(array_diff($files, ['.', '..']) as $file){
            $path = $dir . $file;
            if(is_dir($path)){
                if(isset($this->worldTemplates[strtolower($file)])){
                    continue; // already loaded (e.g. on /phaseworld reload)
                }
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

        $files = scandir($phaseBaseDir);
        if($files === false){
            return;
        }

        foreach(array_diff($files, ['.', '..']) as $file){
            if(is_dir($phaseBaseDir . $file)){
                $this->removeInstance($file);
            }
        }
    }

    public function createInstance(string $templateName) : ?string{
        $instanceId = $templateName . "#" . bin2hex(random_bytes(6));
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

    /**
     * Forgets the instance and deletes its directory in the background.
     *
     * @param string $instanceName The instance directory name (`template#id`); a world name with the
     *                             {@link PHASE_INSTANCE_DIR} prefix is accepted too.
     */
    public function removeInstance(string $instanceName) : void{
        $instanceName = basename($instanceName);
        unset($this->instances[self::PHASE_INSTANCE_DIR . $instanceName]);

        $worldDir = $this->getServer()->getDataPath() . "worlds/" . self::PHASE_INSTANCE_DIR . $instanceName;
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

        $matching = $this->getServer()->getWorldManager()->getProviderManager()->getMatchingProviders($path);
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

    /** @return array<string, string> world name => template name */
    public function getInstances() : array{
        return $this->instances;
    }

    /** @return array<string, TemplateData> template name => template data */
    public function getTemplates() : array{
        return $this->worldTemplates;
    }
}
