<script setup lang="ts">
import { onLaunch, onShow, onHide } from '@dcloudio/uni-app';
import { useAuthStore } from '@/stores/auth';

onLaunch(() => {
  console.log('App Launch');
  const authStore = useAuthStore();
  authStore.initAuth();
});

onShow(() => {
  console.log('App Show');
});

onHide(() => {
  console.log('App Hide');
});
</script>

<style lang="scss">
@import '@/static/styles/global.scss';
</style>
