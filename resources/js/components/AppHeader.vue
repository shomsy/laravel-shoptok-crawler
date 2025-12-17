<template>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
        <div class="container">
            <router-link class="navbar-brand fw-bold text-danger fs-3" to="/">Shoptok Crawler</router-link>

            <button aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"
                    class="navbar-toggler" data-bs-target="#navbarSupportedContent"
                    data-bs-toggle="collapse" type="button">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div id="navbarSupportedContent" class="collapse navbar-collapse">
                <!-- Search Form -->
                <form class="d-flex ms-auto me-3 flex-grow-1" style="max-width: 600px;" @submit.prevent="handleSearch">
                    <div class="input-group">
                        <input
                            v-model="searchQuery"
                            aria-label="Search"
                            class="form-control bg-dark text-white border-secondary"
                            placeholder="Search products..."
                            type="search"
                        >
                        <button class="btn btn-outline-secondary" type="submit">🔍</button>
                    </div>
                </form>

                <ul class="navbar-nav mb-2 mb-lg-0">
                    <li class="nav-item">
                        <router-link class="btn btn-danger text-white fw-bold" to="/products">All Categories
                        </router-link>
                    </li>
                </ul>

                <div class="d-flex align-items-center ms-3">
                    <span class="me-2 text-muted small">Register Store</span>
                    <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center"
                         style="width: 35px; height: 35px;">
                        P
                    </div>
                    <span class="ms-2 fw-bold text-white">Petar Petrovic</span>
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
import {ref} from 'vue';
import {useRouter} from 'vue-router';

const searchQuery = ref('');
const router = useRouter();

const handleSearch = () => {
    if (searchQuery.value.trim()) {
        router.push({
            path: '/products',
            query: {search: searchQuery.value}
        });
    }
};
</script>

<style scoped>
.navbar {
    background-color: #1a1a1a !important; /* Darker theme per screenshot */
}

::placeholder {
    color: #aaa;
}
</style>
