<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\StorefrontSchoolResource;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SchoolController extends Controller
{
    /**
     * GET /api/v1/storefront/schools?search=&page=&per_page=
     *
     * Approved schools only, paginated. Safe to call from the theme on every product page.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = trim($validated['search'] ?? '');

        $schools = School::approved()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('city', 'like', $like));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return StorefrontSchoolResource::collection($schools);
    }

    /**
     * GET /api/v1/storefront/schools/{ulid}
     *
     * Lets the theme re-validate a school stored in the cart. 404 once it is no longer approved.
     */
    public function show(string $id): StorefrontSchoolResource
    {
        $school = School::approved()->where('ulid', $id)->firstOrFail();

        return new StorefrontSchoolResource($school);
    }
}
