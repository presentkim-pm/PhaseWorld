# Changelog

## 2.0.0 (2026-11-27)

The Poggit plugin version for the original [pmmp/PocketMine-MP](https://github.com/pmmp/PocketMine-MP) 5.x is kept on the
[`legacy/pmmp-5.44`](https://github.com/presentkim-pm/PhaseWorld/tree/legacy/pmmp-5.44) branch.

### ⚠️ Breaking changes
- Targets [axolotl-pm/PocketMine-MP](https://github.com/axolotl-pm/PocketMine-MP) `5.51` (BedrockProtocol 64,
  Bedrock `1.26.60`) and **PHP 8.4+**.
- Distributed as a phar from GitHub Releases instead of Poggit. `libasynform` 2.0 and `await-generator` are installed
  with Composer and bundled into the phar.
- The optional dependency on the RoMo `CommandCore` virion is removed (it is not published as a Composer package):
  `/phaseworld` is a plain PocketMine-MP command, so the client no longer auto-completes its sub commands and the
  template names. `TemplateDataEnum` was removed together with it.
- Sources moved from `src/kim/present/phaseworld/` to `src/` (`src-namespace-prefix` in `plugin.yml`); class names are unchanged.
- `PhaseWorld::removeInstance()` takes the instance name (`template#id`) and also accepts a full world name; it only
  forgets the instance and deletes its folder, the world has to be unloaded through the `WorldManager`.

### 🔄 Changes
- Optional CameraAPI `2.0` teleport fade (`softdepend: CameraAPI`).
- Instance ids come from `random_bytes()` instead of `mt_rand()`.
- `composer.json` and PHPUnit tests (world data cloning, template chunk cache, async directory delete, class loading) added.

### 🐛 Fixes
- Every instance of a template shared the template's world data NBT, because the world data was only shallow-cloned:
  renaming, the time, the weather and the spawn of one instance changed all the other instances and the template.
  Each instance now gets its own copy.
- Unloaded instances were never removed from `PhaseWorld::getInstances()`: the instance list is keyed by
  `.phase_instance/template#id`, but the provider reported the bare `template#id`.
- `/phaseworld list instance` on the console printed the template names instead of the instance names.
- `/phaseworld reload` logged a warning ("Failed to load template") for every template that was already loaded.
- A form closed because the player disconnected (`FormValidationException` from libasynform 2.0) is no longer an
  unhandled error in the template / instance list forms.
