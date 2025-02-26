<?php

namespace App\Http\Controllers;

use App\Models\Design;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Intervention\Image\Facades\Image;

class DesignController extends Controller
{
    // Show create design page
    // public function create(Request $request)
    // {
    //     $productId = $request->query('product_id');
        
    //     return Inertia::render('Design', [
    //         'products' => Product::all(),
    //         'selectedProductId' => $productId,
    //         'design' => null
    //     ]);
    // }

    public function create()
    {
        return Inertia::render('Design', [
            'products' => Product::all()
        ]);
    }
    
    // Edit an existing design
    public function edit(Design $design)
    {
        $this->authorize('update', $design);
        
        return Inertia::render('Design', [
            'products' => Product::all(),
            'selectedProductId' => $design->product_id,
            'design' => $design->load('product')
        ]);
    }
    
    // API endpoint to save a design
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'elements' => 'required|array',
            'name' => 'nullable|string|max:255',
        ]);
        
        // Create design
        $design = Design::create([
            'user_id' => Auth::id(),
            'product_id' => $request->product_id,
            'elements' => $request->elements,
            'name' => $request->name ?? 'Untitled Design',
        ]);
        
        // Generate and save thumbnail if image data provided
        if ($request->has('thumbnail_data')) {
            $this->saveThumbnail($design, $request->thumbnail_data);
        }
        
        // Create initial version
        $design->createVersion('Initial version');
        
        return response()->json($design, 201);
    }
    
    // API endpoint to update a design
    public function update(Request $request, Design $design)
    {
        $this->authorize('update', $design);
        
        $request->validate([
            'elements' => 'required|array',
            'name' => 'nullable|string|max:255',
            'create_version' => 'nullable|boolean',
            'version_comment' => 'nullable|string|max:255',
        ]);
        
        // Check if elements have changed and we should create a version
        $elementsChanged = json_encode($design->elements) !== json_encode($request->elements);
        $shouldCreateVersion = $request->create_version && $elementsChanged;
        
        // Update design
        $design->elements = $request->elements;
        if ($request->has('name')) {
            $design->name = $request->name;
        }
        $design->save();
        
        // Generate and save thumbnail if image data provided
        if ($request->has('thumbnail_data')) {
            $this->saveThumbnail($design, $request->thumbnail_data);
        }
        
        // Create a new version if requested
        if ($shouldCreateVersion) {
            $design->createVersion($request->version_comment);
        }
        
        return response()->json($design);
    }
    
    // API endpoint to list designs for current user
    public function index()
    {
        $designs = Design::where('user_id', Auth::id())
            ->with('product')
            ->orderBy('updated_at', 'desc')
            ->paginate(12);
            
        return response()->json($designs);
    }
    
    // API endpoint to get versions of a design
    public function versions(Design $design)
    {
        $this->authorize('view', $design);
        
        $versions = $design->versions()
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($versions);
    }
    
    // API endpoint to restore a specific version
    public function restoreVersion(Design $design, DesignVersion $version)
    {
        $this->authorize('update', $design);
        
        // Create a new version first to save the current state
        $design->createVersion('Auto-saved before restoring version #' . $version->id);
        
        // Restore from the selected version
        $design->elements = $version->elements;
        $design->save();
        
        return response()->json($design);
    }
    
    // Helper method to save thumbnail
    private function saveThumbnail(Design $design, $imageData)
    {
        // Extract base64 image data
        $imageData = substr($imageData, strpos($imageData, ',') + 1);
        $imageData = base64_decode($imageData);
        
        // Create image and resize
        $img = Image::make($imageData);
        $img->resize(400, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        
        // Save thumbnail
        $path = 'designs/thumbnails/' . $design->id . '.jpg';
        Storage::disk('public')->put($path, (string) $img->encode('jpg', 80));
        
        // Update design with thumbnail path
        $design->thumbnail = $path;
        $design->save();
        
        return $path;
    }
    
    // Delete a design
    public function destroy(Design $design)
    {
        $this->authorize('delete', $design);
        
        // Delete associated thumbnail
        if ($design->thumbnail) {
            Storage::disk('public')->delete($design->thumbnail);
        }
        
        // Delete design (versions will cascade delete)
        $design->delete();
        
        return response()->noContent();
    }
}