<?php

declare(strict_types=1);

namespace authorname\pluginname;

use pocketmine\plugin\PluginBase;

final class Main extends PluginBase{
	protected function onLoad() : void{
		$this->getLogger()->info("Template plugin loaded!");
	}

	protected function onEnable() : void{
		$this->getLogger()->info("Template plugin enabled!");
	}

	protected function onDisable() : void{
		$this->getLogger()->info("Template plugin disabled!");
	}
}
