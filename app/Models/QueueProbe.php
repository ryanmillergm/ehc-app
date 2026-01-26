<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueProbe extends Model
{
    protected $fillable = [
        'kind','status','to_email','mailer_default','mail_driver',
        'queue_connection','queue_name','attempt','meta','error',
        'queued_at','started_at','finished_at',
    ];
}
