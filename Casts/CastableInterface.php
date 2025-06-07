<?php
namespace MJ\WPORM\Casts;

interface CastableInterface {
    public function get($value);
    public function set($value);
}
