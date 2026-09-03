<?php
// Example/WhereRelationExample.php
// Demonstrates whereRelation()/orWhereRelation() — Eloquent-style aliases
// of whereHas()/orWhereHas() for filtering by the existence of related records.

use MJ\WPORM\Blueprint;
use MJ\WPORM\Model;

class WREUser extends Model {
	protected $table = 'wre_users';
	protected $fillable = ['id', 'name', 'email'];
	public function up(Blueprint $table) {
		$table->id();
		$table->string('name');
		$table->string('email');
		$this->schema = $table->toSql();
	}
	public function posts() {
		return $this->hasMany(WREPost::class, 'user_id');
	}
}

class WREPost extends Model {
	protected $table = 'wre_posts';
	protected $fillable = ['id', 'user_id', 'title', 'published'];
	public function up(Blueprint $table) {
		$table->id();
		$table->unsignedBigInteger('user_id');
		$table->string('title');
		$table->boolean('published');
		$this->schema = $table->toSql();
	}
}

// Users who have at least one published post.
$usersWithPublishedPosts = WREUser::query()
	->whereRelation('posts', function($q) {
		$q->where('published', 1);
	})
	->get();

// OR version — users with at least one draft OR at least one published post.
$usersWithAnyPost = WREUser::query()
	->orWhereRelation('posts', function($q) {
		$q->where('published', 0);
	})
	->get();

// whereRelation is an alias of whereHas — identical behavior.
$alias = WREUser::query()
	->whereRelation('posts', function($q) {
		$q->where('published', 1);
	})
	->get();