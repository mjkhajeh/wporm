<?php
namespace MJ\WPORM\Casts;

class JsonCast implements CastableInterface {
    public function get($value) {
        if ($value === null || $value === '') return null;
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $decoded;
    }
    public function set($value) {
        return json_encode($value);
    }
}
