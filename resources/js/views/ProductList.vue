<template>
    <div class="container mt-4">

        <!-- 🚨 Error State -->
        <div v-if="error" class="alert alert-danger d-flex align-items-center" role="alert">
            <svg class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" fill="currentColor" height="24" viewBox="0 0 16 16"
                 width="24" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
            </svg>
            <div>
                <strong>Oops!</strong> {{ error }}
                <button class="btn btn-outline-danger btn-sm ms-3" @click="fetchData()">Try Again</button>
            </div>
        </div>

        <!-- 🍞 Breadcrumbs -->
        <nav v-if="breadcrumbs.length > 0" aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#" @click.prevent="router.push('/')">Home</a></li>
                <li v-for="(crumb, index) in breadcrumbs" :key="crumb.slug" :class="{ active: index === breadcrumbs.length - 1 }"
                    class="breadcrumb-item">
                    <a v-if="index !== breadcrumbs.length - 1" href="#"
                       @click.prevent="router.push({ query: { category: crumb.slug } })">{{ crumb.name }}</a>
                    <span v-else>{{ crumb.name }}</span>
                </li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold">{{ currentCategoryName || 'Products' }} <span v-if="totalProducts > 0"
                                                                              class="text-muted fs-6">({{
                    totalProducts
                }})</span></h3>
            <select v-model="sortOption" class="form-select w-auto border-secondary" @change="updateFilters">
                <option value="">Popularity</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
            </select>
        </div>

        <!-- Layout: Sidebar + Grid -->
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 mb-4">
                <div class="p-3 bg-white border rounded shadow-sm">
                    <!-- Categories -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-uppercase text-secondary" style="letter-spacing: 0.5px;">
                            Categories</h6>
                        <button
                            v-if="Object.keys(route.query).length > 0"
                            class="btn btn-link text-decoration-none p-0 text-muted"
                            style="font-size: 0.75rem;"
                            @click="router.push({ query: {} })"
                        >
                            Reset
                        </button>
                    </div>

                    <!-- Skeleton Sidebar -->
                    <div v-if="loading && sidebarCategories.length === 0">
                        <div v-for="n in 5" :key="n" class="placeholder-glow mb-2">
                            <span class="placeholder col-8 bg-light"></span>
                        </div>
                    </div>

                    <ul v-else class="list-group list-group-flush mb-4">
                        <li v-for="level1 in sidebarCategories" :key="level1.id" class="list-group-item bg-transparent border-0 px-0 py-1">
                            <!-- Parent Category Link (Clickable now) -->
                            <div class="d-flex justify-content-between align-items-center">
                                <a
                                    :class="(route.query.category === level1.slug || (!route.query.category && level1.slug === 'tv-sprejemniki')) ? 'text-danger fw-bold' : 'text-dark'"
                                    class="text-decoration-none flex-grow-1"
                                    href="#"
                                    @click.prevent="level1.slug === 'tv-sprejemniki' ? router.push({ query: {} }) : router.push({ query: { ...route.query, category: level1.slug } })" 
                                >
                                    {{ level1.name }}
                                </a>
                                <span v-if="level1.children && level1.children.length > 0" class="small text-muted ms-2 cursor-pointer">
                                     ▼
                                </span>
                            </div>

                            <!-- Level 2 Subcategories (Always Visible if Present) -->
                            <ul
                                v-if="level1.children && level1.children.length > 0"
                                class="list-unstyled ms-3 mt-2 border-start ps-2 border-danger"
                            >
                                <li v-for="level2 in level1.children" :key="level2.id" class="mb-1">
                                    <a
                                        :class="route.query.category === level2.slug ? 'text-danger fw-bold' : 'text-secondary'"
                                        class="text-decoration-none d-block py-1"
                                        href="#"
                                        @click.prevent="router.push({ query: { ...route.query, category: level2.slug } })"
                                    >
                                        {{ level2.name }}
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>

                    <!-- Manufacturer Filter -->
                    <div v-if="availableBrands.length > 0" class="mt-4 pt-3 border-top">
                        <h6 class="fw-bold mb-2 text-uppercase text-secondary" style="font-size: 0.8rem;">Brands</h6>
                        <div v-for="brand in availableBrands" :key="brand" class="form-check">
                            <input :id="brand" v-model="selectedBrands" :value="brand" class="form-check-input"
                                   type="checkbox" @change="updateFilters"/>
                            <label :for="brand" :title="brand" class="form-check-label text-truncate w-100">{{
                                    brand
                                }}</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="col-md-9">
                <div class="row g-3">
                    <!-- Skeleton Products (Loading State) -->
                    <div v-if="loading && products.length === 0" class="row g-3">
                        <div v-for="n in 6" :key="n" class="col-md-4">
                            <div aria-hidden="true" class="card h-100 border-0 shadow-sm">
                                <div class="card-img-top bg-light" style="height: 200px;"></div>
                                <div class="card-body">
                                    <h5 class="card-title placeholder-glow">
                                        <span class="placeholder col-6"></span>
                                    </h5>
                                    <p class="card-text placeholder-glow">
                                        <span class="placeholder col-7"></span>
                                        <span class="placeholder col-4"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="products.length === 0" class="col-12 text-center py-5">
                        <div class="mb-3">📦</div>
                        <h4 class="text-muted">No products found here.</h4>
                        <p class="text-secondary">Try selecting a different category or clearing filters.</p>
                        <button class="btn btn-primary mt-2" @click="router.push({ query: {} })">Browse All</button>
                    </div>

                    <!-- Products Grid -->
                    <div v-for="product in products" v-else :key="product.id" class="col-md-4 mb-4">
                        <div class="card h-100 border-0 shadow-sm product-card position-relative overflow-hidden group">
                            <!-- Image -->
                            <div class="position-relative bg-white text-center p-3" style="height: 220px;">
                                <img
                                    :alt="product.name"
                                    :src="product.image_url || 'https://via.placeholder.com/300x200?text=No+Image'"
                                    class="img-fluid h-100"
                                    loading="lazy"
                                    style="object-fit: contain; transition: transform 0.3s;"
                                />
                            </div>

                            <div class="card-body d-flex flex-column bg-white">
                                <!-- Brand badge -->
                                <div class="mb-1">
                                    <span v-if="product.brand" class="badge bg-light text-secondary border fw-normal">{{
                                            product.brand || 'Generic'
                                        }}</span>
                                </div>
                                <h6 :title="product.name" class="card-title text-dark fw-bold mb-2 text-truncate-2"
                                    style="height: 2.5rem; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                    {{ product.name }}
                                </h6>
                                <div class="mt-auto">
                                    <h5 class="fw-bold text-primary mb-0">
                                        {{ formatPrice(product.price) }} <span
                                        class="fs-6 text-dark">{{ product.currency }}</span>
                                    </h5>
                                    <a :href="product.product_url" class="btn btn-outline-primary btn-sm w-100 mt-2"
                                       target="_blank">View Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Infinite Scroll Sentinel -->
                <!-- Standard Pagination -->
                <nav v-if="paginationLinks.length > 3" class="mt-4 d-flex justify-content-center">
                    <ul class="pagination">
                        <li
                            v-for="(link, index) in paginationLinks"
                            :key="index"
                            :class="['page-item', { active: link.active, disabled: !link.url }]"
                        >
                            <a
                                class="page-link"
                                href="#"
                                v-html="link.label"
                                @click.prevent="changePage(link.url)"
                            ></a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</template>

