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
        $encoded = json_encode($value);
        if ($encoded === false) {
            error_log('WPORM JsonCast: json_encode failed - ' . json_last_error_msg());
            return null;
        }
        return $encoded;
    }
}
