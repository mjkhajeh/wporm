<?php
namespace WPORM\Casts;

class JsonCast implements CastableInterface {
    public function get($value) {
        return json_decode($value, true);
    }
    public function set($value) {
        return json_encode($value);
    }
}
