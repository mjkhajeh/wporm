<?php

namespace MJ\WPORM\WordPress;

class PostMeta extends WordPressModel
{
    protected $wpdbTable = 'postmeta';
    protected $primaryKey = 'meta_id';

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'ID');
    }
}
