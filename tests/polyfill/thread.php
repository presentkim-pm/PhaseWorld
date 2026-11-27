<?php

/**
 * Test-only stand-in for the pmmp/ext-pmmpthread base class that AsyncTask extends.
 *
 * Loading AsyncTask (see AsyncDirectoryDeleteTask) needs its base class,
 * and tasks are only ever run directly by calling onRun() here. It is NEVER loaded when the real extension is available.
 */

declare(strict_types=1);

namespace pmmp\thread;

abstract class Runnable{
}
