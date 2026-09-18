<?php

/**
 * Composite relationship example.
 *
 * This pattern is useful when a plugin stores a relation by both an entity
 * identifier and a language code. Run this example from a WordPress request
 * after loading the ORM and defining the related models used by your plugin.
 */

use MJ\WPORM\Blueprint;
use MJ\WPORM\Model;

class ExampleSpecialist extends Model {
    protected $table = 'example_specialists';
    protected $fillable = ['id', 'user_id', 'language', 'name'];
    protected $timestamps = false;

    public function up(Blueprint $table) {
        $table->id();
        $table->integer('user_id');
        $table->string('language', 10);
        $table->string('name');
        $table->unique(['user_id', 'language']);
    }

    public function specialities() {
        return $this->hasMany(
            ExampleSpecialistSpeciality::class,
            ['user_id', 'language'],
            ['user_id', 'language']
        );
    }
}

class ExampleSpecialistSpeciality extends Model {
    protected $table = 'example_specialist_specialities';
    protected $fillable = ['id', 'user_id', 'language', 'speciality_id'];
    protected $timestamps = false;

    public function up(Blueprint $table) {
        $table->id();
        $table->integer('user_id');
        $table->string('language', 10);
        $table->integer('speciality_id');
        $table->index(['user_id', 'language']);
    }
}

// Lazy loading uses both parent values: user_id = 12 AND language = 'en'.
$specialist = ExampleSpecialist::find(12);
$specialities = $specialist->specialities;

// Eager loading performs one composite-key query and maps each row back to
// the matching (user_id, language) parent tuple.
$specialists = ExampleSpecialist::with('specialities')->get();
foreach ($specialists as $specialist) {
    foreach ($specialist->specialities as $speciality) {
        error_log((string) $speciality->speciality_id);
    }
}
