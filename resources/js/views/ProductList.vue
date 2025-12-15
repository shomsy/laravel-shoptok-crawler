<template>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="fw-bold">Televizorji</h3>
        <select class="form-select w-auto">
            <option selected>Priljubljenost</option>
            <option value="price_asc">Cena: nižja najprej</option>
            <option value="price_desc">Cena: višja najprej</option>
        </select>
    </div>

<template>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="fw-bold">Televizorji</h3>
        <select class="form-select w-auto">
            <option selected>Priljubljenost</option>
            <option value="price_asc">Cena: nižja najprej</option>
            <option value="price_desc">Cena: višja najprej</option>
        </select>
    </div>

    <!-- Layout: Sidebar + Grid -->
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 mb-4">
            <div class="p-3 bg-light rounded shadow-sm">
                <h6 class="fw-bold mb-3 border-bottom pb-2">Izdelki</h6>
                <ul class="list-unstyled mb-0" v-if="sidebarCategories.length">
                    <li v-for="cat in sidebarCategories" :key="cat.id" class="mb-2">
                         <!-- In a real app, these would filter or specific routes. For now, we link to products query -->
                        <router-link :to="`/products?category=${cat.slug}`" class="text-decoration-none text-dark d-block py-1 sidebar-link">
                            {{ cat.name }}
                            <!-- <span class="float-end text-muted small">({{ cat.products_count || 0 }})</span> -->
                        </router-link>
                    </li>
                </ul>
                <p v-else class="text-muted small">Ni podkategorij.</p>
            </div>

            <!-- Example Filter: Producer (Static for visual confirmation of 'sidebar' presence) -->
            <div class="mt-4">
                <h6 class="fw-bold mb-2">Proizvajalec</h6>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="samsung">
                    <label class="form-check-label" for="samsung">Samsung</label>
                </div>
                <!-- ... other brands ... -->
            </div>
        </div>

        <!-- Product Grid -->
        <div class="col-md-9">
             <div class="row">
                  <!-- Loading State -->
                  <div v-if="loading" class="col-12 text-center py-5">
                      <div class="spinner-border text-danger" role="status">
                          <span class="visually-hidden">Loading...</span>
                      </div>
                  </div>

                  <!-- Empty State -->
                  <div v-else-if="products.length === 0" class="col-12 text-center py-5">
                      <p class="text-muted">Ni najdenih izdelkov.</p>
                  </div>

                  <!-- Products Grid -->
                  <div
                    v-else
                    v-for="product in products"
                    :key="product.id"
                    class="col-md-4 mb-4"
                  >
                    <div class="card h-100 border-0 shadow-sm product-card">
                        <div class="position-relative">
                             <span class="position-absolute top-0 start-0 badge bg-warning text-dark m-2">Najboljša cena</span>
                             <img :src="product.image_url || 'https://via.placeholder.com/300x200'" class="card-img-top p-3" :alt="product.name" style="object-fit: contain; height: 200px;">
                        </div>

                      <div class="card-body">
                        <h6 class="card-title text-truncate" :title="product.name">{{ product.name }}</h6>
                        <div class="mt-2 text-start">
                             <p class="h5 fw-bold mb-0 text-dark">
                              {{ formatPrice(product.price) }} {{ product.currency }}
                            </p>
                            <small class="text-muted">v {{ product.retailer || 'Trgovina' }}</small>
                        </div>
                      </div>
                      <div class="card-footer bg-white border-top-0">
                          <button class="btn btn-danger w-100 fw-bold">Primerjaj cene</button>
                      </div>
                    </div>
                  </div>
            </div>

            <!-- Pagination -->
            <div v-if="nextPageUrl" class="text-center mt-4 mb-5">
                <button @click="loadMore" class="btn btn-outline-danger" :disabled="loadingMore">
                    {{ loadingMore ? 'Nalaganje...' : 'Naloži več' }}
                </button>
            </div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useRoute } from 'vue-router';

// State
const products = ref([]);
const sidebarCategories = ref([]);
const loading = ref(true);
const loadingMore = ref(false);
const nextPageUrl = ref(null);

const route = useRoute();

/**
 * Fetch subcategories for the sidebar.
 * We hardcode 'tv-sprejemniki' as the parent slug based on the user request,
 * OR we could use the current route info if we had dynamic routing for categories.
 */
const fetchSidebarCategories = async () => {
    try {
        // Fetch children of "TV Sprejemniki"
        const res = await axios.get('/api/categories/tv-sprejemniki');
        // If the API returns the category object with children
        if (res.data && res.data.children) {
            sidebarCategories.value = res.data.children;
        }
    } catch (e) {
        console.warn("Could not fetch sidebar categories (maybe 'tv-sprejemniki' slug doesn't exist in DB?)", e);
        // Fallback or empty
    }
};

const fetchProducts = async (url = '/api/products') => {
    try {
        // Pass filter if needed
        const queryParams = route.query.category ? { params: { category: route.query.category } } : {};

        // If loading infinite scroll, we don't reset everything
        // But if 'url' is the default /api/products, we might be reloading.
        // For simplicity, we just append or set.

        const res = await axios.get(url, queryParams);

        if (url === '/api/products') {
             products.value = res.data.data;
        } else {
             products.value = [...products.value, ...res.data.data];
        }
        nextPageUrl.value = res.data.next_page_url;
    } catch (error) {
        console.error("Error fetching products:", error);
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
};

const loadMore = () => {
    if (nextPageUrl.value) {
        loadingMore.value = true;
        fetchProducts(nextPageUrl.value);
    }
};

const formatPrice = (value) => {
    if (!value) return "0,00";
    return new Intl.NumberFormat('sl-SI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
};

onMounted(() => {
    fetchProducts();
    fetchSidebarCategories();
});
</script>

<style scoped>
.product-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
}
.sidebar-link:hover {
    color: #d32f2f !important; /* Bootstrap Danger color */
    background-color: #f8f9fa;
    padding-left: 5px;
    transition: all 0.2s;
}
</style>
