<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 登录账号模型
 * @property int $id
 * @property string $phone 手机号
 * @property string $password_hash 密码哈希
 * @property int $account_role 账号角色
 * @property int $status 状态
 */
class Account extends BaseModel
{
    protected $table = 'lj_account';

    // 隐藏敏感字段
    protected $hidden = ['password_hash', 'wechat_openid', 'wechat_unionid'];

    /**
     * 验证密码
     * @param string $password 明文密码
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash ?? '');
    }

    /**
     * 设置密码
     * @param string $password 明文密码
     */
    public function setPasswordAttribute(string $password): void
    {
        $this->data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * 关联联系人
     */
    public function contact(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(StoreContact::class, 'contact_id', 'id');
    }

    /**
     * 关联客户主体（多对多）
     */
    public function customers(): \think\model\relation\HasMany
    {
        return $this->hasMany(AccountCustomer::class, 'account_id', 'id');
    }

    /**
     * 是否后台管理员（角色 >= 6）
     */
    public function isAdmin(): bool
    {
        return $this->account_role >= 6;
    }

    /**
     * 获取默认登录门店ID
     * 从 lj_account_customer 关联表中取 is_default_store=1 的门店
     * @return int
     */
    public function getDefaultStoreId(): int
    {
        $defaultStore = AccountCustomer::where('account_id', $this->id)
            ->where('customer_type', 1)
            ->where('is_default_store', 1)
            ->where('status', 1)
            ->value('customer_id');

        if ($defaultStore) {
            return (int) $defaultStore;
        }

        // 没有默认门店时，取第一个关联门店
        $firstStore = AccountCustomer::where('account_id', $this->id)
            ->where('customer_type', 1)
            ->where('status', 1)
            ->value('customer_id');

        return (int) ($firstStore ?: 0);
    }

    /**
     * 获取所有关联门店ID列表
     * @return array<int>
     */
    public function getStoreIds(): array
    {
        return AccountCustomer::where('account_id', $this->id)
            ->where('customer_type', 1)
            ->where('status', 1)
            ->column('customer_id');
    }
}
