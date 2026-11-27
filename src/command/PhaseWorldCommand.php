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

use kim\present\cameraapi\Camera;
use kim\present\libasynform\SimpleForm;
use kim\present\phaseworld\PhaseWorld;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
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
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if($args === []){
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

    /** @param string[] $args */
    private function executeCreate(CommandSender $sender, array $args) : void{
        if(!isset($args[0]) || $args[0] === ""){
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
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
            if($world !== null){
                $this->teleportWithCamera($sender, $world);
                $sender->sendMessage(TextFormat::GRAY . "Teleported to instance.");
            }
        }
    }

    /** @param string[] $args */
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
                    . implode(", ", array_keys($this->plugin->getInstances()))
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
            $templates = $this->plugin->getTemplates();

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
                $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName);
                if($world !== null){
                    $this->teleportWithCamera($player, $world);
                    $player->sendMessage(TextFormat::GRAY . "Teleported to instance.");
                }
            }
        }, null, [FormValidationException::class => static function() : void{}]);
    }

    public function sendInstanceListForm(Player $player) : void{
        Await::f2c(function() use ($player){
            $instances = $this->plugin->getInstances();

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
                $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($target);
                if($world !== null){
                    $this->teleportWithCamera($player, $world);
                    $player->sendMessage(TextFormat::GREEN . "Teleported to $target");
                }
            }
        }, null, [FormValidationException::class => static function() : void{}]);
    }

    /**
     * Teleports the player to the given world's safe spawn with a short camera fade effect.
     */
    private function teleportWithCamera(Player $player, World $world) : void{
        // Apply a quick fade transition using CameraAPI
        if(class_exists(Camera::class)){
            Camera::of($player)
                  ->fade()
                  ->in(0.3)
                  ->stay(0.1)
                  ->out(0.3)
                  ->send();
        }

        $player->teleport($world->getSafeSpawn());
    }
}
