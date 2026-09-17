<?php

namespace MJ\WPORM\WordPress;

class UserMeta extends WordPressModel
{
    protected $wpdbTable = 'usermeta';
    protected $primaryKey = 'umeta_id';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'ID');
    }
}
