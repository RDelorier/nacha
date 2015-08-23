<?php

namespace Nacha\Traits;

use Nacha\File;

trait AllowsDebug
{
    protected function getImplodeGlue()
    {
        return (File::$debugMode ? '|' : '');
    }
}