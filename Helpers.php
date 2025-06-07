<?php
namespace MJ\WPORM;

function class_basename($class) {
    return basename(str_replace('\\', '/', $class));
}

function current_time($format) {
    return date('Y-m-d H:i:s');
}
