<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    products: { type: Array, required: true },
    categories: { type: Array, required: true },
    currency: { type: String, default: 'BRL' },
});

const search = ref('');
const selectedCategory = ref(null);

const filteredProducts = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('pt-BR');
    return props.products.filter((product) => {
        const categoryMatches = !selectedCategory.value || product.category_id === selectedCategory.value;
        const contentMatches = !term || [product.name, product.sku, product.description, product.category]
            .filter(Boolean)
            .some((value) => value.toLocaleLowerCase('pt-BR').includes(term));
        return categoryMatches && contentMatches;
    });
});

const money = (value) => new Intl.NumberFormat('pt-BR', {
    style: 'currency', currency: props.currency,
}).format(value);
</script>

<template>
    <section class="catalog-workspace" aria-labelledby="catalog-title">
        <div class="catalog-toolbar">
            <div>
                <p class="eyebrow">Coleção</p>
                <h2 id="catalog-title">Encontre o que combina com você</h2>
            </div>
            <label class="search-field">
                <span class="sr-only">Buscar no catálogo</span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                <input v-model="search" type="search" placeholder="Buscar produto ou código" />
            </label>
        </div>

        <div class="category-filter" aria-label="Filtrar por categoria">
            <button :class="{ active: selectedCategory === null }" @click="selectedCategory = null">Todos</button>
            <button
                v-for="category in categories"
                :key="category.id"
                :class="{ active: selectedCategory === category.id }"
                @click="selectedCategory = category.id"
            >{{ category.name }}</button>
        </div>

        <p class="results-count" aria-live="polite">
            {{ filteredProducts.length }} {{ filteredProducts.length === 1 ? 'produto' : 'produtos' }}
        </p>

        <div v-if="filteredProducts.length" class="product-grid">
            <article v-for="product in filteredProducts" :key="product.id" class="product-card">
                <a :href="product.url" class="product-media" :aria-label="`Ver ${product.name}`">
                    <img :src="product.image" :alt="product.name" loading="lazy" />
                    <span v-if="product.featured" class="product-badge">Destaque</span>
                </a>
                <div class="product-card-body">
                    <div class="product-meta">
                        <span>{{ product.category || 'Catálogo' }}</span>
                        <span v-if="product.sku">{{ product.sku }}</span>
                    </div>
                    <h3><a :href="product.url">{{ product.name }}</a></h3>
                    <p class="product-description">{{ product.description }}</p>
                    <div class="product-card-footer">
                        <div v-if="product.price" class="price-block">
                            <span v-if="product.promotional_price" class="old-price">{{ money(product.price) }}</span>
                            <strong>{{ money(product.promotional_price || product.price) }}</strong>
                        </div>
                        <span v-else class="price-on-request">Sob consulta</span>
                        <a :href="product.url" class="round-link" aria-label="Abrir produto">↗</a>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="empty-state">
            <div class="empty-icon">⌕</div>
            <h3>Nenhum produto encontrado</h3>
            <p>Tente outro termo ou remova o filtro de categoria.</p>
            <button @click="search = ''; selectedCategory = null">Limpar filtros</button>
        </div>
    </section>
</template>
