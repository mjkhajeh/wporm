<?php
namespace MJ\WPORM;

function class_basename($class) {
    return basename(str_replace('\\', '/', $class));
}
