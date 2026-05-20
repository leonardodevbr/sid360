import { createApp } from 'vue';
import { createPinia } from 'pinia';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';
import 'sweetalert2/dist/sweetalert2.min.css';
import '@vueform/multiselect/themes/default.css';
import '../css/app.css';
import './style.css';
import App from './App.vue';
import router from './router';

const app = createApp(App);

app.use(createPinia());
app.use(router);
app.use(Toast, {
  position: 'top-right',
  timeout: 3000,
  closeOnClick: true,
  pauseOnHover: true,
  draggable: true,
});

app.mount('#app');

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js')
        .then((reg) => console.log('Service Worker registrado.', reg))
        .catch((err) => console.error('Erro ao registrar Service Worker:', err));
}
