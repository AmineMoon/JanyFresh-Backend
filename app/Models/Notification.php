<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';

    const TYPE_GENERAL = 'general';
    const TYPE_ORDER = 'order';
    const TYPE_DELIVERY = 'delivery';
    const TYPE_SYSTEM = 'system';

    const RECIPIENT_ALL = 'all';
    const RECIPIENT_RETAILERS = 'retailers';
    const RECIPIENT_DRIVERS = 'drivers';
    const RECIPIENT_EVERYONE = 'everyone';
    const RECIPIENT_SPECIFIC = 'specific';

    protected $fillable = [
        'title',
        'message',
        'type',
        'data',
        'sender_id',
        'recipient_type',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipients()
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function recipientUsers()
    {
        return $this->belongsToMany(User::class, 'notification_recipients')
            ->withPivot('status', 'sent_at', 'read_at');
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
