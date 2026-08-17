import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useAppStore = defineStore('app', () => {
  const statusBarHeight = ref(0);
  const screenWidth = ref(375);
  const screenHeight = ref(667);
  const globalLoading = ref(false);
  const isConnected = ref(true);

  function initSystemInfo() {
    try {
      const systemInfo = uni.getSystemInfoSync();
      statusBarHeight.value = systemInfo.statusBarHeight ?? 0;
      screenWidth.value = systemInfo.screenWidth ?? 375;
      screenHeight.value = systemInfo.screenHeight ?? 667;
    } catch {
      // 默认值兜底
    }
  }

  function setGlobalLoading(loading: boolean) {
    globalLoading.value = loading;
  }

  function setNetworkStatus(connected: boolean) {
    isConnected.value = connected;
  }

  return {
    statusBarHeight, screenWidth, screenHeight, globalLoading, isConnected,
    initSystemInfo, setGlobalLoading, setNetworkStatus,
  };
});
