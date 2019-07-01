<?php namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Group extends Eloquent
{
    protected $connection = 'mongodb_atlas_conn';

    public function navigation()
    {
        return $this->belongsTo(Navigation::class);
    }

    public function navServices(){
        return $this->belongsToMany(NavService::class);
    }

}