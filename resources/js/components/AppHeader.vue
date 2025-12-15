<template>
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
      <router-link class="navbar-brand fw-bold text-danger fs-3" to="/">Shoptok</router-link>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <form class="d-flex ms-auto me-3 flex-grow-1" style="max-width: 600px;">
          <input class="form-control me-2" type="search" placeholder="Kaj iščeš?" aria-label="Search">
            <!-- Search icon can be added here -->
        </form>

        <ul class="navbar-nav mb-2 mb-lg-0">
             <li class="nav-item">
                <router-link class="btn btn-danger text-white fw-bold" to="/products">Vse kategorije</router-link>
            </li>
        </ul>
        
         <div class="d-flex align-items-center ms-3">
            <span class="me-2 text-muted small">Registrirajte trgovino</span>
             <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                D
             </div>
             <span class="ms-2 fw-bold">Dušan Ivić</span>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup>
// Header logic if needed
</script>
