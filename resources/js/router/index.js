import { createRouter, createWebHistory } from 'vue-router';

// Lazy optimized imports
const Home = () => import('../views/Home.vue');
const ProductList = () => import('../views/ProductList.vue');

const routes = [
    { path: '/', component: Home },
    { path: '/products', component: ProductList },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
