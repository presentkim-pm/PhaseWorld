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

use kim\present\phaseworld\PhaseWorld;
use pocketmine\world\format\io\WorldProvider;
use pocketmine\world\format\io\WorldProviderManagerEntry;

final class PhaseProviderEntry extends WorldProviderManagerEntry{
    public function __construct(private readonly PhaseWorld $plugin){
        parent::__construct(function(string $path) use ($plugin) : bool{
            // Normalize path separators
            $normalizedPath = str_replace("\\", "/", $path);
            
            // Check directory constraint
            if(!str_contains($normalizedPath, PhaseWorld::PHASE_INSTANCE_DIR)){
                return false;
            }
            
            // Check name pattern: template#id
            $parts = explode("#", basename($path));
            if(count($parts) !== 2){
                return false;
            }
            
            return $plugin->getTemplate($parts[0]) !== null;
        });
    }

    public function fromPath(string $path, \Logger $logger) : WorldProvider{
        return new PhaseWorldProvider($path, $this->plugin);
    }
}