<script setup>
import {nextTick, onMounted, onUnmounted, ref, watch} from 'vue';
import axios from 'axios';
import {useRoute, useRouter} from 'vue-router';

// State
const products = ref([]);
const breadcrumbs = ref([]);
const sidebarCategories = ref([]);
const currentCategoryName = ref('');
const currentCategoryObject = ref(null);
const loading = ref(true);
const paginationLinks = ref([]);
const error = ref(null);
const totalProducts = ref(0);

// Filters
const sortOption = ref('');
const selectedBrands = ref([]);
const availableBrands = ref([]);

// Router
const route = useRoute();
const router = useRouter();

/* 🧭 Helpers */
const formatPrice = (value) =>
    new Intl.NumberFormat('sl-SI', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(Number(value || 0));

/* 🔄 Unified Data Fetching */
const fetchData = async (isLoadMore = false, queryParams = null) => {
    // Determine active query params (Use passed args or fallback to current route)
    const currentParams = queryParams || route.query;
    
    loading.value = true;
    error.value = null;

    try {
        const currentCategorySlug = currentParams.category;

        // 🧠 Dynamic Logic:
        // If we have a category slug, hit the Category Controller (Deep tree logic).
        // If not, hit the Product Controller (Global search/browse).
        let url;
        if (currentCategorySlug) {
            url = `/api/categories/${currentCategorySlug}`;
        } else {
            console.log("🌍 No category selected. Switching to Global Product Search.");
            url = `/api/products`;
        }

        // Build API Params
        const apiParams = {
            ...currentParams,
        };
        // Remove category from query params as it's in the URL path (only for category endpoint)
        if (currentCategorySlug) {
            delete apiParams.category;
        }

        console.log(`📡 Fetching: ${url}`, apiParams);

        const res = await axios.get(url, {params: apiParams});
        const data = res.data;

        // 1. Update Context (Sidebar, Breadcrumbs)
        currentCategoryName.value = data.category?.name || 'Products';
        sidebarCategories.value = data.sidebar_tree || [];
        currentCategoryObject.value = data.category;
        availableBrands.value = data.available_brands || [];
        breadcrumbs.value = data.breadcrumbs || [];
        totalProducts.value = data.products.total || 0;

        // Overwrite products list & links
        products.value = data.products.data;
        paginationLinks.value = data.products.links || [];

    } catch (e) {
        console.error('❌ Error fetching data:', e);
        if (e.response && e.response.status === 404) {
            error.value = "Category not found. Please try a different category.";
        } else {
            error.value = "Failed to load products. Please check your connection.";
        }
        products.value = [];
        paginationLinks.value = [];
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
};

/* 🔄 State Sync & Filters */
const syncStateFromUrl = () => {
    sortOption.value = route.query.sort || '';
    selectedBrands.value = route.query.brand ? route.query.brand.split(',') : [];
};

const updateFilters = () => {
    router.push({
        query: {
            ...route.query,
            sort: sortOption.value || undefined,
            brand: selectedBrands.value.length > 0 ? selectedBrands.value.join(',') : undefined,
            page: 1 // Reset to page 1 on filter change
        },
    });
};

const changePage = (url) => {
    if (!url) return;
    try {
        const targetUrl = new URL(url);
        const page = targetUrl.searchParams.get('page');
        if (page) {
            console.log("📄 Changing to page:", page);
            // Scroll to the top of the products grid
            window.scrollTo({ top: 0, behavior: 'smooth' });
            router.push({ query: { ...route.query, page } });
        }
    } catch (e) {
        console.error("❌ Pagination Error:", e);
    }
};

/* 🚀 Lifecycle */
onMounted(() => {
    syncStateFromUrl();
    fetchData();
});

/* 👀 Watch Query Changes */
watch(() => route.query, (newQuery, oldQuery) => {
    // Deep compare to avoid redundant fetches
    if (JSON.stringify(newQuery) === JSON.stringify(oldQuery)) return;

    console.log('🌍 Route changed, refetching...', newQuery);
    syncStateFromUrl();
    fetchData(false, newQuery);
});
</script>

<style scoped>
.product-card {
    transition: all 0.3s ease;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 1rem 3rem rgba(0, 0, 0, .1) !important;
}

.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Transitions */
.slide-enter-active, .slide-leave-active {
    transition: max-height 0.3s ease, opacity 0.3s ease;
    max-height: 200px;
    opacity: 1;
    overflow: hidden;
}

.slide-enter-from, .slide-leave-to {
    max-height: 0;
    opacity: 0;
}
</style>
