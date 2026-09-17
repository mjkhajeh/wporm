<?php

namespace MJ\WPORM\WordPress;

class User extends WordPressModel
{
    protected $wpdbTable = 'users';
    protected $primaryKey = 'ID';

    public function posts()
    {
        return $this->hasMany(Post::class, 'post_author', 'ID');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id', 'ID');
    }

    public function meta()
    {
        return $this->hasMany(UserMeta::class, 'user_id', 'ID');
    }
}
