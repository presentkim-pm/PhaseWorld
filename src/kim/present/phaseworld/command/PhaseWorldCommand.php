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

namespace kim\present\phaseworld\command;

use kim\present\libasynform\SimpleForm;
use kim\present\phaseworld\data\TemplateDataEnum;
use kim\present\phaseworld\PhaseWorld;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\network\mcpe\protocol\types\command\CommandHardEnum;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use RoMo\CommandCore\command\parameter\EnumParameter;
use RoMo\CommandCore\command\parameter\OneEnumParameter;
use RoMo\CommandCore\CommandCore;
use SOFe\AwaitGenerator\Await;

final class PhaseWorldCommand extends Command{

    public function __construct(
        private readonly PhaseWorld $plugin
    ){
        parent::__construct(
            "phaseworld",
            "PhaseWorld main command",
            "/phaseworld <list|create|reload> [args...]",
            ["phase"]
        );
        $this->setPermission("phaseworld.command");

        if(class_exists(CommandCore::class)){
            CommandCore::getInstance()->registerCommandOverload($this,
                // Overload 1: list [type]
                CommandCore::createOverload(
                    new OneEnumParameter("list"),
                    new EnumParameter("type", new CommandHardEnum("type", ["template", "instance"])),
                ),
                // Overload 2: create <template>
                CommandCore::createOverload(
                    new OneEnumParameter("create"),
                    new EnumParameter("template", TemplateDataEnum::getInstance())
                ),
                // Overload 3: reload
                CommandCore::createOverload(
                    new OneEnumParameter("reload")
                )
            );
        }
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if(empty($args)){
            throw new InvalidCommandSyntaxException();
        }

        $subCommand = strtolower(array_shift($args));
        switch($subCommand){
            case "list":
                $this->executeList($sender, $args);
                break;
            case "create":
                $this->executeCreate($sender, $args);
                break;
            case "reload":
                $this->executeReload($sender);
                break;
            default:
                throw new InvalidCommandSyntaxException();
        }
    }

    private function executeCreate(CommandSender $sender, array $args) : void{
        if(empty($args[0])){
            if($sender instanceof Player){
                $this->sendTemplateListForm($sender);
                return;
            }
            $sender->sendMessage(TextFormat::RED . "Usage: /phaseworld create <template_name>");
            return;
        }

        $templateName = strtolower($args[0]);
        if($this->plugin->getTemplate($templateName) === null){
            $sender->sendMessage(TextFormat::RED . "Template '$templateName' not found. Load it first.");
            return;
        }

        $worldName = $this->plugin->createInstance($templateName);
        if($worldName === null){
            $sender->sendMessage(TextFormat::RED . "Failed to create instance.");
            return;
        }

        $sender->sendMessage(TextFormat::GREEN . "Instance created to: $worldName");
        if($sender instanceof Player){
            $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
            if($world){
                $sender->teleport($world->getSafeSpawn());
                $sender->sendMessage(TextFormat::GRAY . "Teleported to instance.");
            }
        }
    }

    private function executeList(CommandSender $sender, array $args) : void{
        $type = strtolower($args[0] ?? "");
        if($type === "template"){
            $sender->sendMessage(
                TextFormat::YELLOW . "Templates: "
                . implode(", ", array_keys($this->plugin->getTemplates()))
            );
        }elseif($type === "instance"){
            if($sender instanceof Player){
                $this->sendInstanceListForm($sender);
            }else{
                $sender->sendMessage(
                    TextFormat::YELLOW . "Active Instances: "
                    . implode(", ", $this->plugin->getInstances())
                );
            }
        }else{
            $sender->sendMessage(TextFormat::RED . "Usage: /phaseworld list [template|instance]");
        }
    }

    private function executeReload(CommandSender $sender) : void{
        $this->plugin->loadTemplatesFromDirectory($this->plugin->getDataFolder() . "templates/");
        $sender->sendMessage(TextFormat::GREEN . "Reloaded phase world templates.");
    }

    public function sendTemplateListForm(Player $player) : void{
        Await::f2c(function() use ($player){
            $templates = PhaseWorld::getInstance()->getTemplates();

            $form = SimpleForm::create(
                "Create Phase World Instance",
                "Select a template to create instance:"
            );
            $templateNames = array_keys($templates);
            foreach($templateNames as $name){
                $form->addButton("$name");
            }

            $response = yield from $form->send($player);
            if($response !== null && isset($templateNames[$response])){
                $templateName = $templateNames[$response];
                $worldName = $this->plugin->createInstance($templateName);
                if($worldName === null){
                    $player->sendMessage(TextFormat::RED . "Failed to create instance.");
                    return;
                }

                $player->sendMessage(TextFormat::GREEN . "Instance created to: $worldName");
                $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
                if($world){
                    $player->teleport($world->getSafeSpawn());
                    $player->sendMessage(TextFormat::GRAY . "Teleported to instance.");
                }
            }
        });
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
