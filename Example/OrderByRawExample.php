<?php
// Example/OrderByRawExample.php
// Demonstrates orderByRaw usage with bindings

use MJ\WPORM\DB;
use MJ\WPORM\Model;

// Using anonymous model via DB::table for a quick demonstration
$query = DB::table('products')
    ->select(['id','name'])
    ->where('price','>',10)
    ->orderByRaw('FIELD(name, ?, ?)', ['Widget', 'Gadget'])
    ->setDebug(true);

// Dump SQL (prepared) for inspection
$query->dumpSql();

// You could run get():
// $rows = $query->get();


// Using a real Model: demonstrates calling on model query
class DemoProduct extends Model {
    protected $table = 'products';
}

$q = DemoProduct::query()->orderByRaw('FIELD(name, ?, ?)', ['Widget','Gadget'])->setDebug(true);
$q->dumpSql();

return true;
