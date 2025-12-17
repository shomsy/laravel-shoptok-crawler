<script setup>
import { ref, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";

const route = useRoute();
const router = useRouter();

const products = ref([]);
const sidebarTree = ref([]);
const availableBrands = ref([]);
const breadcrumbs = ref([]);
const currentPage = ref(1);
const totalPages = ref(1);
const selectedBrand = ref(null);
const sortOption = ref("popularity");
const isLoading = ref(true);
const categorySlug = ref(route.params.slug || route.query.category || null);
const expandedCategories = ref(new Set());

const toggleCategory = (id) => {
    if (expandedCategories.value.has(id)) {
        expandedCategories.value.delete(id);
    } else {
        expandedCategories.value.add(id);
    }
};

// Pagination
const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        fetchProducts(currentPage.value + 1);
    }
};
const prevPage = () => {
    if (currentPage.value > 1) {
        fetchProducts(currentPage.value - 1);
    }
};

// ✅ Main Fetcher
const fetchProducts = async (page = 1) => {
    try {
        isLoading.value = true;

        let url;
        if (categorySlug.value) {
            url = `/api/categories/${categorySlug.value}?page=${page}`;
            if (selectedBrand.value)
                url += `&brand=${encodeURIComponent(selectedBrand.value)}`;
            if (sortOption.value && sortOption.value !== "popularity")
                url += `&sort=${sortOption.value}`;
        } else {
            url = `/api/products?page=${page}`;
            if (selectedBrand.value)
                url += `&brand=${encodeURIComponent(selectedBrand.value)}`;
            if (sortOption.value && sortOption.value !== "popularity")
                url += `&sort=${sortOption.value}`;
        }

        const { data } = await axios.get(url);

        products.value = data.products.data;
        breadcrumbs.value = data.breadcrumbs;
        sidebarTree.value = data.sidebar_tree?.data || data.sidebar_tree || [];

        // Auto-expand roots by default
        sidebarTree.value.forEach(cat => expandedCategories.value.add(cat.id));

        availableBrands.value = data.available_brands || [];
        totalPages.value = data.products.last_page;
        currentPage.value = data.products.current_page;
    } catch (e) {
        console.error("Error loading products:", e);
    } finally {
        isLoading.value = false;
    }
};

// Watch for route changes (Params OR Query)
watch(
    () => [route.params.slug, route.query.category],
    ([newSlug, newQueryCat]) => {
        // 🧹 Resetovanje stanja pre novog fetch-a
        products.value = [];
        breadcrumbs.value = [];
        // sidebarTree.value = []; // Opciono, ako želiš da i sidebar trepne

        categorySlug.value = newSlug || newQueryCat || null;
        fetchProducts(1);
    }
);

onMounted(() => {
    fetchProducts();
});
</script>

<template>
    <div class="container py-4">
        <!-- 🧭 Breadcrumbs -->
        <nav v-if="breadcrumbs.length" class="mb-3 small text-muted">
            <ol class="breadcrumb mb-0">
                <li
                    v-for="(crumb, index) in breadcrumbs"
                    :key="index"
                    class="breadcrumb-item"
                    :class="{ active: index === breadcrumbs.length - 1 }"
                >
                    <RouterLink
                        v-if="index !== breadcrumbs.length - 1"
                        :to="crumb.url"
                    >{{ crumb.name }}</RouterLink
                    >
                    <span v-else>{{ crumb.name }}</span>
                </li>
            </ol>
        </nav>

        <div class="row g-4">
            <!-- 🧱 Sidebar -->
            <div class="col-lg-3 col-md-4">
                <aside class="border-end pe-3">
                    <!-- Categories -->
                    <section v-if="sidebarTree.length">
                        <h6 class="fw-bold mb-2">CATEGORIES</h6>
                        <ul class="list-unstyled">
                            <li v-for="cat in sidebarTree" :key="cat.id" class="mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <RouterLink
                                        :to="`/category/${cat.slug}`"
                                        class="fw-bold text-danger text-decoration-none"
                                    >{{ cat.name }}</RouterLink>

                                    <span
                                        v-if="cat.children && cat.children.length"
                                        class="p-1"
                                    >
                                        <i class="bi bi-caret-up-fill text-dark"></i>
                                        <span v-if="false">▲</span> <!-- Fallback hider -->
                                    </span>
                                </div>

                                <ul
                                    v-if="cat.children && cat.children.length"
                                    class="ms-3 mt-2 list-unstyled"
                                >
                                    <li v-for="child in cat.children" :key="child.id" class="mb-1">
                                        <RouterLink
                                            :to="`/category/${child.slug}`"
                                            class="text-muted text-decoration-none"
                                            active-class="fw-bold text-dark"
                                        >
                                            {{ child.name }}
                                        </RouterLink>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                        <hr />
                    </section>

                    <!-- Brands -->
                    <section v-if="availableBrands.length">
                        <h6 class="fw-bold mb-2">BRANDS</h6>
                        <ul class="list-unstyled">
                            <li v-for="brand in availableBrands" :key="brand">
                                <label class="d-flex align-items-center gap-2">
                                    <input
                                        type="checkbox"
                                        :value="brand"
                                        v-model="selectedBrand"
                                        @change="fetchProducts(1)"
                                    />
                                    {{ brand }}
                                </label>
                            </li>
                        </ul>
                    </section>
                </aside>
            </div>

            <!-- 🛍️ Product Grid -->
            <div class="col-lg-9 col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0">
                        Products
                        <span class="text-muted small"
                        >({{ products.length ? products.length : 0 }})</span
                        >
                    </h4>

                    <!-- Sort -->
                    <select
                        v-model="sortOption"
                        @change="fetchProducts(1)"
                        class="form-select w-auto"
                    >
                        <option value="popularity">Popularity</option>
                        <option value="price_asc">Price: Low → High</option>
                        <option value="price_desc">Price: High → Low</option>
                    </select>
                </div>

                <div v-if="isLoading" class="text-center py-5">
                    <div class="spinner-border text-danger" role="status"></div>
                </div>

                <div v-else>
                    <div v-if="!products.length" class="alert alert-secondary">
                        No products found.
                    </div>

                    <div class="row g-3">
                        <div
                            v-for="product in products"
                            :key="product.id"
                            class="col-6 col-md-4 col-lg-3"
                        >
                            <div class="card h-100 shadow-sm border-0">
                                <img
                                    v-if="product.image_url"
                                    :src="product.image_url"
                                    :alt="product.name"
                                    class="card-img-top p-3"
                                />
                                <div class="card-body">
                  <span
                      v-if="product.brand"
                      class="badge bg-light text-dark mb-2"
                  >
                    {{ product.brand }}
                  </span>
                                    <h6 class="fw-bold mb-1 text-truncate">
                                        {{ product.name }}
                                    </h6>
                                    <p class="mb-0 text-danger fw-bold">
                                        {{ product.price.toFixed(2) }} {{ product.currency }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="totalPages > 1"
                        class="d-flex justify-content-center align-items-center mt-4 gap-3"
                    >
                        <button
                            class="btn btn-outline-secondary"
                            :disabled="currentPage === 1"
                            @click="prevPage"
                        >
                            ← Prev
                        </button>
                        <span>Page {{ currentPage }} / {{ totalPages }}</span>
                        <button
                            class="btn btn-outline-secondary"
                            :disabled="currentPage === totalPages"
                            @click="nextPage"
                        >
                            Next →
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.breadcrumb-item + .breadcrumb-item::before {
    content: "/";
}
.card-img-top {
    object-fit: contain;
    height: 180px;
}
</style>
