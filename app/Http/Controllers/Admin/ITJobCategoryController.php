<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ITJobCategory;
use App\Models\ITJobRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ITJobCategoryController extends Controller
{
    public function index()
    {
        // Note: ITJobCategory::jobRequests() is not usable here — it_job_requests.category
        // stores the category name as free text, not a category_id foreign key.
        $categories = ITJobCategory::orderBy('name')->get();

        $usageCounts = ITJobRequest::whereIn('category', $categories->pluck('name'))
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        $categories->each(function ($category) use ($usageCounts) {
            $category->requests_count = $usageCounts->get($category->name, 0);
        });

        return Inertia::render('Admin/ITJobCategories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:it_job_requests_categories,name',
        ]);

        ITJobCategory::create($data);

        return redirect()->back()->with('success', 'Category added.');
    }

    public function update(Request $request, ITJobCategory $itJobCategory)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:it_job_requests_categories,name,' . $itJobCategory->id,
        ]);

        $itJobCategory->update($data);

        return redirect()->back()->with('success', 'Category updated.');
    }

    public function destroy(ITJobCategory $itJobCategory)
    {
        $inUse = ITJobRequest::where('category', $itJobCategory->name)->exists();

        if ($inUse) {
            return redirect()->back()->with('error', 'This category is used by existing IT Job Requests and cannot be deleted. Rename it instead if needed.');
        }

        $itJobCategory->delete();

        return redirect()->back()->with('success', 'Category deleted.');
    }
}
