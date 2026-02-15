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
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use RoMo\CommandCore\command\parameter\EnumParameter;
use RoMo\CommandCore\CommandCore;
use SOFe\AwaitGenerator\Await;

final class PhaseWorldInstanceCreateCommand extends Command{

    public function __construct(
        private readonly PhaseWorld $plugin
    ){
        parent::__construct(
            "phaseworldinstancecreate",
            "Create phase world instance",
            "/phaseworldinstancecreate <template_name>"
        );
        $this->setPermission("phaseworld.command");

        if(class_exists(CommandCore::class)){
            CommandCore::getInstance()->registerCommandOverload($this, CommandCore::createOverload(
                new EnumParameter("template", TemplateDataEnum::getInstance())
            ));
        }
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if(empty($args[0])){
            if($sender instanceof Player){
                $this->sendTemplateListForm($sender);
                return;
            }

            throw new InvalidCommandSyntaxException();
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
            $sender->teleport($world->getSafeSpawn());
            $sender->sendMessage(TextFormat::GRAY . "Teleported to instance.");
        }
    }

    public function sendTemplateListForm(Player $player) : void{
        Await::f2c(function() use ($player){
            $templates = PhaseWorld::getInstance()->getTemplates();

            $form = SimpleForm::create(
                "Create Phase World Instance",
                "Select an template to create instance:"
            );
            $templateNames = array_keys($templates);
            foreach($templateNames as $name){
                $form->addButton("$name");
            }

            $response = yield from $form->send($player);
            if($response !== null && isset($templates[$response])){
                $worldName = $this->plugin->createInstance($templateNames[$response]);
                if($worldName === null){
                    $player->sendMessage(TextFormat::RED . "Failed to create instance.");
                    return;
                }

                $player->sendMessage(TextFormat::GREEN . "Instance created to: $worldName");
                $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
                $player->teleport($world->getSafeSpawn());
                $player->sendMessage(TextFormat::GRAY . "Teleported to instance.");
            }
        });
    }
}
