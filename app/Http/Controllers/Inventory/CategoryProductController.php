<?php namespace App\Http\Controllers\Inventory;


use App\Http\Controllers\Controller;
use App\Sheba\InventoryService\Services\CategoryProductService;
use Illuminate\Http\Request;

class CategoryProductController extends Controller
{
    private $categoryProductService;
    public function __construct(CategoryProductService $categoryProductService)
    {
        $this->categoryProductService = $categoryProductService;
    }

    public function getProducts(Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $category_products = $this->categoryProductService
            ->setMasterCategoryId($request->master_category_id)
            ->setCategoryId($request->category_id)
            ->setUpdatedAfter($request->updated_after)
            ->setOffset($request->offset)
            ->setLimit($request->limit)
            ->getProducts($partner->id);
        return http_response($request, null, 200, $category_products);
    }

}