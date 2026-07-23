<?php

declare(strict_types=1);

namespace App\Modules\Auction\Presentation\Controllers;

use App\Modules\Auction\Infrastructure\Persistence\Models\Category;
use App\Modules\Auction\Presentation\Resources\CategoryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoriesController
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::orderBy('name')->get());
    }
}
