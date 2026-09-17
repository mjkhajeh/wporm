<?php

namespace MJ\WPORM\WordPress;

class CommentMeta extends WordPressModel
{
    protected $wpdbTable = 'commentmeta';
    protected $primaryKey = 'meta_id';

    public function comment()
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'comment_ID');
    }
}
