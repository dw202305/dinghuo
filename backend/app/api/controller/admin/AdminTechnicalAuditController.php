<?php
declare(strict_types=1);

namespace app\api\controller\admin;

use app\api\controller\BaseController;
use app\api\validate\AuditValidate;
use app\common\exception\BusinessException;
use app\common\service\TechnicalAuditService;
use think\exception\ValidateException;

/**
 * 后台技术审核控制器
 *
 * 处理预审/后审审核相关接口（PRD v3.2 §5.9）。
 */
class AdminTechnicalAuditController extends BaseController
{
    /** @var TechnicalAuditService */
    private TechnicalAuditService $auditService;

    /**
     * 初始化
     */
    protected function initialize(): void
    {
        $this->auditService = app(TechnicalAuditService::class);
    }

    /**
     * 后台切换订单为预审流程
     * POST /api/v1/admin/orders/:id/audit/switch-to-pre
     *
     * @return \think\Response
     */
    public function switchToPreAudit(): \think\Response
    {
        $orderId = (int) $this->app->request->route('id', 0);

        // 通过 ID 查找 order_no
        $order = \app\common\model\Order::find($orderId);
        if (!$order) {
            throw new BusinessException(\app\common\enum\ErrorCode::DATA_NOT_FOUND, '订单不存在');
        }

        $adminId = $this->getAccountId();
        $order = $this->auditService->switchToPreAudit($order->order_no, $adminId);

        return $this->success([
            'order_no'   => $order->order_no,
            'audit_type' => $order->audit_type,
        ], '已切换为预审流程');
    }

    /**
     * 提交审核结果
     * POST /api/v1/admin/orders/:id/audit/result
     *
     * @return \think\Response
     */
    public function submitResult(): \think\Response
    {
        $orderId = (int) $this->app->request->route('id', 0);
        $order = \app\common\model\Order::find($orderId);
        if (!$order) {
            throw new BusinessException(\app\common\enum\ErrorCode::DATA_NOT_FOUND, '订单不存在');
        }

        $validate = new AuditValidate();
        $validate->check($this->app->request->param());

        $result = $this->app->request->param('result');
        $data = [
            'remark'       => $this->app->request->param('remark', ''),
            'auditor_name' => $this->app->request->param('auditor_name', ''),
        ];

        $auditorId = $this->getAccountId();
        $order = $this->auditService->submitAuditResult($order->order_no, $auditorId, $result, $data);

        return $this->success([
            'order_no'     => $order->order_no,
            'audit_status' => $order->audit_status,
            'order_status' => $order->order_status,
        ], '审核结果已提交');
    }

    /**
     * 获取审核详情
     * GET /api/v1/admin/orders/:id/audit
     *
     * @return \think\Response
     */
    public function getAuditDetail(): \think\Response
    {
        $orderId = (int) $this->app->request->route('id', 0);
        $order = \app\common\model\Order::find($orderId);
        if (!$order) {
            throw new BusinessException(\app\common\enum\ErrorCode::DATA_NOT_FOUND, '订单不存在');
        }

        $detail = $this->auditService->getAuditDetail($order->order_no);
        return $this->success($detail);
    }

    /**
     * 检查预审支付超时
     * GET /api/v1/admin/orders/:id/audit/timeout-check
     *
     * @return \think\Response
     */
    public function checkTimeout(): \think\Response
    {
        $orderId = (int) $this->app->request->route('id', 0);
        $order = \app\common\model\Order::find($orderId);
        if (!$order) {
            throw new BusinessException(\app\common\enum\ErrorCode::DATA_NOT_FOUND, '订单不存在');
        }

        $result = $this->auditService->checkPaymentTimeout($order->order_no);
        return $this->success($result);
    }
}
