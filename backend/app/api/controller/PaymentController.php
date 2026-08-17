<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\validate\PaymentValidate;
use app\common\service\PaymentService;
use think\exception\ValidateException;

/**
 * 支付控制器（门店端）
 * 创建支付/查询状态/微信回调/支付宝回调
 */
class PaymentController extends BaseController
{
    protected PaymentService $paymentService;

    protected function initialize(): void
    {
        $this->paymentService = new PaymentService();
    }

    /**
     * 创建支付
     * POST /api/v1/store/payment/create
     */
    public function create(): \think\Response
    {
        $data = $this->app->request->post();

        try {
            validate(PaymentValidate::class)->scene('create')->check($data);
        } catch (ValidateException $e) {
            return $this->paramError($e->getMessage());
        }

        try {
            $result = $this->paymentService->createPayment(
                $this->getStoreId(),
                (int) $data['order_id'],
                (int) $data['pay_channel'],
                (string) $data['pay_method'],
            );

            return $this->success($result);
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1006);
        } catch (\Throwable $e) {
            return $this->error('支付服务调用失败，请稍后重试', 5003);
        }
    }

    /**
     * 查询支付状态
     * GET /api/v1/store/payment/status
     */
    public function status(): \think\Response
    {
        $orderId = (int) $this->app->request->param('order_id', 0);

        if ($orderId <= 0) {
            return $this->paramError('订单ID不能为空');
        }

        try {
            $result = $this->paymentService->queryPaymentStatus($this->getStoreId(), $orderId);
            return $this->success($result);
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 1004);
        }
    }

    /**
     * 微信支付回调
     * POST /api/v1/store/payment/notify/wechat
     */
    public function wechatNotify(): \think\Response
    {
        $rawContent = $this->app->request->getContent();
        $notifyData = json_decode($rawContent, true) ?: [];

        try {
            $result = $this->paymentService->handleWechatNotify($notifyData);

            if ($result) {
                return json(['code' => 'SUCCESS', 'message' => '成功']);
            }
            return json(['code' => 'FAIL', 'message' => '处理失败']);
        } catch (\Throwable $e) {
            \think\facade\Log::error('微信回调处理异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return json(['code' => 'FAIL', 'message' => '处理失败']);
        }
    }

    /**
     * 支付宝支付回调
     * POST /api/v1/store/payment/notify/alipay
     */
    public function alipayNotify(): \think\Response
    {
        $notifyData = $this->app->request->post();

        try {
            $result = $this->paymentService->handleAlipayNotify($notifyData);

            if ($result) {
                return response('success', 200, [], 'text/plain');
            }
            return response('failure', 200, [], 'text/plain');
        } catch (\Throwable $e) {
            return response('failure', 200, [], 'text/plain');
        }
    }
}
