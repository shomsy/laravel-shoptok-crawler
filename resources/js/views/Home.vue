<template>
    <div class="home-view">
        <!-- Hero Section / Banner -->
        <div class="bg-dark text-white py-5 mb-5" style="background: linear-gradient(90deg, #1a1a1a 0%, #333 100%);">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="display-4 fw-bold">Best Prices</h1>
                        <p class="lead">Find products with the biggest savings today.</p>
                        <router-link class="btn btn-danger btn-lg mt-3" to="/products">Explore Products</router-link>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <h2 class="fw-bold mb-4">Popular Categories</h2>

            <div v-if="loading" class="row">
                <div v-for="n in 3" :key="n" class="col-md-4 mb-3">
                    <div class="card h-100 border-0 shadow-sm p-5 bg-light placeholder-glow">
                        <span class="placeholder col-6 mx-auto"></span>
                    </div>
                </div>
            </div>

            <div v-else class="row">
                <div v-for="cat in categories" :key="cat.id" class="col-md-4 mb-3">
                    <div class="card h-100 border-0 shadow-sm text-center p-4 hover-scale">
                        <h5 class="fw-bold text-dark">{{ cat.name }}</h5>
                        <router-link
                            :to="cat.slug === 'tv-sprejemniki' ? '/products' : '/products?category=' + cat.slug"
                            class="stretched-link"></router-link>
                    </div>
                </div>

                <!-- Fallback if empty -->
                <div v-if="categories.length === 0" class="col-12 text-center text-muted">
                    No categories found. Run the crawler first!
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {onMounted, ref} from 'vue';
import axios from 'axios';

const categories = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const res = await axios.get('/api/categories');
        // Filter out empty ones if needed, or just take top 6
        categories.value = res.data.data.slice(0, 6);
    } catch (e) {
        console.error("Failed to load categories", e);
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.hover-scale {
    transition: transform 0.2s;
}

.hover-scale:hover {
    transform: translateY(-5px);
    background-color: #f8f9fa;
}
</style>
