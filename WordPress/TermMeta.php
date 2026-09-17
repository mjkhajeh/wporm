<?php

namespace MJ\WPORM\WordPress;

class TermMeta extends WordPressModel
{
    protected $wpdbTable = 'termmeta';
    protected $primaryKey = 'meta_id';

    public function term()
    {
        return $this->belongsTo(Term::class, 'term_id', 'term_id');
    }
}
