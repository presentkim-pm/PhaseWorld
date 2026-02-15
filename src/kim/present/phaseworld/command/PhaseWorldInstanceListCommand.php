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

use kim\present\libasynform\SimpleForm;
use kim\present\phaseworld\PhaseWorld;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;

final class PhaseWorldInstanceListCommand extends Command{

    public function __construct(
        private readonly PhaseWorld $plugin
    ){
        parent::__construct(
            "phaseworldinstancelist",
            "Show phase world instance list",
            "/phaseworldinstancelist"
        );
        $this->setPermission("phaseworld.command");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if($sender instanceof Player){
            $this->sendInstanceListForm($sender);
        }else{
            $sender->sendMessage(
                TextFormat::YELLOW . "Active Instances: "
                . implode(", ", $this->plugin->getInstances())
            );
        }
    }

    public function sendInstanceListForm(Player $player) : void{
        Await::f2c(function() use ($player){
            $instances = PhaseWorld::getInstance()->getInstances();

            $form = SimpleForm::create(
                "Phase World Instances",
                "Select an instance to teleport:"
            );
            $instanceNames = array_keys($instances);
            foreach($instanceNames as $worldName){
                $form->addButton(str_replace(PhaseWorld::PHASE_INSTANCE_DIR, "", $worldName));
            }

            $response = yield from $form->send($player);
            if($response !== null && isset($instanceNames[$response])){
                $target = $instanceNames[$response];
                $world = Server::getInstance()->getWorldManager()->getWorldByName($target);
                if($world){
                    $player->teleport($world->getSafeSpawn());
                    $player->sendMessage(TextFormat::GREEN . "Teleported to $target");
                }
            }
        });
    }
}
