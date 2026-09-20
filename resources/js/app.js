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
