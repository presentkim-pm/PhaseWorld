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

namespace kim\present\phaseworld\command;

use kim\present\phaseworld\PhaseWorld;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

final class PhaseWorldTemplateListCommand extends Command{

    public function __construct(
        private readonly PhaseWorld $plugin
    ){
        parent::__construct(
            "phaseworldtemplatelist",
            "Show phase world template list",
            "/phaseworldtemplatelist"
        );
        $this->setPermission("phaseworld.command");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        $sender->sendMessage(
            TextFormat::YELLOW . "Templates: "
            . implode(", ", array_keys($this->plugin->getTemplates()))
        );
    }
}
