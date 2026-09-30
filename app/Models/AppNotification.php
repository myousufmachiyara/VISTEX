<?php // app/Models/AppNotification.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AppNotification extends Model {
    protected $table = 'app_notifications';
    protected $fillable = ['user_id', 'type', 'title', 'body', 'link_type', 'link_id', 'read_at'];
    protected $casts = ['read_at' => 'datetime'];
}