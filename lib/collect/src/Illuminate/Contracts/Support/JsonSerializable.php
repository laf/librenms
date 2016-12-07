<?php

namespace Illuminate\Contracts\Support;

interface JsonSerializable
{
    /**
     * Get an array that is ready to be serialized
     *
     * @return array
     */
    public function jsonSerialize();
}