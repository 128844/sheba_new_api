<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    protected $guarded = ['id',];
    protected $table = 'inspections';

    public function scopePublished($query)
    {
        return $query->where('is_published', 1);
    }

    public function formTemplate()
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }
}