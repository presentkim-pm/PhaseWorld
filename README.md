# PhaseWorld

**PhaseWorld** is a high-performance virtual world instance loader for PocketMine-MP. It is designed to create temporary, volatile world instances from a template with near-zero disk I/O and instant loading times.

## 🚀 Key Features

*   **Zero-IO Instantiation**: Creates world instances without copying region files. Only a lightweight dummy folder is created.
*   **Memory Caching**: Template worlds are loaded entirely into RAM (chunks, NBT, settings).
*   **Instant Loading**: Instances load instantly because chunks are deep-cloned from memory.
*   **Volatile**: Changes in instances are **never** saved to disk. When an instance is unloaded or the server stops, all data is lost and the dummy folder is automatically cleaned up.
*   **Async Cleanup**: Stale instance folders are deleted asynchronously to prevent main thread lag.

## 🛠 Installation & Usage

1.  Put the `PhaseWorld` plugin into your `plugins` folder.
2.  Start the server. A `plugin_data/PhaseWorld/templates/` directory will be created.
3.  Place your template world folders (e.g., `lobby_template`, `game_map`) inside `plugin_data/PhaseWorld/templates/`.
4.  Restart the server or run `/phaseworld reload`. PhaseWorld automatically loads all valid worlds in the `templates` directory into memory.

### Commands

*   `/phaseworld list [template|instance]`: List loaded templates or active instances.
*   `/phaseworld create <template_name>`: Create a new instance from a template and teleport to it.
*   `/phaseworld reload`: Manually reload a template world from the `plugin_data/templates/` directory.

## 🧩 API for Developers

PhaseWorld provides a simple API to manage templates and instances programmatically.

### Main Class
Access the main instance:
```php
use kim\present\phaseworld\PhaseWorld;

$plugin = PhaseWorld::getInstance();
```

### Loading a Template
Templates are automatically loaded from the `templates` folder. You can also load one manually:
```php
// Load 'worlds/my_map' as template 'arena_1'
$success = $plugin->loadTemplate("arena_1", $plugin->getServer()->getDataPath() . "worlds/my_map");
```

### Creating an Instance
To create a new world instance:
```php
use pocketmine\Server;
use pocketmine\player\Player;

// Create an instance of 'arena_1'
// Returns the name of the created world (e.g., ".phase_instance/arena_1#a1b2c3d4")
$worldName = $plugin->createInstance("arena_1");

if ($worldName !== null) {
    $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
    if ($world !== null) {
        // Teleport a player to the new instance
        /** @var Player $player */
        $player->teleport($world->getSafeSpawn());
    }
}
```

### Cleaning Up
Instances are automatically removed when the server stops. You can also remove them manually (this unloads the world and deletes the folder asynchronously):
```php
$plugin->removeInstance($worldName);
```

### Custom Instance Creation (Advanced)
If you want to create an instance manually without using `PhaseWorld::createInstance` (e.g., for custom naming or management):

```php
use kim\present\phaseworld\PhaseWorld;
use pocketmine\Server;

$templateName = "arena_1";
// Generate a unique ID (format: template#uuid)
// Note: PhaseWorld relies on the "template#id" format to identify the template!
$instanceId = $templateName . "#" . bin2hex(random_bytes(8));
$relativePath = PhaseWorld::PHASE_INSTANCE_DIR . $instanceId;

// 1. Create the directory structure (required for PMMP to recognize it as a world)
$instancePath = Server::getInstance()->getDataPath() . "worlds/" . $relativePath;
@mkdir($instancePath, 0777, true);

// 2. Load the world
if(Server::getInstance()->getWorldManager()->loadWorld($relativePath)){
    $world = Server::getInstance()->getWorldManager()->getWorldByName($relativePath);
    // $world is now your volatile instance!
}
```

## 📂 Directory Structure

*   `plugin_data/PhaseWorld/templates/`: Place your original world files here.
*   `worlds/.phase_instance/`: This is where active instances live. **Do not modify this manually.**

## ⚠️ Limitations

*   **Volatile Only**: Changes made in an instance are discarded when the world is unloaded.
*   **Memory Usage**: Templates are cached in RAM. Large worlds will consume significant memory.

## 📝 License

This project is licensed under the MIT License.
