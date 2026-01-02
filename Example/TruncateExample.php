<?php
// Example/TruncateExample.php
// Demonstrates usage of Model::query()->truncate()

use MJ\WPORM\Model;
use MJ\WPORM\Blueprint;

class TruncateItem extends Model {
    protected $table = 'truncate_items';
    protected $fillable = ['id', 'name'];
    public function up(Blueprint $table) {
        $table->id();
        $table->string('name');
        $this->schema = $table->toSql();
    }
}

// Usage: create some records, then truncate
$item = new TruncateItem(['name' => 'A']);
$item->save();
$item = new TruncateItem(['name' => 'B']);
$item->save();

// Remove all records from the table quickly
TruncateItem::query()->truncate();