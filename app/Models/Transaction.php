<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'cashbox_id',
        'target_user_id',
        'target_cashbox_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // العلاقة مع المستخدم الذي قام بالعملية
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // العلاقة مع المستخدم الهدف للعملية
    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->whereHas('user', function ($userQuery) use ($companyId) {
            $userQuery->where('company_id', $companyId);
        });
    }

    public function scopeByCreator($query, $creatorId)
    {
        return $query->whereHas('user', function ($userQuery) use ($creatorId) {
            $userQuery->where('created_by', $creatorId);
        });
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // الدالة لعكس عملية التحويل
    public function reverseTransfer()
    {
        $sender = $this->user;
        $receiver = $this->targetUser;

        if (!$sender || !$receiver) {
            throw new Exception("المستخدمون المرتبطون بالمعاملة غير موجودين.");
        }

        $sender->balance += $this->amount;
        $receiver->balance -= $this->amount;

        $sender->save();
        $receiver->save();
    }

    // الدالة لعكس عملية السحب
    public function reverseWithdraw()
    {
        $user = $this->user;

        if (!$user) {
            throw new Exception("المستخدم المرتبط بالمعاملة غير موجود.");
        }

        $user->balance += $this->amount;
        $user->save();
    }

    // الدالة لعكس عملية الإيداع
    public function reverseDeposit()
    {
        $user = $this->user;

        if (!$user) {
            throw new Exception("المستخدم المرتبط بالمعاملة غير موجود.");
        }

        $user->balance -= $this->amount;

        if ($user->balance < 0) {
            throw new Exception("الرصيد غير كافٍ لعكس العملية.");
        }

        $user->save();
    }
}

