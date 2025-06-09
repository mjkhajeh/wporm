<?php
use MJ\WPORM\Blueprint;
use MJ\WPORM\Model;

class History extends Model {
	protected $table = 'ramzyar_currency_history';
	protected $fillable = ['id', 'currency_id', 'value'];
	protected $guarded = ['id'];
	protected $casts = [
		'currency_id'	=> 'int',
	];

	public function up( Blueprint $table ) {
		$table->id();
		$table->integer('currency_id');
		$table->tinyText('value');
		$table->timestamps();
		$this->schema = $table->toSql();
	}

	public function currency() {
		return $this->belongsTo(Currency::class, 'currency_id');
	}
}