<?php

namespace MJ\WPORM\WordPress;

class Option extends WordPressModel
{
    protected $wpdbTable = 'options';
    protected $primaryKey = 'option_id';
}
