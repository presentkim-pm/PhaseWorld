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

namespace kim\present\phaseworld\task;

use pocketmine\scheduler\AsyncTask;

final class AsyncDirectoryDeleteTask extends AsyncTask{

    public function __construct(private readonly string $dir){}

    public function onRun() : void{
        $this->recursiveDelete($this->dir);
    }

    private function recursiveDelete(string $dir) : void{
        if(!is_dir($dir)){
            return;
        }

        $files = scandir($dir);
        if($files === false){
            return;
        }

        foreach(array_diff($files, ['.', '..']) as $file){
            $path = "$dir/$file";
            if(is_dir($path) && !is_link($path)){
                $this->recursiveDelete($path);
            }else{
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
