<?php

declare(strict_types=1);

namespace kim\present\phaseworld\tests;

use kim\present\phaseworld\task\AsyncDirectoryDeleteTask;
use PHPUnit\Framework\TestCase;

final class AsyncDirectoryDeleteTaskTest extends TestCase{

    public function testDeletesTheDirectoryRecursively() : void{
        $root = sys_get_temp_dir() . "/phaseworld-delete-" . bin2hex(random_bytes(6));
        mkdir($root . "/a/b", 0777, true);
        file_put_contents($root . "/file.txt", "x");
        file_put_contents($root . "/a/b/deep.txt", "y");

        (new AsyncDirectoryDeleteTask($root))->onRun();

        self::assertDirectoryDoesNotExist($root);
    }

    public function testMissingDirectoryIsIgnored() : void{
        $this->expectNotToPerformAssertions();
        (new AsyncDirectoryDeleteTask(sys_get_temp_dir() . "/phaseworld-missing-" . bin2hex(random_bytes(6))))->onRun();
    }
}
