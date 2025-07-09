<?php
use MJ\WPORM\Model;
use MJ\WPORM\Blueprint;

class Product extends Model {
    protected $table = 'products';
    protected $fillable = ['id', 'name', 'deleted'];
    protected $softDeletes = true;
    protected $deletedAtColumn = 'deleted'; // boolean column
    protected $softDeleteType = 'boolean'; // use boolean-flag soft deletes

    public function up(Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('deleted')->default(0);
		$table->timestamps();
        $this->schema = $table->toSql();
    }
}

// Usage:
// $product = Product::find(1);
// $product->delete(); // sets deleted = 1
// Product::query()->withTrashed()->get(); // includes deleted
// Product::query()->onlyTrashed()->get(); // only deleted
// $product->restore(); // sets deleted = 0
