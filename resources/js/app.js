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
