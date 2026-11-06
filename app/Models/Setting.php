<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Setting extends Model {
    public $incrementing = false;
    protected $primaryKey = 'key';
    protected $fillable = ['key','value','type','is_secret'];
    protected $casts = ['is_secret'=>'boolean'];
}
