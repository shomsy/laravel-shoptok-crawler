<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Products</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container py-4">
    <div class="row g-4">

        <aside class="col-12 col-lg-3">
            <div class="card">
                <div class="card-header">
                    TV sprejemniki categories
                </div>
                <div class="list-group list-group-flush">
                    <a class="list-group-item list-group-item-action {{value: $activeCategory === '' ? 'active' : '' }}"
                       href="{{value: route(name: 'products.index') }}">
                        All products
                    </a>

                    @foreach($sidebarCategories as $cat)
                        <a class="list-group-item list-group-item-action {{value: $activeCategory === $cat->slug ? 'active' : '' }}"
                           href="{{value: route(name: 'products.index', parameters: ['category' => $cat->slug]) }}">
                            {{value: $cat->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        </aside>

        <main class="col-12 col-lg-9">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h1 class="h4 m-0">Products</h1>
                <div class="text-muted small">
                    Showing {{value: $products->firstItem() ?? 0 }}–{{value: $products->lastItem() ?? 0 }}
                    of {{value: $products->total() }}
                </div>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @foreach($products as $p)
                    <div class="col">
                        <div class="card h-100">
                            @if($p->image_url)
                                <img src="{{value: $p->image_url }}" class="card-img-top"
                                     style="object-fit:cover; height: 180px;" alt="">
                            @endif
                            <div class="card-body">
                                <div class="small text-muted mb-1">{{value: $p->category?->name }}</div>
                                <div class="fw-semibold">{{value: $p->name }}</div>
                            </div>
                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <div class="fw-bold">{{value: number_format(num: (float)$p->price, decimals: 2, decimal_separator: ',', thousands_separator: '.') }}
                                    €
                                </div>
                                <a class="btn btn-sm btn-primary" href="{{value: $p->product_url }}" target="_blank"
                                   rel="noreferrer">
                                    Open
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{value: $products->links('pagination::bootstrap-5') }}
            </div>
        </main>

    </div>
</div>
</body>

</html>
