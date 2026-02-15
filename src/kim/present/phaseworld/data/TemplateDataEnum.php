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

use pocketmine\network\mcpe\protocol\types\command\CommandHardEnum;
use pocketmine\utils\SingletonTrait;

final class TemplateDataEnum extends CommandHardEnum{
    use SingletonTrait;

    /**
     * @var string[]             $values
     *
     * @phpstan-var list<string> $values
     */
    private array $values = [];

    private function __construct(){
        parent::__construct("phase_world_template", []);
        self::setInstance($this);
    }

    public function register(string $templateName) : void{
        $this->values[] = strtolower($templateName);
    }

    public function getValues() : array{
        return $this->values;
    }
}
