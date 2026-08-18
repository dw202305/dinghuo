<?php

declare(strict_types=1);

namespace tests\Feature;

use tests\TestCase;
use think\facade\Db;

/**
 * Feature 测试基类（真实 MySQL）
 *
 * 铁律：隔离执行，不碰生产。
 * - 每个用例执行前后强制校验当前连接库必须为 shishang_order_test；
 * - 用例间通过 TRUNCATE 清理资金/库存/订单相关表，互不串扰。
 *
 * 表结构来源：deploy/mysql/init.sql（v1.3，已含 audit_type /
 * uk_payment_transaction_id / uk_inventory_log_idempotent 等最新迁移）。
 */
abstract class FeatureTestCase extends TestCase
{
    /** Feature 测试唯一允许连接的库 */
    protected const TEST_DATABASE = 'shishang_order_test';

    /** 测试默认交易主体门店ID */
    protected const STORE_ID = 9101;

    /** 用例前后清理的表（逻辑表名，不带 lj_ 前缀） */
    private const CLEAN_TABLES = [
        'order',
        'order_item',
        'order_status_history',
        'payment',
        'inventory_log',
        'store_inventory',
        'operation_log',
        'customer_balance_account',
        'customer_balance_transaction',
        'recharge_order',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertIsolatedTestDatabase();
        $this->truncateTables();
    }

    protected function tearDown(): void
    {
        $this->truncateTables();

        parent::tearDown();
    }

    /**
     * 安全护栏：当前 MySQL 连接必须是隔离测试库，否则立即失败
     */
    protected function assertIsolatedTestDatabase(): void
    {
        $rows = Db::query('SELECT DATABASE() AS db');
        $current = (string) ($rows[0]['db'] ?? '');

        if ($current !== self::TEST_DATABASE) {
            $this->fail(
                "Feature 测试安全护栏：当前连接库为 [{$current}]，"
                . '仅允许连接 ' . self::TEST_DATABASE . '，测试终止。'
            );
        }
    }

    private function truncateTables(): void
    {
        foreach (self::CLEAN_TABLES as $table) {
            Db::execute("TRUNCATE TABLE `lj_{$table}`");
        }
    }

    /**
     * 造一张订单（deploy lj_order NOT NULL 列齐全）
     *
     * @param array $overrides 覆盖字段
     * @return array 含 id 的订单行
     */
    protected function seedOrder(array $overrides = []): array
    {
        $data = array_merge([
            'order_no'            => 'SS-T10-' . bin2hex(random_bytes(5)),
            'transaction_type'    => 1,
            'transaction_id'      => self::STORE_ID,
            'service_store_id'    => self::STORE_ID,
            'created_by'          => 1,
            'delivery_method'     => 1,
            'receiver_name'       => '资金安全测试门店',
            'receiver_phone'      => '13800000000',
            'receiver_province'   => '广东省',
            'receiver_city'       => '深圳市',
            'receiver_district'   => '南山区',
            'receiver_detail'     => '隔离测试地址',
            'order_status'        => 2, // PENDING_PAY 待支付
            'item_count'          => 1,
            'total_amount_cent'   => 20000,
            'paid_amount_cent'    => 0,
            'payment_status'      => 0,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ], $overrides);

        $data['id'] = Db::name('order')->insertGetId($data);

        return $data;
    }

    /**
     * 造一张支付单（deploy lj_payment）
     *
     * @param array $overrides 覆盖字段
     * @return array 含 id 的支付单行
     */
    protected function seedPayment(array $overrides = []): array
    {
        $data = array_merge([
            'payment_no'        => 'PAYT10' . bin2hex(random_bytes(5)),
            'order_id'          => 0,
            'order_no'          => '',
            'payment_channel'   => 'wechat',
            'pay_method'        => 'JSAPI',
            'pay_amount_cent'   => 20000,
            'pay_status'        => 0, // PENDING 待支付
            'idempotent_key'    => 'order_pay:t10:' . bin2hex(random_bytes(5)),
            'transaction_subject_type' => 1,
            'transaction_subject_id'   => self::STORE_ID,
            'created_at'        => date('Y-m-d H:i:s'),
        ], $overrides);

        $data['id'] = Db::name('payment')->insertGetId($data);

        return $data;
    }

    /**
     * 造一行门店套件库存（deploy lj_store_inventory）
     */
    protected function seedInventory(int $storeId, string $kitSku, int $available): array
    {
        $data = [
            'store_id'        => $storeId,
            'kit_sku'         => $kitSku,
            'total_purchased' => $available,
            'available'       => $available,
            'locked'          => 0,
            'consumed'        => 0,
            'frozen'          => 0,
            'return_pending'  => 0,
            'adjusted'        => 0,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        $data['id'] = Db::name('store_inventory')->insertGetId($data);

        return $data;
    }

    /**
     * 读取订单最新行
     */
    protected function freshOrder(int $orderId): array
    {
        $row = Db::name('order')->where('id', $orderId)->find();
        $this->assertNotNull($row, "订单 {$orderId} 应存在");

        return $row;
    }

    /**
     * 读取支付单最新行
     */
    protected function freshPayment(int $paymentId): array
    {
        $row = Db::name('payment')->where('id', $paymentId)->find();
        $this->assertNotNull($row, "支付单 {$paymentId} 应存在");

        return $row;
    }
}
