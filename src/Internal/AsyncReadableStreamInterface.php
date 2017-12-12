<?php

namespace Http\Adapter\Artax\Internal;

interface AsyncReadableStreamInterface
{
    public function readAsync($length);

    public function getContentsAsync();

    public function isReadableAsync();
}
