<?php
use MJ\WPORM\Blueprint;
use MJ\WPORM\Model;

class Currency extends Model {
	protected $table = 'ramzyar_currency';
	protected $fillable = ['id', 'source', 'symbol', 'value', 'change_percent', 'title', 'title_fa'];
	protected $guarded = ['id'];

	public function up( Blueprint $table ) {
		$table->id();
        $table->string('source');
        $table->string('symbol');
        $table->tinyText('value');
        $table->float('change_percent');
		$table->string('title');
		$table->string('title_fa');
		$table->timestamps();
        $this->schema = $table->toSql();
	}

	public function history() {
        // Return the result of hasMany, not just call it
        return $this->hasMany(History::class, 'currency_id', 'id');
    }

	public function creating() {
		$this->symbol = strtoupper( $this->symbol );
	}

	public function updating() {
		$this->symbol = strtoupper( $this->symbol );
	}
}