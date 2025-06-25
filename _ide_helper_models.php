<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperActivityLog
 * @property int $id
 * @property string $action
 * @property string $model
 * @property string $row_id
 * @property array<array-key, mixed>|null $data_old
 * @property array<array-key, mixed>|null $data_new
 * @property string|null $description
 * @property int|null $user_id
 * @property int|null $created_by
 * @property int|null $company_id
 * @property string|null $user_agent
 * @property string|null $ip_address
 * @property string|null $url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog filter(array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereCompanyIsCurrent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereCreatedByUser()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereCreatedByUserOrChildren()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereDataNew($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereDataOld($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereRowId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog withoutRole($roles, $guard = null)
 */
	class ActivityLog extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperAttribute
 * @property int $id
 * @property string $name
 * @property string|null $value
 * @property int $company_id
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AttributeValue> $values
 * @property-read int|null $values_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute whereValue($value)
 */
	class Attribute extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperAttributeValue
 * @property int $id
 * @property int $attribute_id
 * @property int $created_by
 * @property string $name
 * @property string|null $color
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Attribute $attribute
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeValue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeValue whereAttributeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeValue whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeValue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeValue whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeValue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeValue whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeValue whereUpdatedAt($value)
 */
	class AttributeValue extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperBrand
 * @property int $id
 * @property int $company_id
 * @property int $created_by
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereUpdatedAt($value)
 */
	class Brand extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperCashBox
 * @property int $id
 * @property string $name
 * @property string $balance
 * @property int $cash_box_type_id
 * @property int $is_default
 * @property int $user_id
 * @property int $created_by
 * @property int $company_id
 * @property string|null $description
 * @property string|null $account_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\CashBoxType $typeBox
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereCashBoxTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereCompanyIsCurrent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereCreatedByUser()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereCreatedByUserOrChildren()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBox whereUserId($value)
 */
	class CashBox extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperCashBoxType
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $company_id
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashBox> $cashBoxes
 * @property-read int|null $cash_boxes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType whereCompanyIsCurrent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType whereCreatedByUser()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType whereCreatedByUserOrChildren()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashBoxType whereUpdatedAt($value)
 */
	class CashBoxType extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperCategory
 * @property int $id
 * @property int $company_id
 * @property int $created_by
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category> $children
 * @property-read int|null $children_count
 * @property-read \App\Models\Company $company
 * @property-read Category|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 */
	class Category extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperCompany
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $field
 * @property string|null $owner_name
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property int|null $created_by
 * @property string|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashBox> $cashBoxes
 * @property-read int|null $cash_boxes_count
 * @property-read \App\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Image> $images
 * @property-read int|null $images_count
 * @property-read \App\Models\Image|null $logo
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $userCompanyCash
 * @property-read int|null $user_company_cash_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\CompanyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company filter(array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCompanyIsCurrent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCreatedByUser()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCreatedByUserOrChildren()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereField($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereOwnerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company withoutRole($roles, $guard = null)
 */
	class Company extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperImage
 * @property int $id
 * @property string $url
 * @property string $type
 * @property int $imageable_id
 * @property string $imageable_type
 * @property int $company_id
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $imageable
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereImageableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereImageableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereUrl($value)
 */
	class Image extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperInstallment
 * @property int $id
 * @property int $installment_plan_id
 * @property int $user_id
 * @property int $created_by
 * @property string|null $installment_number
 * @property string $due_date
 * @property string $amount
 * @property string $status
 * @property string|null $paid_at
 * @property string $remaining
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\InstallmentPlan $installmentPlan
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment whereInstallmentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment whereInstallmentPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment whereRemaining($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Installment whereUserId($value)
 */
	class Installment extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperInstallmentPayment
 * @property int $id
 * @property int $installment_plan_id
 * @property int $company_id
 * @property int $created_by
 * @property string $payment_date
 * @property string $amount_paid
 * @property string $payment_method
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\InstallmentPlan $plan
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment whereInstallmentPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPayment whereUpdatedAt($value)
 */
	class InstallmentPayment extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperInstallmentPlan
 * @property int $id
 * @property int $invoice_id
 * @property int $user_id
 * @property int $company_id
 * @property int $created_by
 * @property string $total_amount
 * @property string $down_payment
 * @property string $remaining_amount
 * @property int $number_of_installments
 * @property string $installment_amount
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Installment> $installments
 * @property-read int|null $installments_count
 * @property-read \App\Models\Invoice $invoice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InstallmentPayment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereDownPayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereInstallmentAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereNumberOfInstallments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereRemainingAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstallmentPlan whereUserId($value)
 */
	class InstallmentPlan extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperInvoice
 * @property int $id
 * @property int $company_id
 * @property int $created_by
 * @property int $user_id
 * @property int $invoice_type_id
 * @property string|null $invoice_number
 * @property string|null $due_date
 * @property string $total_amount
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\InstallmentPlan|null $installmentPlan
 * @property-read \App\Models\InvoiceType $invoiceType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereInvoiceTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUserId($value)
 */
	class Invoice extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperInvoiceItem
 * @property int $id
 * @property int $invoice_id
 * @property int|null $product_id
 * @property int $company_id
 * @property int $created_by
 * @property string $name
 * @property int $quantity
 * @property string $unit_price
 * @property string $discount
 * @property string $total
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Invoice $invoice
 * @property-read \App\Models\Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereUpdatedAt($value)
 */
	class InvoiceItem extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperInvoiceType
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $context sales, purchases, inventory, finance, services, etc.
 * @property string $code
 * @property int $company_id
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType whereContext($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceType whereUpdatedAt($value)
 */
	class InvoiceType extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperOrder
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 */
	class Order extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperPayment
 * @property int $id
 * @property int $user_id
 * @property int $company_id
 * @property int $created_by
 * @property string $payment_date
 * @property string $amount
 * @property string $method
 * @property string|null $notes
 * @property int $is_split
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $payment_method_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Installment> $installments
 * @property-read int|null $installments_count
 * @property-read \App\Models\PaymentMethod|null $paymentMethod
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereIsSplit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUserId($value)
 */
	class Payment extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperPaymentMethod
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereUpdatedAt($value)
 */
	class PaymentMethod extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperProduct
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $active
 * @property bool $featured
 * @property bool $returnable
 * @property string|null $desc
 * @property string|null $desc_long
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int $category_id
 * @property int|null $brand_id
 * @property int $company_id
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Brand|null $brand
 * @property-read \App\Models\Category $category
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductVariant> $variants
 * @property-read int|null $variants_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereBrandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDesc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDescLong($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereReturnable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 */
	class Product extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperProductVariant
 * @property int $id
 * @property string|null $barcode
 * @property string|null $sku
 * @property numeric|null $retail_price
 * @property numeric|null $wholesale_price
 * @property string|null $profit_margin
 * @property string|null $image
 * @property numeric|null $weight
 * @property array<array-key, mixed>|null $dimensions
 * @property int|null $min_quantity
 * @property numeric|null $tax
 * @property numeric|null $discount
 * @property string $status
 * @property int $product_id
 * @property int $created_by
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductVariantAttribute> $attributes
 * @property-read int|null $attributes_count
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\Product $product
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Stock> $stocks
 * @property-read int|null $stocks_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereBarcode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereDimensions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereMinQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereProfitMargin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereRetailPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereTax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereWeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariant whereWholesalePrice($value)
 */
	class ProductVariant extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperProductVariantAttribute
 * @property int $id
 * @property int $product_variant_id
 * @property int $attribute_id
 * @property int $attribute_value_id
 * @property int $company_id
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Attribute $attribute
 * @property-read \App\Models\AttributeValue $attributeValue
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AttributeValue> $values
 * @property-read int|null $values_count
 * @property-read \App\Models\ProductVariant|null $variant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariantAttribute newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariantAttribute newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariantAttribute query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariantAttribute whereAttributeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariantAttribute whereAttributeValueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariantAttribute whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariantAttribute whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariantAttribute whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariantAttribute whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariantAttribute whereProductVariantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductVariantAttribute whereUpdatedAt($value)
 */
	class ProductVariantAttribute extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperProfit
 * @property int $id
 * @property string $source_type
 * @property int $source_id
 * @property int $created_by
 * @property int|null $user_id
 * @property int $company_id
 * @property string $revenue_amount
 * @property string $cost_amount
 * @property string $profit_amount
 * @property string|null $note
 * @property string $profit_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\User|null $customer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereCostAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereProfitAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereProfitDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereRevenueAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Profit whereUserId($value)
 */
	class Profit extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperQuotation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation query()
 */
	class Quotation extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperRevenue
 * @property int $id
 * @property string $source_type
 * @property int $source_id
 * @property int|null $user_id
 * @property int $created_by
 * @property int|null $wallet_id
 * @property int $company_id
 * @property string $amount
 * @property string $paid_amount
 * @property string $remaining_amount
 * @property string|null $payment_method
 * @property string|null $note
 * @property string $revenue_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\User|null $customer
 * @property-read \App\Models\CashBox|null $wallet
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue wherePaidAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereRemainingAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereRevenueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revenue whereWalletId($value)
 */
	class Revenue extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperRole
 * @property int $id
 * @property int|null $created_by
 * @property string $name
 * @property string $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $company_id
 * @property-read \App\Models\RoleCompany|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company> $companies
 * @property-read int|null $companies_count
 * @property-read \App\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCompanyIsCurrent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedByUser()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedByUserOrChildren()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withoutPermission($permissions)
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperRoleCompany
 * @property int $id
 * @property int $role_id
 * @property int $company_id
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $createdBy
 * @property-read \App\Models\Role $role
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany whereCompanyIsCurrent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany whereCreatedByUser()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany whereCreatedByUserOrChildren()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleCompany whereUpdatedAt($value)
 */
	class RoleCompany extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperService
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $default_price
 * @property int $company_id
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereDefaultPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereUpdatedAt($value)
 */
	class Service extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperStock
 * @property int $id
 * @property int $quantity
 * @property int $reserved
 * @property int $min_quantity
 * @property numeric|null $cost
 * @property string|null $batch
 * @property \Illuminate\Support\Carbon|null $expiry
 * @property string|null $loc
 * @property string $status
 * @property int $variant_id
 * @property int $warehouse_id
 * @property int $company_id
 * @property int $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\User|null $updater
 * @property-read \App\Models\ProductVariant $variant
 * @property-read \App\Models\Warehouse $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereBatch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereExpiry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereLoc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereMinQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereReserved($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereVariantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stock whereWarehouseId($value)
 */
	class Stock extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperSubscription
 * @property int $id
 * @property int $user_id
 * @property int $service_id
 * @property int $company_id
 * @property int $created_by
 * @property string $start_date
 * @property string $next_billing_date
 * @property string $billing_cycle
 * @property string $price
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Service $service
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereBillingCycle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereNextBillingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereUserId($value)
 */
	class Subscription extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperTransaction
 * @property int $id
 * @property int $user_id
 * @property int|null $target_user_id
 * @property string|null $cashbox_id
 * @property string|null $target_cashbox_id
 * @property string|null $original_transaction_id
 * @property string $type
 * @property string $amount
 * @property string $balance_before
 * @property string $balance_after
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $company_id
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\User|null $targetUser
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction byCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction byCreator($creatorId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction byUser($userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereBalanceBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCashboxId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereOriginalTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTargetCashboxId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTargetUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUserId($value)
 */
	class Transaction extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperTranslation
 * @property int $id
 * @property string $locale
 * @property string $field
 * @property string $value
 * @property string $model_type
 * @property int $model_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $model
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereField($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereValue($value)
 */
	class Translation extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @method void deposit(float|int $amount)
 * @mixin IdeHelperUser
 * @property int $id
 * @property string $phone
 * @property string $password
 * @property string|null $email
 * @property string|null $username
 * @property string|null $nickname
 * @property string|null $full_name
 * @property string|null $position
 * @property string|null $settings
 * @property string|null $last_login_at
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $balance
 * @property string $status
 * @property string $customer_type retail or wholesale
 * @property int|null $created_by
 * @property int|null $company_id
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CashBox|null $cashBoxeDefault
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashBox> $cashBoxes
 * @property-read int|null $cash_boxes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company> $companies
 * @property-read int|null $companies_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company> $companyUsersCash
 * @property-read int|null $company_users_cash_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Installment> $createdInstallments
 * @property-read int|null $created_installments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $createdRoles
 * @property-read int|null $created_roles_count
 * @property-read User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InstallmentPlan> $installmentPlans
 * @property-read int|null $installment_plans_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Installment> $installments
 * @property-read int|null $installments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Translation> $trans
 * @property-read int|null $trans_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User filter(array $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCompanyIsCurrent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedByUser()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedByUserOrChildren()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCustomerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperVariant
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VariantAttribute> $attributes
 * @property-read int|null $attributes_count
 * @property-read \App\Models\Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variant query()
 */
	class Variant extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperVariantAttribute
 * @property-read \App\Models\Attribute|null $attribute
 * @property-read \App\Models\AttributeValue|null $attributeValue
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\Variant|null $variant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VariantAttribute newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VariantAttribute newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VariantAttribute query()
 */
	class VariantAttribute extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @mixin IdeHelperWarehouse
 * @property int $id
 * @property string $name
 * @property string|null $location
 * @property string|null $manager
 * @property int|null $capacity
 * @property bool $status
 * @property int $company_id
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Stock> $stocks
 * @property-read int|null $stocks_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereManager($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereUpdatedAt($value)
 */
	class Warehouse extends \Eloquent {}
}

