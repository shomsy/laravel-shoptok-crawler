<template>
  <div class="home-view">
    <!-- Hero Section / Banner -->
    <div class="bg-dark text-white py-5 mb-5" style="background: linear-gradient(90deg, #1a1a1a 0%, #333 100%);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-4 fw-bold">Najboljša cena</h1>
                    <p class="lead">Poiščite izdelke z največjimi prihranki danes.</p>
                    <router-link to="/products" class="btn btn-danger btn-lg mt-3">Razišči izdelke</router-link>
                </div>
                <!-- <div class="col-md-6"> -->
                     <!-- Hero Image Placeholder -->
                <!-- </div> -->
            </div>
        </div>
    </div>

    <div class="container">
        <h2 class="fw-bold mb-4">Priljubljene kategorije</h2>
        <div class="row">
             <div class="col-md-4 mb-3">
                 <div class="card h-100 border-0 shadow-sm text-center p-4 hover-scale">
                     <h5>Televizorji</h5>
                     <router-link to="/products?category=televizorji" class="stretched-link"></router-link>
                 </div>
             </div>
             <div class="col-md-4 mb-3">
                 <div class="card h-100 border-0 shadow-sm text-center p-4 hover-scale">
                     <h5>Prenosniki</h5>
                     <router-link to="/products" class="stretched-link"></router-link>
                 </div>
             </div>
             <div class="col-md-4 mb-3">
                 <div class="card h-100 border-0 shadow-sm text-center p-4 hover-scale">
                     <h5>Telefoni</h5>
                     <router-link to="/products" class="stretched-link"></router-link>
                 </div>
             </div>
        </div>
    </div>
  </div>
</template>

<style scoped>
.hover-scale {
    transition: transform 0.2s;
}
.hover-scale:hover {
    transform: translateY(-5px);
}
</style>
