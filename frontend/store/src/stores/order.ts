import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { CreateOrderParams, AddOrderItemParams } from '@/types/order';

/** 订单草稿项 */
export interface DraftOrderItem extends AddOrderItemParams {
  /** 本地临时ID */
  _tempId: string;
  /** 是否已保存到服务端 */
  _saved: boolean;
  /** 服务端返回的 item_id */
  _itemId?: number;
}

export const useOrderStore = defineStore('order', () => {
  const orderId = ref<number>(0);
  const orderNo = ref<string>('');
  const orderBase = ref<CreateOrderParams>({});
  const items = ref<DraftOrderItem[]>([]);

  const itemCount = computed(() => items.value.length);

  function setOrderId(id: number, no: string) {
    orderId.value = id;
    orderNo.value = no;
  }

  function setOrderBase(data: CreateOrderParams) {
    orderBase.value = { ...data };
  }

  function addItem(item: Omit<DraftOrderItem, '_tempId' | '_saved'>) {
    const newItem: DraftOrderItem = {
      ...item,
      _tempId: `temp_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
      _saved: false,
    };
    items.value.push(newItem);
  }

  function updateItem(tempId: string, data: Partial<DraftOrderItem>) {
    const index = items.value.findIndex((i) => i._tempId === tempId);
    if (index !== -1) {
      items.value[index] = { ...items.value[index], ...data };
    }
  }

  function removeItem(tempId: string) {
    const index = items.value.findIndex((i) => i._tempId === tempId);
    if (index !== -1) {
      items.value.splice(index, 1);
    }
  }

  function reset() {
    orderId.value = 0;
    orderNo.value = '';
    orderBase.value = {};
    items.value = [];
  }

  return {
    orderId, orderNo, orderBase, items, itemCount,
    setOrderId, setOrderBase, addItem, updateItem, removeItem, reset,
  };
});
