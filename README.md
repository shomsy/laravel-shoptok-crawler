# Shoptok Crawler & Advanced Product Catalog

A high-performance Laravel 11 & Vue.js 3 application designed to crawl complex hierarchical data from [Shoptok.si](https://www.shoptok.si) and present it through a state-of-the-art interactive catalog.

Originally built as a technical interview task, this project has been evolved with advanced architecture, performance optimizations, and robust data integrity features.

## 🚀 Key Features

### 🛠️ Advanced Crawling Engine

- **Recursive Hierarchy Discovery**: Automatically discovers and traverses the full category tree (e.g., from "TV sprejemniki" down to "Televizorji" and "TV dodatki").
- **Selenium Integration**: Uses Selenium for robust HTML retrieval, ensuring dynamic content is correctly captured.
- **Idempotent Batch Upserts**: Optimized delivery to the database using batch `upsert` logic, significantly reducing DB hits and ensuring no duplicate products.
- **Recursion Safeguards**: Implements BFS (Breadth-First Search) and model-level listeners to prevent infinite loops and circular dependencies in the category tree.

### ⚡ Performance & Caching

- **Smart Cache Invalidation**: Implements an intelligent caching strategy that uses the `max(updated_at)` timestamp of products in the cache key. The cache auto-refreshes the moment the crawler updates any data.
- **Database Optimization**: Utilizes recursive ancestor/descendant lookup algorithms for fast scoping in large product sets.
- **Sidebar Aggregation**: Dynamically calculates available brands and categories using optimized SQL queries that respect active filters and search terms.

### 🎨 Modern Frontend (Vue.js 3 SPA)

- **Interactive UI**: A sleek, responsive interface built with Vue 3 and Bootstrap 5.
- **Real-time Filtering**: Supports multi-brand selection, sorting (Price/Popularity), and full-text search.
- **Global & Scoped Navigation**: Handles navigation seamlessly between the global product list and specific category branches with accurate breadcrumb generation.

## 🛠️ Tech Stack

- **Backend**: Laravel 11 (PHP 8.3)
- **Frontend**: Vue.js 3 (Composition API), Vite, Bootstrap 5
- **Database**: MySQL 8
- **Cache/Session**: Redis
- **Automation**: Laravel Sail (Docker), Selenium

## 📦 Installation & Setup

### 1. Requirements

Ensure you have **Docker** and **Docker Compose** installed.

### 2. Basic Setup

```bash
# Clone the repository
git clone https://github.com/shomsy/laravel-shoptok-crawler
cd laravel-shoptok-crawler

# Install dependencies (via Sail helper if local PHP is missing)
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php83-composer:latest composer install --ignore-platform-reqs

# Launch the environment
./vendor/bin/sail up -d

# Run migrations
./vendor/bin/sail artisan migrate
```

### 3. Running the Crawler

The crawler can be triggered via specialized Artisan commands:

```bash
# Crawl the entire TV hierarchy (Recursive mode)
./vendor/bin/sail artisan crawl:tv-sprejemniki

# Crawl only the flat "Televizorji" category
./vendor/bin/sail artisan crawl:televizorji
```

## 🏗️ Architecture Highlights

- **Action/Service Pattern**: Logic is decoupled into dedicated Actions (e.g., `CrawlShoptokCategoryAction`) and Services (e.g., `ShoptokProductParserService`), ensuring maximum testability and maintainability.
- **Unified Scoping**: The product filtering logic is centralized in the `Product` model scope, ensuring consistent behavior between the Search API and the Category API.
- **Safety First**: The application includes model "booters" to protect against data corruption during high-concurrency crawl operations.

---
Built with ❤️ for performance and scalability.
