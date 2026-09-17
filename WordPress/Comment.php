<?php

namespace MJ\WPORM\WordPress;

class Comment extends WordPressModel
{
    protected $wpdbTable = 'comments';
    protected $primaryKey = 'comment_ID';

    public function post()
    {
        return $this->belongsTo(Post::class, 'comment_post_ID', 'ID');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id', 'ID');
    }

    public function meta()
    {
        return $this->hasMany(CommentMeta::class, 'comment_id', 'comment_ID');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'comment_parent', 'comment_ID');
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'comment_parent', 'comment_ID');
    }
}
