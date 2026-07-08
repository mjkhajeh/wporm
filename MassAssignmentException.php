<?php
namespace MJ\WPORM;

/**
 * Thrown when attempting to mass-assign an attribute that is not
 * listed in $fillable or is listed in $guarded.
 *
 * Mirrors Laravel Eloquent's Illuminate\Database\Eloquent\MassAssignmentException.
 */
class MassAssignmentException extends \RuntimeException {
    /**
     * Create a new MassAssignmentException instance.
     *
     * @param string $attribute The attribute that was blocked.
     * @param string $model     The fully-qualified model class name.
     * @return static
     */
    public static function forAttribute($attribute, $model) {
        return new static(
            "Add [{$attribute}] to {$model}'s fillable property to allow mass assignment."
        );
    }
}
