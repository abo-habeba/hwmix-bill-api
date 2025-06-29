<?php

namespace App\Models;

use Exception;
use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use Blameable, Scopes;
    protected $fillable = [
        'user_id',
        'cashbox_id',
        'target_user_id',
        'target_cashbox_id',
        'created_by',
        'company_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'original_transaction_id', // تمت إضافته
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class, 'cashbox_id');
    }

    public function targetCashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class, 'target_cashbox_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'original_transaction_id');
    }

    public function reverseTransactions()
    {
        return $this->hasMany(Transaction::class, 'original_transaction_id');
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

    // الدوال لعكس المعاملات
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

    public function reverseWithdraw()
    {
        $user = $this->user;

        if (!$user) {
            throw new Exception("المستخدم المرتبط بالمعاملة غير موجود.");
        }

        $user->balance += $this->amount;
        $user->save();
    }

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
