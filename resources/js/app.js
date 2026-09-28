import './bootstrap';
import { createApp } from 'vue';
import CatalogApp from './components/CatalogApp.vue';

const catalogRoot = document.querySelector('#catalog-app');

if (catalogRoot) {
    createApp(CatalogApp, {
        products: JSON.parse(catalogRoot.dataset.products || '[]'),
        categories: JSON.parse(catalogRoot.dataset.categories || '[]'),
        currency: catalogRoot.dataset.currency || 'BRL',
        initialCategory: catalogRoot.dataset.initialCategory || '',
        eyebrow: catalogRoot.dataset.eyebrow || 'Coleção',
        title: catalogRoot.dataset.title || 'Encontre o que combina com você',
        description: catalogRoot.dataset.description || '',
    }).mount(catalogRoot);
}

const errorHelpSearch = document.querySelector('[data-error-help-search]');

if (errorHelpSearch) {
    const items = [...document.querySelectorAll('[data-error-help-item]')];
    const count = document.querySelector('[data-error-help-count]');
    const empty = document.querySelector('[data-error-help-empty]');
    const normalize = (value) => value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const filterErrors = () => {
        const term = normalize(errorHelpSearch.value);
        let visible = 0;

        items.forEach((item) => {
            const matches = !term || normalize(item.dataset.search || '').includes(term);
            item.hidden = !matches;
            visible += matches ? 1 : 0;
        });

        count.textContent = `${visible} ${visible === 1 ? 'orientação disponível' : 'orientações disponíveis'}`;
        empty.hidden = visible !== 0;
    };

    const initialCode = new URLSearchParams(window.location.search).get('codigo');
    if (initialCode) {
        errorHelpSearch.value = initialCode;
    }

    errorHelpSearch.addEventListener('input', filterErrors);
    filterErrors();
}

const pushManager = document.querySelector('[data-push-manager]');

if (pushManager) {
    const button = pushManager.querySelector('[data-push-toggle]');
    const status = pushManager.querySelector('[data-push-status]');
    const publicKey = pushManager.dataset.publicKey || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let registration = null;
    let subscription = null;

    const setState = (message, enabled = false, disabled = false) => {
        status.textContent = message;
        button.textContent = enabled ? 'Desativar neste dispositivo' : 'Ativar neste dispositivo';
        button.disabled = disabled;
        button.dataset.enabled = enabled ? 'true' : 'false';
    };

    const applicationServerKey = (value) => {
        const padding = '='.repeat((4 - (value.length % 4)) % 4);
        const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);

        const key = Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));

        if (key.length !== 65 || key[0] !== 4) {
            throw new Error('push-invalid-vapid-key');
        }

        return key;
    };

    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    const errorMessage = (error) => {
        const code = error instanceof Error ? error.message : '';
        const name = error instanceof DOMException ? error.name : '';

        if (code === 'push-invalid-vapid-key' || name === 'InvalidAccessError') {
            return 'A chave pública Web Push da plataforma é inválida. Solicite a revisão da configuração VAPID.';
        }
        if (code === 'push-request-401' || code === 'push-request-419') {
            return 'Sua sessão expirou. Entre novamente antes de ativar as notificações.';
        }
        if (code === 'push-request-422') {
            return 'O provedor de notificações deste navegador não foi aceito pela plataforma.';
        }
        if (code === 'push-request-503') {
            return 'As chaves Web Push ainda não estão completas no servidor.';
        }
        if (name === 'NotAllowedError') {
            return 'A permissão foi bloqueada. Libere as notificações nas configurações do navegador.';
        }
        if (name === 'AbortError') {
            return 'O serviço de notificações do navegador não respondeu. Verifique a conexão e tente novamente.';
        }

        return 'Não foi possível ativar o Web Push. Recarregue a página e tente novamente.';
    };

    const request = async (url, method, body) => {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            throw new Error(`push-request-${response.status}`);
        }

        return response.json();
    };

    const initializePush = async () => {
        setState('Verificando os recursos de notificação deste dispositivo…', false, true);

        if (!publicKey) {
            setState('O Web Push ainda não foi configurado pela plataforma.', false, true);
            return;
        }

        if (!window.isSecureContext) {
            setState('As notificações exigem acesso HTTPS seguro.', false, true);
            return;
        }

        if (isIos && !isStandalone) {
            setState('No iPhone ou iPad, adicione a plataforma à Tela de Início e abra pelo novo ícone para ativar.', false, true);
            return;
        }

        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            setState('Este navegador não oferece suporte a Web Push.', false, true);
            return;
        }

        applicationServerKey(publicKey);
        await navigator.serviceWorker.register('/push-sw.js', {scope: '/', updateViaCache: 'none'});
        registration = await navigator.serviceWorker.ready;
        subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            setState('Notificações ativas neste dispositivo.', true);
        } else if (Notification.permission === 'denied') {
            setState('As notificações foram bloqueadas nas configurações do navegador.', false, true);
        } else {
            setState('Ative para receber somente avisos genéricos; o conteúdo continuará protegido no painel.');
        }
    };

    button.addEventListener('click', async () => {
        button.disabled = true;

        try {
            if (subscription) {
                await request(pushManager.dataset.destroyUrl, 'DELETE', {endpoint: subscription.endpoint});
                await subscription.unsubscribe();
                subscription = null;
                setState('Notificações desativadas neste dispositivo.');
                return;
            }

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                setState('Permissão não concedida. Ajuste as notificações nas configurações do navegador.', false, permission === 'denied');
                return;
            }

            const newSubscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey(publicKey),
            });
            const payload = newSubscription.toJSON();
            payload.contentEncoding = PushManager.supportedContentEncodings?.[0] || 'aes128gcm';
            try {
                await request(pushManager.dataset.storeUrl, 'POST', payload);
            } catch (error) {
                await newSubscription.unsubscribe();
                throw error;
            }
            subscription = newSubscription;
            setState('Notificações ativas neste dispositivo.', true);
        } catch (error) {
            setState(errorMessage(error));
        } finally {
            if (!button.dataset.enabled || button.dataset.enabled === 'false') {
                button.disabled = Notification.permission === 'denied';
            }
        }
    });

    initializePush().catch((error) => setState(errorMessage(error), false, true));
}
