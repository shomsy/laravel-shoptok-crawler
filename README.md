# 🧠 Shoptok Crawler & Advanced Product Catalog

![Laravel](https://img.shields.io/badge/Laravel-12.x-red?logo=laravel)
![Vue.js](https://img.shields.io/badge/Vue-3.x-brightgreen?logo=vue.js)
![Redis](https://img.shields.io/badge/Cache-Redis-blue)
![Tests](https://img.shields.io/badge/Tests-88%20passing-success)
![License](https://img.shields.io/badge/License-MIT-lightgrey)

A high-performance **Laravel 12 + Vue.js 3** application built to crawl and manage complex hierarchical data from  
[Shoptok.si](https://www.shoptok.si), providing a lightning-fast API and modern single-page frontend.

Originally created as a **technical interview task**, this project has evolved into a **production-ready, scalable
architecture** featuring intelligent caching, recursive data discovery, and an elegant Vue.js interface.

---

## 🚀 Key Features

### 🛠️ Advanced Crawling Engine

- **Recursive Hierarchy Discovery** — automatically crawls and maps the full category tree (e.g. `"TV sprejemniki"` →
  `"Televizorji"` → `"TV dodatki"`).
- **Dual-Engine Crawler** — supports both native HTTP and Selenium (headless Chrome) for JavaScript-heavy pages.
- **Batch Upsert Logic** — idempotent product synchronization with minimal database hits.
- **Circular Safety** — BFS-based traversal and model-level validation prevent infinite recursion or self-parenting.

### ⚡ Performance & Caching

- **Smart Cache Invalidation** — cache keys include `max(updated_at)`, guaranteeing automatic refresh after data
  changes.
- **Brand Aggregation** — optimized SQL scans generate brand filters dynamically, respecting active queries.
- **Sidebar Caching** — recursive category trees cached in Redis for sub-100 ms API response times.
- **Optimized Eloquent Queries** — indexes and scope-level filtering ensure minimal DB load even on large datasets.

### 🎨 Modern Frontend (Vue.js 3 SPA)

- **Fully Interactive Interface** — Vue 3 (Composition API) + Bootstrap 5 for a clean, responsive layout.
- **Real-Time Filtering** — multi-brand selection, price sorting, and instant search with API syncing.
- **Breadcrumb Navigation** — built dynamically via category recursion for precise hierarchy mapping.
- **Pagination & State Management** — seamless transitions between category routes using Vue Router.

---

## 🧪 Testing

This project includes an extensive **automated test suite (88 tests)** covering:

- Recursive category hierarchy logic
- Product filtering and search scope behavior
- API responses, cache invalidation, and pagination
- Crawler idempotency and data consistency

Run the tests via:

```bash
./vendor/bin/sail artisan test
```

---

## 🔗 API Overview

| Endpoint                     | Description                                                                 |
|------------------------------|-----------------------------------------------------------------------------|
| `GET /api/products`          | Paginated product list with search, brand, and sort filters                 |
| `GET /api/categories`        | Returns all root categories for the sidebar                                 |
| `GET /api/categories/{slug}` | Returns products for a specific category (recursively includes descendants) |

All endpoints are cached and optimized for quick responses (< 100 ms with warm cache).

---

## 🛠️ Tech Stack

| Layer               | Technology                              |
|---------------------|-----------------------------------------|
| **Backend**         | Laravel 12 (PHP 8.3)                    |
| **Frontend**        | Vue.js 3 + Vite + Bootstrap 5           |
| **Database**        | MySQL 8                                 |
| **Cache / Session** | Redis                                   |
| **Automation**      | Laravel Sail (Docker)                   |
| **Crawling**        | GuzzleHTTP + Selenium (Headless Chrome) |

---

## 📦 Installation & Setup

### 1️⃣ Requirements

- **Docker** and **Docker Compose**

### 2️⃣ Basic Setup

```bash
# Clone the repository
git clone https://github.com/shomsy/laravel-shoptok-crawler
cd laravel-shoptok-crawler

# Install dependencies
docker run --rm   -v "$(pwd):/var/www/html"   -w /var/www/html   laravelsail/php83-composer:latest   
        composer require laravel/sail --dev --ignore-platform-reqs

# Create your local environment configuration by copying the example file: 
cp .env.example .env

# Launch Sail
./vendor/bin/sail up -d

# Run migrations
./vendor/bin/sail artisan migrate
```

### 3️⃣ Run the Crawler

```bash
# Crawl the full "TV Sprejemniki" hierarchy recursively
./vendor/bin/sail artisan crawl:tv-sprejemniki

# Or crawl a single flat category (e.g. "Televizorji")
./vendor/bin/sail artisan crawl:televizorji
```

---

## 🏗️ Architecture Highlights

- **Action/Service Pattern** — decouples logic into reusable, testable components (`CrawlShoptokCategoryAction`,
  `ShoptokApiService`, etc.).
- **Unified Filtering Logic** — the `Product::filter()` scope powers both the search and category endpoints.
- **Hierarchical Models** — `Category::getDescendantIds()` recursively retrieves all children using BFS traversal.
- **Smart Cache Layer** — versioned cache (`v8`) invalidates automatically after DB updates.
- **Model Boot Protection** — prevents circular parent assignments and ensures referential integrity.
- **Breadcrumb Builder** — generates human-readable navigation chains dynamically for any depth.

---

## 💡 Example API Response

**`GET /api/categories/tv-dodatki`**

```json
{
  "category": {
    "id": 3,
    "name": "TV dodatki",
    "slug": "tv-dodatki"
  },
  "breadcrumbs": [
    {
      "name": "TV sprejemniki",
      "url": "/category/tv-sprejemniki"
    },
    {
      "name": "Televizorji",
      "url": "/category/televizorji"
    },
    {
      "name": "TV dodatki",
      "url": "/category/tv-dodatki"
    }
  ],
  "available_brands": [
    "Samsung",
    "Sony",
    "LG",
    "Vivax"
  ],
  "products": {
    "data": [
      ...
    ],
    "total": 294,
    "per_page": 20,
    "last_page": 15
  }
}
```

---

## 🧩 Project Structure

```
app/
 ├── Actions/Shoptok/...
 ├── Console/Commands/CrawlTvSprejemnikiCommand.php
 ├── Http/Controllers/Api/
 │     ├── CategoryController.php
 │     └── ProductController.php
 ├── Models/
 │     ├── Category.php
 │     └── Product.php
 ├── Services/Shoptok/
 │     ├── ShoptokApiService.php
 │     └── ShoptokSeleniumService.php
 └── Data/Shoptok/CrawlResult.php
```

---

## 🧑‍💻 Author

**Developed by Miloš Stanković [@shomsy](https://github.com/shomsy)**

Senior PHP Developer · Clean Architect · API Design Enthusiast

---
Built with ❤️ for **performance, maintainability, and technical elegance.**
