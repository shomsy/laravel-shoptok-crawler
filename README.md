# Shoptok Crawler & Product Catalog

A Laravel-based crawler and Vue.js SPA for scraping and displaying TV products from Shoptok.si.

## 🚀 Features

- **Crawler**: Scrapes products from Shoptok categories, handling pagination, subcategories (recursion), and data
  extraction (price, image, brand).
- **Idempotent Storage**: Uses `updateOrCreate` and unique hashes to prevent duplicates.
- **Vue.js Frontend**: Modern SPA with Bootstrap 5, displaying products in a responsive grid.
- **Filtering**: Sort by price and filter by Manufacturer (Brand).
- **Sub-menu**: Sidebar dynamically lists subcategories crawled from the root category.

## 🛠️ Usage

### 1. Setup & Migration

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

### 2. Run Crawler

To crawl the entire "TV sprejemniki" hierarchy (including subcategories like "Televizorji" and "TV dodatki"):

```bash
./vendor/bin/sail artisan crawl:tv-sprejemniki
```

### 3. Frontend

Visit `http://localhost` to view the product catalog.

---

## ⚠️ Known Limitations & Implementation Notes

- **Language**: The source site (Shoptok.si) is in Slovenian. Category names and product titles are kept as-is. The UI
  labels are in English.
- **Images**: Images are extracted using `src`, `data-src`, or `data-original`. Some products might still miss images if
  Shoptok uses complex dynamic loading not captured by the static HTML parser.
- **Brands**: Shoptok does not provide a structured "Brand" field. We extract brands from product titles using a Regex (
  Samsung, LG, Sony, etc.). Products with obscure brand names in the title might not match.
- **WAF/Blocking**: Shoptok has strict rate limiting. The crawler sleeps between requests (`usleep`), but extensive
  crawling might still trigger temporary IP blocks.

## 🏗️ Tech Stack

- **Backend**: Laravel 11, SQLite/MySQL
- **Frontend**: Vue.js 3, Bootstrap 5, Vite
- **Crawling**: Symfony DOM Crawler, Selenium Service (optional context)
  (Laravel + Sail)

## Overview

This project is a robust crawler for [shoptok.si](https://www.shoptok.si). It fetches products from the "Televizorji"
and "TV sprejemniki" categories, stores them in a MySQL database, and displays them via a paginated Bootstrap UI.

## Features

- **Robust Parsing**: Handles various HTML structures and item blocks (using `symfony/dom-crawler`).
- **Idempotency**: Products are identified by a hash of their URL (`external_id`), preventing duplicates during
  subsequent crawls.
- **Pagination handling**: Automatically traverses all pages of a category.
- **Recursive Categories**: Supports root categories and dynamic subcategories for "TV sprejemniki".
- **Dockerized**: Full environment via Laravel Sail (MySQL, Redis, Mailpit).

## Requirements

- Docker Desktop
- PHP 8.2+
- Composer

## Setup

1. **Clone the repository**

   ```bash
   git clone <repo-url>
   cd shoptok-crawler
   ```

2. **Install Dependencies**

   ```bash
   # If you have PHP local
   composer install
   
   # OR using Docker (if PHP is not local)
   # NOTE: You MUST run 'composer update' if you are upgrading from previous version to install php-webdriver
   docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php83-composer:latest composer install --ignore-platform-reqs
   ```

3. **Start Environment (with Selenium)**

   ```bash
   ./vendor/bin/sail up -d
   ```

*Ensure the `selenium` container is running.*

4. **Run Migrations**

   ```bash
   ./vendor/bin/sail artisan migrate
   ```

## Usage

### 1. Crawl "Televizorji" (Basic)

Crawls the flat list of televisions.

```bash
./vendor/bin/sail artisan crawl:televizorji
```

### 2. Crawl "TV sprejemniki" (Advanced)

Crawls the root category, discovers subcategories (sidebar), and crawls each of them.

```bash
./vendor/bin/sail artisan crawl:tv-sprejemniki
```

### 3. View Products

Open default browser at [http://localhost/products](http://localhost/products).

## Architecture

- **Crawlers**: Located in `app/Crawlers/Shoptok`.
    - `ShoptokClient`: Handles HTTP requests with retry/timeout logic.
    - `TelevizorjiCrawler`: Logic for iterating pages and extracting items.
    - `TvSprejemnikiCrawler`: Logic for discovering subcategories and delegating to `TelevizorjiCrawler`.
- **Parsers**:
    - `ProductParser`: Extracts price, name, image from DOM elements.
    - `CategoryParser`: Extracts subcategory links.
- **Services**:
    - `ProductUpsertService`: Handles `updateOrCreate` logic for database consistency.
- **UI**:
    - Bootstrap 5 based grid layout.
    - Sidebar for filtering by parsed subcategories.
